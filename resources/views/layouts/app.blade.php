<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Подбор земельных участков' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/swiper@11/swiper-bundle.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-overlay {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .countdown-expired { color: #ef4444; }
        .lot-card-on-board {
            border: 1px solid #62bdff !important;
            background-color: #e0f0ff !important;
            opacity: 1 !important;
            filter: none !important;
        }
        .lot-card-on-board .lot-card-content { display: flex !important; }
        .lot-card-on-board .lot-card-collapsed { display: none !important; }
        .lot-card-faded {
            opacity: 0.4;
            filter: grayscale(100%);
        }
        .lot-card-faded .lot-card-content {
            display: none;
        }
        .lot-card-faded .lot-card-collapsed {
            display: block !important;
        }
        .swiper-lazy-preloader {
            background-color: rgba(255,255,255,0.8);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        .modal-backdrop {
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .tab-active { border-bottom: 2px solid #3b82f6; color: #3b82f6; }
        .maplibregl-map { width: 100%; height: 100%; }
        .map-container { width: 100%; height: 66vh; min-height: 500px; }
        .yougile-badge {
            position: absolute;
            top: -3px;
            left: -3px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            overflow: hidden;
        }
        .viewed-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 20px;
            height: 20px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        aside.flex.h-full.w-\[25rem\].min-w-\[25rem\].flex-col.bg-neutral-0 {
            display: none;
        }
    </style>
    @stack('styles')
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@6.5.0/dist/maplibre-gl.css" />
    <script type="module">
        import * as maplibregl from 'https://unpkg.com/maplibre-gl@6.5.0/dist/maplibre-gl.mjs';
        window.maplibregl = maplibregl;
        window.dispatchEvent(new Event('maplibre-loaded'));
    </script>
</head>
<body class="bg-gray-50 font-sans min-h-screen">
    @yield('content')
    @stack('scripts')
</body>
</html>
