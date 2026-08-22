@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "{$title} - " . config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f6f9fc] antialiased">
    <div class="w-full max-w-md px-4">
        <div class="mb-8 text-center">
            <span class="text-xl font-semibold text-[#0a2540]">{{ config('app.name') }}</span>
        </div>

        <div class="rounded-lg bg-white p-8 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
