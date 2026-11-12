<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    @include('partials.head')
    <script>
        // Applied before paint so the collapsed sidebar does not flash open.
        try { if (localStorage.getItem('sidebar-collapsed') === '1') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
    </script>
    @stack('head')
</head>
<body>
    <div class="app" id="app">
        @include('partials.sidebar')

        <div class="app-main">
            @include('partials.topbar')

            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        if (document.documentElement.classList.contains('sidebar-collapsed')) document.getElementById('app').classList.add('is-collapsed');
    </script>
    @include('partials.flash')
    @stack('modals')
    @stack('scripts')
</body>
</html>
