<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\McpAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class McpAuditLogController extends Controller
{
    /**
     * Display the organisation's MCP activity log: every tool call with
     * who, what, arguments, outcome and duration.
     */
    public function __invoke(Request $request): View
    {
        return view('audit.mcp', [
            'logs' => $this->filteredLogs($request),
            'toolNames' => McpAuditLog::query()->distinct()->orderBy('tool_name')->pluck('tool_name'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, McpAuditLog>
     */
    private function filteredLogs(Request $request): LengthAwarePaginator
    {
        return McpAuditLog::query()
            ->with('user')
            ->when($request->filled('tool_name'), fn ($query) => $query->where('tool_name', $request->string('tool_name')->value()))
            ->when($request->filled('outcome'), fn ($query) => $query->where('outcome', $request->string('outcome')->value()))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();
    }
}
