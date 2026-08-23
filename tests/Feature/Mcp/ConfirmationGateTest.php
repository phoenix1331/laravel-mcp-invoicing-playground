<?php

declare(strict_types=1);

use App\Mcp\Support\ConfirmationGate;

it('returns null when confirmed', function () {
    $gate = app(ConfirmationGate::class);

    $result = $gate->requireConfirmation(true, 'delete this thing', 'It cannot be undone.');

    expect($result)->toBeNull();
});

it('returns a structured confirmation prompt when not confirmed', function () {
    $gate = app(ConfirmationGate::class);

    $result = $gate->requireConfirmation(false, 'delete this thing', 'It cannot be undone.');

    expect($result)->not->toBeNull();
    expect($result->getStructuredContent())->toBe([
        'requires_confirmation' => true,
        'action' => 'delete this thing',
        'consequence' => 'It cannot be undone.',
    ]);
});

it('mentions the action and consequence in the text summary', function () {
    $gate = app(ConfirmationGate::class);

    $result = $gate->requireConfirmation(false, 'void this invoice', 'This is permanent.');

    $text = $result->responses()->first()->content()->__toString();

    expect($text)->toContain('void this invoice')
        ->and($text)->toContain('This is permanent.')
        ->and($text)->toContain('confirm: true');
});
