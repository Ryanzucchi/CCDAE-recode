<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Distrito') · CCDAE</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('ccdae-theme') === 'light' ? 'light' : 'dark'; } catch (_) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ccdae-shell">
    <main class="ccdae-content ccdae-modal-content">@yield('content')</main>
</body>
</html>
