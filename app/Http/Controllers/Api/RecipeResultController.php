<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeResult;
use App\Models\User;
use App\Support\Utf8;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Фотоотчёты: как блюдо выглядит в реальности.
 *
 * Загрузить фотоотчёт может и гость — под своим именем. Файл проходит
 * строгую проверку на стороне Laravel: только настоящие jpeg/png/webp
 * размером до 5 МБ. Имя файла — UUID, расширение — из определённого
 * сервером MIME-типа, а не из имени, присланного клиентом.
 */
class RecipeResultController extends Controller
{
    /** Сколько фотоотчётов на страницу. */
    private const PER_PAGE = 12;

    /** Допустимые MIME-типы файла. */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * GET /api/recipes/{slug}/results — фотоотчёты по рецепту.
     */
    public function index(Request $request, string $slug): Response
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $user = $request->user();

        $results = $recipe->results()
            ->with('user:id,name')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn (RecipeResult $result) => $this->resultPayload($result, $user));

        return response()->json($results);
    }

    /**
     * POST /api/recipes/{slug}/results — добавить фотоотчёт.
     *
     * Гость подписывается именем (guest_name), пользователь — от аккаунта.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $user = $request->user();

        // Текст чистим до проверки: мусорные байты в имени или комментарии
        // иначе ломали бы ответ на 500.
        $request->merge([
            'guest_name' => Utf8::clean($request->input('guest_name')),
            'comment' => Utf8::clean($request->input('comment')),
        ]);

        $rules = [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                // MIME-тип определяется по содержимому файла, а не по имени:
                // переименованный .php с картинкой внутри сюда не пройдёт.
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
            'guest_name' => ['nullable', 'string', 'max:50'],
        ];

        if (!$user) {
            // Гостю нечем подписаться, кроме имени.
            $rules['guest_name'] = ['required', 'string', 'max:50'];
        }

        $data = $request->validate($rules, [
            'image.required' => 'Прикрепите фотографию блюда.',
            'image.file' => 'Прикрепите фотографию блюда.',
            'image.image' => 'Можно загружать только изображения.',
            'image.mimes' => 'Допустимые форматы: jpg, png, webp.',
            'image.mimetypes' => 'Допустимые форматы: jpg, png, webp.',
            'image.max' => 'Максимальный размер файла — 5 МБ.',
            'comment.max' => 'Комментарий слишком длинный (максимум 1000 символов).',
            'guest_name.required' => 'Напишите, как вас зовут.',
            'guest_name.max' => 'Имя слишком длинное (максимум 50 символов).',
        ]);

        $file = $request->file('image');
        $extension = self::ALLOWED_MIME[$file->getMimeType()] ?? 'jpg';
        $uniqueName = Str::uuid().'.'.$extension;

        // Файлы публичные: симлинк public/storage -> storage/app/public.
        $path = $file->storeAs('results', $uniqueName, 'public');

        $result = $recipe->results()->create([
            'user_id' => $user?->id,
            'guest_name' => $user ? null : trim((string) $data['guest_name']),
            'comment' => $data['comment'] ?? null,
            'image_path' => $path,
        ]);

        return response()->json([
            'message' => 'Фотоотчёт добавлен.',
            'data' => [
                'result' => $this->resultPayload($result->load('user:id,name'), $user),
            ],
        ], 201);
    }

    /**
     * DELETE /api/recipes/{slug}/results/{id} — удалить фотоотчёт.
     */
    public function destroy(Request $request, string $slug, int $id): JsonResponse
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $result = $recipe->results()->find($id);

        if (!$result) {
            return response()->json(['message' => 'Фотоотчёт не найден.'], 404);
        }

        if (!Gate::forUser($request->user())->allows('delete', $result)) {
            return response()->json([
                'message' => 'Удалить фотоотчёт может только его автор или администратор.',
            ], 403);
        }

        $path = $result->image_path;
        $result->delete();

        // Файл удаляем с диска, чтобы не копить мусор: без картинки
        // отчёт всё равно не нужен.
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Фотоотчёт удалён.']);
    }

    /** Рецепт по slug или по id — как и в карточке. */
    private function findRecipe(string $slugOrId): ?Recipe
    {
        $isNumeric = ctype_digit($slugOrId);

        return Recipe::query()
            ->active()
            ->where($isNumeric ? 'id' : 'slug', $slugOrId)
            ->first();
    }

    /** Ответ по одному фотоотчёту. */
    private function resultPayload(RecipeResult $result, ?User $user): array
    {
        return [
            'id' => $result->id,
            'image_url' => $result->imageUrl(),
            'comment' => $result->comment,
            'created_at' => $result->created_at?->toIso8601String(),
            'user' => [
                'id' => $result->user?->id,
                'name' => $result->authorName(),
            ],
            'is_guest' => $result->isGuest(),
            'can_delete' => Gate::forUser($user)->allows('delete', $result),
        ];
    }
}
