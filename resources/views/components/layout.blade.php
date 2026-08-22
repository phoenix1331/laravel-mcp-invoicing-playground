@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "{$title} - " . config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased text-[#425466]" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-slate-900/30 md:hidden"
            style="display: none;"
        ></div>

        <aside
            x-cloak
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 border-r border-[#e3e8ee] bg-white transition-transform duration-200 ease-in-out md:static md:translate-x-0"
        >
            <x-sidebar-nav />
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-top-bar />

            <main class="flex-1 bg-[#f6f9fc] p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
