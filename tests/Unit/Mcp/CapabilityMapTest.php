<?php

declare(strict_types=1);

use App\Mcp\Support\CapabilityMap;

it('maps every route it knows about to a tool name', function () {
    foreach (CapabilityMap::mapped() as $routeName => $toolName) {
        expect($routeName)->toBeString()->not->toBeEmpty()
            ->and($toolName)->toBeString()->not->toBeEmpty();
    }
});

it('gives every exempt route a non-empty reason', function () {
    foreach (CapabilityMap::exempt() as $routeName => $reason) {
        expect($routeName)->toBeString()->not->toBeEmpty()
            ->and($reason)->toBeString()->not->toBeEmpty();
    }
});

it('never maps and exempts the same route', function () {
    $overlap = array_intersect(array_keys(CapabilityMap::mapped()), array_keys(CapabilityMap::exempt()));

    expect($overlap)->toBeEmpty();
});

it('resolves the tool for a mapped route', function () {
    expect(CapabilityMap::toolFor('invoices.index'))->toBe('invoices.list');
});

it('returns null for an unmapped route', function () {
    expect(CapabilityMap::toolFor('not-a-real-route'))->toBeNull();
});

it('reports exemption status and reason for an exempt route', function () {
    expect(CapabilityMap::isExempt('login'))->toBeTrue()
        ->and(CapabilityMap::exemptionReason('login'))->not->toBeNull();
});

it('reports a mapped route as not exempt', function () {
    expect(CapabilityMap::isExempt('invoices.index'))->toBeFalse()
        ->and(CapabilityMap::exemptionReason('invoices.index'))->toBeNull();
});
