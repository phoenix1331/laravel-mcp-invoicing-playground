<?php

declare(strict_types=1);

use App\Mcp\Servers\InvoicingServer;
use Laravel\Mcp\Server\Transport\FakeTransporter;

beforeEach(function () {
    $this->path = base_path('docs/data/capability-map.json');
});

afterEach(function () {
    if (file_exists($this->path)) {
        unlink($this->path);
        @rmdir(dirname($this->path));
        @rmdir(dirname(dirname($this->path)));
    }
});

it('writes a well-formed capability map json file', function () {
    $this->artisan('docs:capability-map')->assertSuccessful();

    expect($this->path)->toBeFile();

    $data = json_decode(file_get_contents($this->path), associative: true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKeys(['generatedAt', 'tools', 'resources', 'resourceTemplates', 'prompts', 'parityMatrix']);
});

it('matches the live server tool catalogue', function () {
    $this->artisan('docs:capability-map')->assertSuccessful();

    $data = json_decode(file_get_contents($this->path), associative: true);

    $server = new InvoicingServer(new FakeTransporter);
    $registered = $server->createContext()->tools()->map(fn ($tool) => $tool->name())->all();

    expect(collect($data['tools'])->pluck('name')->all())->toEqualCanonicalizing($registered);
});

it('reports a fully satisfied parity matrix', function () {
    $this->artisan('docs:capability-map')->assertSuccessful();

    $data = json_decode(file_get_contents($this->path), associative: true);

    $unsatisfied = collect($data['parityMatrix'])->reject(fn (array $row) => $row['satisfied']);

    expect($unsatisfied)->toBeEmpty();
});
