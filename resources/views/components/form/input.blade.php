@props(['label', 'name', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-[#0a2540]">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#0a2540] shadow-sm focus:border-[#635bff] focus:outline-none focus:ring-1 focus:ring-[#635bff]',
        ]) }}
    >
    @error($name)
        <p class="mt-1.5 text-sm text-[#df1b41]">{{ $message }}</p>
    @enderror
</div>
