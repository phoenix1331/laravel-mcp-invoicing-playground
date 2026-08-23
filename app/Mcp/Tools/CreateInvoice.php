<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\AllocateInvoiceNumber;
use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Enums\InvoiceStatus;
use App\Mcp\Concerns\AuthorizesToolAccess;
use App\Mcp\Concerns\IsWriteTool;
use App\Mcp\Support\Idempotency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class CreateInvoice extends Tool
{
    use AuthorizesToolAccess, IsWriteTool;

    protected string $name = 'invoices.create';

    protected string $description = 'Create a new draft invoice with one or more line items. Totals are always calculated server-side from the lines.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->required()->description('The customer this invoice is for.'),
            'issue_date' => $schema->string()->format('date')->required()->description('The issue date (YYYY-MM-DD).'),
            'due_date' => $schema->string()->format('date')->required()->description('The due date (YYYY-MM-DD), on or after the issue date.'),
            'currency' => $schema->string()->required()->description('A three-letter ISO currency code, e.g. GBP.'),
            'tax_rate' => $schema->number()->required()->description('The tax rate as a percentage, e.g. 20 for 20%.'),
            'notes' => $schema->string()->description('Optional free-text notes.'),
            'lines' => $schema->array()
                ->items($schema->object([
                    'description' => $schema->string()->required(),
                    'quantity' => $schema->number()->required(),
                    'unit_price' => $schema->number()->required(),
                ]))
                ->required()
                ->description('At least one line item.'),
            'idempotency_key' => $schema->string()
                ->description('Optional. Reusing the same key within 24h replays the original result instead of creating a second invoice - use this if a call might be retried.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'number' => $schema->string()->required(),
            'status' => $schema->string()->enum(InvoiceStatus::class)->required(),
            'total' => $schema->number()->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($error = $this->authorizeTool($request, 'create', Invoice::class)) {
            return $error;
        }

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->find($data['customer_id']);

        if (! $customer instanceof Customer) {
            return Response::error("No customer was found with id {$data['customer_id']}.");
        }

        /** @var User $user */
        $user = $request->user();

        return app(Idempotency::class)->remember($this->name(), $data['idempotency_key'] ?? null, $user->organisation_id, function () use ($data, $user) {
            return $this->createInvoice($data, $user);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createInvoice(array $data, User $user): ResponseFactory
    {
        $invoice = DB::transaction(function () use ($data, $user) {
            $number = app(AllocateInvoiceNumber::class)($user->organisation()->firstOrFail());

            $invoice = Invoice::create([
                ...collect($data)->except(['lines', 'idempotency_key'])->all(),
                'organisation_id' => $user->organisation_id,
                'created_by_user_id' => $user->id,
                'number' => $number,
                'status' => InvoiceStatus::Draft,
            ]);

            foreach ($data['lines'] as $position => $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => app(CalculateLineTotal::class)((float) $line['quantity'], (float) $line['unit_price']),
                    'position' => $position,
                ]);
            }

            return $invoice;
        });

        app(RecalculateInvoiceTotals::class)($invoice);

        $summary = Response::text("Created draft invoice {$invoice->number} for {$invoice->currency} {$invoice->total}.");

        return Response::make($summary)->withStructuredContent([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'total' => (float) $invoice->total,
        ]);
    }
}
