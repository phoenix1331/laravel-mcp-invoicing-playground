<?php

namespace App\Http\Controllers;

use App\Actions\BuildDashboardSummary;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $summary = app(BuildDashboardSummary::class)();

        $recentInvoices = Invoice::query()
            ->with('customer')
            ->latest('issue_date')
            ->limit(10)
            ->get();

        return view('dashboard', [
            'summary' => $summary,
            'recentInvoices' => $recentInvoices,
        ]);
    }
}
