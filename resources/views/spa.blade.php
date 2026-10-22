{{--
    Оболочка SPA «Что приготовить?».

    Это собранный Vite index.html: он один и тот же и для сервера, и для
    service worker'а — тот при работе без сети отдаёт его из кеша. Поэтому
    страница выглядит одинаково онлайн и офлайн.
--}}
@php
    $shellPath = public_path('frontend/index.html');
@endphp
@if(is_file($shellPath))
    {!! file_get_contents($shellPath) !!}
@else
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Что приготвить?</title>
    </head>
    <body>
        <p style="padding:40px;text-align:center;font-family:sans-serif">
            Фронтенд не собран. Выполните: <code>cd frontend &amp;&amp; npm install &amp;&amp; npm run build</code>
        </p>
    </body>
    </html>
@endif
