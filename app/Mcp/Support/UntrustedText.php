<?php

declare(strict_types=1);

namespace App\Mcp\Support;

/**
 * Wraps user-supplied free text (invoice notes, line descriptions, customer
 * names/addresses) in an explicit delimiter before it reaches a tool
 * response, so a prompt injection payload arrives clearly labelled as data
 * rather than blending into the model's instructions. Mirrors the labelling
 * described in the invoicing://guidelines resource.
 */
class UntrustedText
{
    public static function wrap(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return "<untrusted-data>{$value}</untrusted-data>";
    }
}
