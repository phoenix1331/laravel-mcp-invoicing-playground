@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'w-full rounded-md bg-[#635bff] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#5147e5] focus:outline-none focus:ring-2 focus:ring-[#635bff] focus:ring-offset-2',
    ]) }}
>
    {{ $slot }}
</button>
