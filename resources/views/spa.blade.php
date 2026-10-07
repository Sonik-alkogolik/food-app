{{-- SPA «Что приготовить?» — собранный бандл из public/frontend (vite build) --}}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Что приготовить? — найди свой идеальный рецепт из 1000 рецептов с фото</title>
    <meta name="description" content="Каталог из 1000 домашних рецептов с пошаговыми фотографиями: завтраки, супы, вторые блюда, салаты, десерты и выпечка.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Что приготовить? — 1000 рецептов с фото">
    <meta property="og:description" content="Найди свой идеальный рецепт! Пошаговые фотографии, фильтры, поиск по ингредиентам.">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @php
        $manifestPath = public_path('frontend/.vite/manifest.json');
        $entry = is_file($manifestPath) ? (json_decode(file_get_contents($manifestPath), true)['index.html'] ?? null) : null;
    @endphp
    @if($entry)
        <script type="module" src="/frontend/{{ $entry['file'] }}"></script>
        @foreach($entry['css'] ?? [] as $css)
            <link rel="stylesheet" href="/frontend/{{ $css }}">
        @endforeach
    @else
        <p style="padding:40px;text-align:center;font-family:sans-serif">
            Фронтенд не собран. Выполните: <code>cd frontend &amp;&amp; npm install &amp;&amp; npm run build</code>
        </p>
    @endif
</head>
<body>
<div id="app"></div>
</body>
</html>
