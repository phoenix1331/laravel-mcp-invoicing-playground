<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

class BuildDashboardSummary
{
    /**
     * @return array{
     *     outstanding: float,
     *     overdue: float,
     *     paid_this_month: float,
     *     drafts: int,
     *     revenue_by_month: array<int, array{month: string, total: float}>,
     * }
     */
    public function __invoke(): array
    {
        return [
            'outstanding' => (float) Invoice::query()->where('status', InvoiceStatus::Sent)->sum('total'),
            'overdue' => (float) Invoice::query()
                ->where('status', InvoiceStatus::Sent)
                ->whereDate('due_date', '<', Carbon::today())
                ->sum('total'),
            'paid_this_month' => (float) Invoice::query()
                ->where('status', InvoiceStatus::Paid)
                ->whereBetween('updated_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->sum('total'),
            'drafts' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
            'revenue_by_month' => $this->revenueByMonth(),
        ];
    }

    /**
     * @return array<int, array{month: string, total: float}>
     */
    private function revenueByMonth(): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => Carbon::now()->subMonths($offset)->startOfMonth());

        return $months->map(function (Carbon $month) {
            $total = Invoice::query()
                ->where('status', InvoiceStatus::Paid)
                ->whereBetween('updated_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('total');

            return [
                'month' => $month->format('M'),
                'total' => (float) $total,
            ];
        })->values()->all();
    }
}
