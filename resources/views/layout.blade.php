<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visits — @yield('title', 'Dashboard')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
        const html = document.documentElement;
        const stored = localStorage.getItem('visits-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (stored ? stored === 'dark' : prefersDark) html.classList.replace('light', 'dark');
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen text-sm text-gray-800 dark:text-gray-200 transition-colors">

<nav class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-3 flex items-center gap-4">
    <a href="{{ route('visits.index') }}" class="font-semibold text-gray-900 dark:text-gray-100 text-base tracking-tight">Visits</a>
    <a href="{{ route('visits.index') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Overview</a>
    <a href="{{ route('visits.sessions') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Sessions</a>
    <a href="{{ route('visits.visitors') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Visitors</a>
    <a href="{{ route('visits.me') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Whoami</a>
    <span class="text-gray-400 dark:text-gray-500 text-xs">{{ config('app.env') }}</span>
    <div class="ml-auto">
        <button onclick="toggleTheme()"
            class="text-xs px-3 py-1.5 rounded border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <span class="dark:hidden">&#9789; Dark</span>
            <span class="hidden dark:inline">&#9728; Light</span>
        </button>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-6 py-6">
    @yield('content')
</main>

<script>
    function toggleTheme() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.replace('dark', 'light');
            localStorage.setItem('visits-theme', 'light');
        } else {
            html.classList.replace('light', 'dark');
            localStorage.setItem('visits-theme', 'dark');
        }
    }
</script>

@stack('scripts')

</body>
</html>
