<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IRPF')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
    <script>
        window.csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-full items-center justify-between px-4 sm:px-6 lg:px-10 py-4">
                <a href="{{ route('clients.index') }}" class="text-lg font-semibold tracking-tight text-slate-900">
                    IRPF – Clientes
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('clients.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Dashboard</a>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-full px-4 sm:px-6 lg:px-10 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
