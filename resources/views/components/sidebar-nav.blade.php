<div class="flex h-full flex-col">
    <div class="flex h-16 items-center px-6">
        <span class="text-lg font-semibold text-[#0a2540]">{{ config('app.name') }}</span>
    </div>

    <nav class="flex-1 space-y-1 px-3">
        <a href="{{ url('/dashboard') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            Dashboard
        </a>
        <a href="{{ url('/invoices') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            Invoices
        </a>
        <a href="{{ url('/customers') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            Customers
        </a>
        <a href="{{ url('/settings/organisation') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            Settings
        </a>
        <a href="{{ route('settings.tokens') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            API tokens
        </a>
        <a href="{{ url('/settings/mcp') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            MCP console
        </a>
        <a href="{{ url('/audit/mcp') }}" class="flex items-center rounded-md px-3 py-2 text-sm font-medium text-[#425466] hover:bg-[#f6f9fc] hover:text-[#0a2540]">
            MCP activity
        </a>
    </nav>
</div>
