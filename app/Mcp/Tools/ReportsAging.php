<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ReportsAging extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'reports.aging';

    protected string $description = 'Get receivables aging buckets for the caller\'s organisation: sent invoices grouped by how many days overdue they are.';

    /**
     * @var array<string, array{0: int, 1: int|null}>
     */
    private const BUCKETS = [
        'current' => [0, 0],
        '1_30' => [1, 30],
        '31_60' => [31, 60],
        '61_90' => [61, 90],
        'over_90' => [91, null],
    ];

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'buckets' => $schema->object([
                'current' => $schema->number()->required(),
                '1_30' => $schema->number()->required(),
                '31_60' => $schema->number()->required(),
                '61_90' => $schema->number()->required(),
                'over_90' => $schema->number()->required(),
            ])->required(),
            'total_outstanding' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'viewAny', Invoice::class)) {
            return $error;
        }

        $today = Carbon::today();

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->get(['due_date', 'total']);

        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);

        foreach ($invoices as $invoice) {
            $daysOverdue = (int) max(0, $today->diffInDays($invoice->due_date->startOfDay(), false) * -1);

            $bucket = $this->bucketFor($daysOverdue);

            $buckets[$bucket] += (float) $invoice->total;
        }

        $totalOutstanding = array_sum($buckets);

        $text = Response::text("Total outstanding: {$totalOutstanding}. Over 90 days: {$buckets['over_90']}.");

        return Response::make($text)->withStructuredContent([
            'buckets' => $buckets,
            'total_outstanding' => $totalOutstanding,
        ]);
    }

    private function bucketFor(int $daysOverdue): string
    {
        foreach (self::BUCKETS as $bucket => [$min, $max]) {
            if ($daysOverdue >= $min && ($max === null || $daysOverdue <= $max)) {
                return $bucket;
            }
        }

        return 'over_90';
    }
}
