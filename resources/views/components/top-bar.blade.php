<header class="flex h-16 items-center justify-between border-b border-[#e3e8ee] bg-white px-4 md:px-6">
    <button
        @click="sidebarOpen = true"
        type="button"
        class="rounded-md p-2 text-[#425466] hover:bg-[#f6f9fc] md:hidden"
        aria-label="Open sidebar"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
        </svg>
    </button>

    <div class="flex items-center gap-2 text-sm font-medium text-[#0a2540]">
        <span>{{ optional(optional(auth()->user())->organisation)->name ?? 'Organisation' }}</span>
    </div>

    <div class="flex items-center gap-3">
        <span class="text-sm text-[#425466]">{{ optional(auth()->user())->name ?? 'Guest' }}</span>
    </div>
</header>
