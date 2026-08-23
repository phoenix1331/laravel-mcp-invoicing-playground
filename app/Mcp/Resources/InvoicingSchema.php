<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Uri('invoicing://schema')]
#[Description('The domain model, so the client understands relationships between organisations, users, customers, invoices and invoice lines.')]
class InvoicingSchema extends Resource
{
    protected string $mimeType = 'text/markdown';

    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
            # Domain model

            ## Organisation

            The tenancy boundary. Every other model belongs to exactly one
            organisation, and a caller can only ever see data within their own.

            - `id`, `name`, `slug`, `address`, `vat_number`, `logo_path`

            ## User

            A member of one organisation, with a role that gates write access.

            - `id`, `organisation_id`, `name`, `email`, `role`
            - Roles: `owner` (full access, including delete and team management),
              `member` (create and edit, no delete or team management), `viewer`
              (read-only)

            ## Customer

            Belongs to one organisation. Has many invoices.

            - `id`, `organisation_id`, `name`, `email`, `address`
            - Can only be deleted if it has no invoices

            ## Invoice

            Belongs to one organisation and one customer; created by one user.
            Has many invoice lines.

            - `id`, `organisation_id`, `customer_id`, `created_by_user_id`,
              `number`, `status`, `issue_date`, `due_date`, `currency`, `notes`,
              `subtotal`, `tax_rate`, `tax_total`, `total`
            - `status` is one of `draft`, `sent`, `paid`, `void` - see the
              `invoicing://guidelines` resource for the allowed transitions
            - `subtotal`, `tax_total` and `total` are always derived from the
              invoice's lines, never set directly

            ## Invoice line

            Belongs to one invoice.

            - `id`, `invoice_id`, `description`, `quantity`, `unit_price`,
              `line_total`, `position`
            - `line_total` is `quantity * unit_price`, calculated server-side
            MARKDOWN);
    }
}
