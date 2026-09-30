<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoGuard - @yield('title', 'EWS System')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { slate: { 950: '#020617' } } } } }
    </script>
    @stack('head')
    <style>
        body { background-color: #020617; color: white; margin: 0; padding: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 shrink-0 z-50">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center space-x-8">
                    <a href="{{ route('dashboard') }}" class="text-white font-black text-xl tracking-wider flex items-center gap-2">
                        <span class="w-3 h-3 bg-cyan-500 rounded-full animate-pulse"></span>
                        GEOGUARD
                    </a>
                    <div class="hidden md:block">
                        <div class="flex items-baseline space-x-4">
                            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-slate-800 text-cyan-400 border border-slate-700' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} px-3 py-2 rounded-md text-sm font-bold transition-colors">HUD Dashboard</a>
                            <a href="{{ route('prediction') }}" class="{{ request()->routeIs('prediction') ? 'bg-slate-800 text-cyan-400 border border-slate-700' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} px-3 py-2 rounded-md text-sm font-bold transition-colors">Geo-Analysis</a>
                            <a href="{{ route('early-warning') }}" class="{{ request()->routeIs('early-warning') ? 'bg-slate-800 text-cyan-400 border border-slate-700' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} px-3 py-2 rounded-md text-sm font-bold transition-colors">Early Warning</a>
                            <a href="{{ route('incidents') }}" class="{{ request()->routeIs('incidents') ? 'bg-slate-800 text-cyan-400 border border-slate-700' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} px-3 py-2 rounded-md text-sm font-bold transition-colors">Incident Logs</a>
                            <a href="{{ route('dispatcher') }}" class="{{ request()->routeIs('dispatcher') ? 'bg-slate-800 text-cyan-400 border border-slate-700' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }} px-3 py-2 rounded-md text-sm font-bold transition-colors">Dispatcher Panel</a>
                        </div>
                    </div>
                </div>
                <div class="text-xs text-slate-500 font-mono">
                    SYS.VER. 2.1.0 // ONLINE
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow overflow-auto relative bg-slate-950">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
