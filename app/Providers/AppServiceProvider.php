<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    /**
     * Лимиты частоты запросов к API.
     *
     * Без них любой может положить приложение: /api/search делает
     * LIKE-сканирование по таблицам, а /api/fridge/match — тяжёлый
     * перебор ингредиентов. Ограничения заданы именованными лимитерами,
     * чтобы в маршрутах не было разбросанных магических чисел.
     */
    private function registerRateLimiters(): void
    {
        // Чтение каталога — самый частый запрос, лимит пониже, чем у поиска.
        RateLimiter::for('api-read', fn (Request $request) => $this->limit($request, 120, 400));

        // Поиск и подбор по холодильнику — тяжёлые к БД.
        RateLimiter::for('api-search', fn (Request $request) => $this->limit($request, 60, 200));

        // Детальная карточка рецепта: счётчик просмотров пишется на каждый
        // запрос, поэтому лимит выше.
        RateLimiter::for('api-detail', fn (Request $request) => $this->limit($request, 240, 800));

        // Запись: загрузка изображений, избранное, комментарии, смена пароля.
        RateLimiter::for('api-write', fn (Request $request) => $this->limit($request, 20, 60));

        // Комментарии гостей: 3 за 15 минут с одного адреса.
        //
        // Гость пишет под именем, и удалить свой комментарий он не может —
        // поэтому завалить рецепт анонимными записями можно было бы легко.
        // Авторизованных этот лимит не касается: у них свой общий лимит
        // на запись.
        RateLimiter::for('guest-comment', function (Request $request) {
            if ($this->isAuthenticated($request)) {
                return Limit::none();
            }

            return Limit::perMinutes(15, 3)
                ->by('guest:'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Слишком много комментариев. Попробуйте снова через 15 минут.',
                    ], 429, $headers);
                });
        });

        // Вход — жёстче: перебор пароля должен упираться в лимит.
        RateLimiter::for('api-login', fn (Request $request) => $this->limit($request, 10, 10));

        // Регистрация и сброс пароля — редкие операции, но их любят
        // использовать для перебора существующих адресов.
        RateLimiter::for('api-register', fn (Request $request) => $this->limit($request, 5, 20));

        RateLimiter::for('api-reset', fn (Request $request) => $this->limit($request, 5, 20));
    }

    /**
     * Авторизован ли запрос.
     *
     * Проверяем токен сами, а не через $request->user(): middleware
     * ограничения частоты выполняются раньше middleware авторизации
     * (так устроена очередь middleware в Laravel), и на момент проверки
     * пользователь ещё не определён.
     */
    private function isAuthenticated(Request $request): bool
    {
        if ($request->user()) {
            return true;
        }

        // Токен есть, но не рабочий — запрос считаем гостевым.
        return (bool) $request->bearerToken() && auth('sanctum')->check();
    }

    /**
     * Двойной лимит: по пользователю и по IP.
     *
     * Лимит только по пользователю обходится регистрацией десятка аккаунтов,
     * лимит только по IP — сменой адреса. Оба счётчика работают одновременно,
     * и запрос проходит, только если не исчерпан ни один из них.
     *
     * Для гостя «пользовательский» счётчик — это тот же IP: неавторизованный
     * клиент ограничен числом запросов с одного адреса.
     *
     * @return array<int, Limit>
     */
    private function limit(Request $request, int $perUser, int $perIp): array
    {
        $ip = $request->ip();
        $user = $request->user();

        $limits = [
            // Общий потолок с одного адреса — он же ограничивает гостей.
            Limit::perMinute($perIp)->by('ip:'.$ip),
        ];

        if ($user) {
            $limits[] = Limit::perMinute($perUser)->by('user:'.$user->id);
        } else {
            $limits[] = Limit::perMinute($perUser)->by('guest:'.$ip);
        }

        return $limits;
    }
}
