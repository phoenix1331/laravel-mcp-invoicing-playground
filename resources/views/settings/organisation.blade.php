<x-layout title="Organisation settings">
    <h1 class="text-2xl font-semibold text-[#0a2540]">Organisation settings</h1>
    <p class="mt-1 text-sm text-[#425466]">Name, address, VAT number and logo shown on invoices. Owner role only.</p>

    <div class="mt-6 rounded-lg bg-white p-6 shadow-[0_1px_3px_rgba(10,37,64,0.08)] ring-1 ring-slate-900/5">
        <form method="POST" action="{{ route('settings.organisation.update') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <x-form.input label="Name" name="name" value="{{ old('name', $organisation->name) }}" required autofocus />
            <x-form.input label="Address" name="address" value="{{ old('address', $organisation->address) }}" />
            <x-form.input label="VAT number" name="vat_number" value="{{ old('vat_number', $organisation->vat_number) }}" />

            <div>
                <label for="logo" class="mb-1.5 block text-sm font-medium text-[#0a2540]">Logo</label>

                <div class="flex items-center gap-4">
                    @if ($organisation->logo_path)
                        <img src="{{ Storage::disk('public')->url($organisation->logo_path) }}" alt="{{ $organisation->name }} logo" class="h-12 w-12 rounded-md object-cover ring-1 ring-[#e3e8ee]">
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-md bg-[#f6f9fc] text-xs text-[#8792a2] ring-1 ring-[#e3e8ee]">None</div>
                    @endif

                    <input
                        id="logo"
                        name="logo"
                        type="file"
                        accept="image/*"
                        class="block flex-1 text-sm text-[#425466] file:mr-3 file:rounded-md file:border file:border-[#e3e8ee] file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[#425466] hover:file:bg-[#f6f9fc]"
                    >
                </div>
                <p class="mt-1.5 text-xs text-[#8792a2]">PNG or JPG, up to 2MB.</p>
                @error('logo')
                    <p class="mt-1.5 text-sm text-[#df1b41]">{{ $message }}</p>
                @enderror
            </div>

            <div class="w-40">
                <x-form.button type="submit">Save changes</x-form.button>
            </div>
        </form>
    </div>
</x-layout>
