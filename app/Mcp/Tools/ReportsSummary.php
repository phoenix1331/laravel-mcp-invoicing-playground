<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\BuildDashboardSummary;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ReportsSummary extends Tool
{
    use AuthorizesToolAccess;

    protected string $name = 'reports.summary';

    protected string $description = 'Get the dashboard figures for the caller\'s organisation as structured data: outstanding, overdue and paid-this-month totals, draft count, and revenue by month.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'outstanding' => $schema->number()->required(),
            'overdue' => $schema->number()->required(),
            'paid_this_month' => $schema->number()->required(),
            'drafts' => $schema->integer()->required(),
            'revenue_by_month' => $schema->array()
                ->items($schema->object([
                    'month' => $schema->string()->required(),
                    'total' => $schema->number()->required(),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'viewAny', Invoice::class)) {
            return $error;
        }

        $summary = app(BuildDashboardSummary::class)();

        $text = Response::text(
            "Outstanding: {$summary['outstanding']}, overdue: {$summary['overdue']}, ".
            "paid this month: {$summary['paid_this_month']}, drafts: {$summary['drafts']}."
        );

        return Response::make($text)->withStructuredContent($summary);
    }
}
