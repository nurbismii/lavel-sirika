<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'SIRIKA' }}</title>
    <meta name="description" content="{{ $pageDescription ?? 'SIRIKA' }}">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <script src="{{ mix('js/app.js') }}" defer></script>
</head>
<body>
    <div class="app-shell" x-data="{ mobileNavOpen: false }">
        <aside class="sidebar" :class="{ 'open': mobileNavOpen }">
            <p class="brand">SIRIKA</p>
            <p class="brand-subtitle">Sistem Rute Izin Kendaraan</p>

            <nav aria-label="Navigasi utama">
                <ul class="nav-list">
                    <li>
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="{{ url('/road-segments') }}">
                            Master Rute
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="{{ url('/imports') }}">
                            Import Excel
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="{{ url('/permits') }}">
                            Izin Kendaraan
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="{{ url('/scan') }}">
                            Scan QR
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <button class="button mobile-nav-toggle" type="button" x-on:click="mobileNavOpen = ! mobileNavOpen">
                    Menu
                </button>

                <div class="topbar-meta">
                    <span class="topbar-user">{{ auth()->user()->name }}</span>
                    <span class="topbar-role">{{ auth()->user()->role }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button" type="submit">Logout</button>
                </form>
            </header>

            <main class="content">
                <div class="content-inner">
                    <h1 class="page-title">{{ $pageTitle ?? 'SIRIKA' }}</h1>

                    @isset($pageDescription)
                        <p class="page-description">{{ $pageDescription }}</p>
                    @endisset

                    @if (session('status'))
                        <x-alert type="success" class="layout-gap">
                            {{ session('status') }}
                        </x-alert>
                    @endif

                    <div class="layout-gap">
                        @yield('content')
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
