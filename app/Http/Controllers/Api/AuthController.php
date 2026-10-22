<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация по токену Sanctum.
 *
 * Токен отдаётся клиенту один раз — при регистрации и при входе.
 * Дальше он передаётся в заголовке Authorization: Bearer <token>.
 *
 * Регистрация обычная, без подтверждения почты: сейчас в приложении
 * нет отправки писем, а подтверждение без неё только мешало бы.
 */
class AuthController extends Controller
{
    /** POST /api/auth/register — регистрация нового пользователя. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'email.unique' => 'Пользователь с таким email уже зарегистрирован.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // модель хеширует сама (cast 'hashed')
        ]);

        return response()->json([
            'message' => 'Регистрация прошла успешно.',
            'data' => $this->userPayload($user),
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    /** POST /api/auth/login — вход и выдача токена. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Одинаковый текст ошибки на «нет такого email» и «неверный пароль»:
        // иначе форма входа превращается в способ узнать, какие адреса
        // зарегистрированы.
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        return response()->json([
            'message' => 'Вы вошли в аккаунт.',
            'data' => $this->userPayload($user),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    /** POST /api/auth/logout — отзыв текущего токена. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // У сессионной авторизации currentAccessToken() — null,
        // тогда просто ничего не отзываем.
        $token?->delete();

        return response()->json(['message' => 'Вы вышли из аккаунта.']);
    }

    /** GET /api/auth/me — текущий пользователь. */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * POST /api/auth/password — смена пароля.
     *
     * После смены отзываются все остальные токены пользователя: если пароль
     * меняют из-за подозрения на взлом, старые устройства должны выйти.
     * Текущий токен остаётся рабочим — иначе пришлось бы входить заново
     * сразу после смены.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'current_password.required' => 'Введите текущий пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Новый пароль должен быть не короче 8 символов.',
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Текущий пароль указан неверно.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->delete();

        return response()->json(['message' => 'Пароль изменён.']);
    }

    /**
     * POST /api/auth/forgot-password — запрос ссылки для сброса пароля.
     *
     * Ответ одинаковый независимо от того, зарегистрирован адрес или нет:
     * иначе форма сброса пароля превращалась бы в способ узнать, какие
     * адреса есть в системе. В локальной среде (почта не настроена) ссылка
     * возвращается в ответе — иначе восстановить пароль на dev-стенде
     * было бы невозможно.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $data['email'];
        $token = null;

        Password::sendResetLink(['email' => $email], function ($user, $resetToken) use (&$token) {
            $token = $resetToken;

            // С колбэком брокер сам письмо не отправляет — отправляем мы.
            $user->sendPasswordResetNotification($resetToken);

            return null;
        });

        $response = [
            'message' => 'Если такой адрес зарегистрирован, мы отправили на него ссылку для смены пароля.',
        ];

        if ($token && app()->environment('local')) {
            $response['data'] = [
                'reset_url' => url('/reset-password?token='.$token.'&email='.urlencode($email)),
            ];
        }

        return response()->json($response);
    }

    /**
     * POST /api/auth/reset-password — установка нового пароля по токену.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен быть не короче 8 символов.',
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                // Поле подтверждения не имеет своих правил, поэтому в
                // валидированные данные оно не попадает — берём из запроса.
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();

                // Сбросили пароль — все прежние устройства выходят из аккаунта.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => match ($status) {
                    Password::INVALID_TOKEN => 'Ссылка для сброса пароля недействительна или уже использована.',
                    Password::INVALID_USER => 'Пользователь с таким адресом не найден.',
                    Password::RESET_THROTTLED => 'Слишком много попыток. Попробуйте позже.',
                    default => 'Не удалось сбросить пароль.',
                },
            ]);
        }

        return response()->json(['message' => 'Пароль обновлён. Теперь можно войти с новым паролем.']);
    }

    /** Публичные данные пользователя — без пароля и прочих секретов. */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
