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

        // Вход — жёстче: перебор пароля должен упираться в лимит.
        RateLimiter::for('api-login', fn (Request $request) => $this->limit($request, 10, 10));

        // Регистрация и сброс пароля — редкие операции, но их любят
        // использовать для перебора существующих адресов.
        RateLimiter::for('api-register', fn (Request $request) => $this->limit($request, 5, 20));

        RateLimiter::for('api-reset', fn (Request $request) => $this->limit($request, 5, 20));
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
