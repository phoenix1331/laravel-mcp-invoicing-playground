@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'w-full rounded-md bg-[#f87171] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#ef4444] focus:outline-none focus:ring-2 focus:ring-[#f87171] focus:ring-offset-2',
    ]) }}
>
    {{ $slot }}
</button>
