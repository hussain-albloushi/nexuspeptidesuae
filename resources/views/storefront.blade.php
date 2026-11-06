<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0B4F8A">
    <title>{{ $seo['title'] ?? 'Zenvy Peptides' }}</title>

    <meta name="description" content="{{ $seo['description'] ?? 'Research-grade peptides with verified purity and full traceability.' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cantora+One&family=McLaren&family=Mona+Sans:wght@400;500;600;700&family=Noto+Sans+Mandaic&display=swap" rel="stylesheet">

    <script>
        window.__STOREFRONT_CONTENT__ = @json($content ?: new \stdClass());
    </script>

    @vite(['resources/css/storefront.css', 'resources/js/storefront/main.js'])
</head>
<body class="bg-background">
    <div id="app"></div>
</body>
</html>
