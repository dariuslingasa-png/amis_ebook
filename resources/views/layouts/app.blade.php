<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'AMIS eBook Portal' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/AMIS_Logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <div class="ebook-shell">
        <header class="ebook-topbar">
            <div class="ebook-topbar-inner">
                <a href="{{ route('books.index') }}" class="ebook-brand">
                    <img src="{{ asset('images/AMIS_Logo.svg') }}" alt="AMIS Logo">
                    <span>
                        <strong>AMIS</strong>
                        <small>eBook</small>
                    </span>
                </a>

                <nav class="ebook-nav">
                    <a href="{{ route('books.index') }}" class="ebook-nav-link {{ request()->routeIs('books.*') ? 'is-active' : '' }}">
                        <i data-lucide="library" class="w-4 h-4"></i>
                        Catalog
                    </a>

                    @auth
                        @if(Auth::user()->role === 'admin')
                            <a href="{{ route('admin.books.index') }}" class="ebook-nav-link {{ request()->routeIs('admin.books.index') ? 'is-active' : '' }}">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                Admin
                            </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="ebook-nav-button is-danger">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                Sign Out
                            </button>
                        </form>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="ebook-main">
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition class="ebook-alert">
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        {{ session('success') }}
                    </span>
                    <button type="button" @click="show = false" class="ebook-icon-btn" aria-label="Dismiss alert">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="ebook-footer">
            &copy; {{ date('Y') }} Al Munawwara Islamic School eBook. All rights reserved.
        </footer>
    </div>

    <script>
        const refreshEbookIcons = () => window.lucide?.createIcons();
        document.addEventListener('DOMContentLoaded', refreshEbookIcons);
        window.addEventListener('load', refreshEbookIcons);
        setTimeout(refreshEbookIcons, 100);
    </script>
</body>
</html>
