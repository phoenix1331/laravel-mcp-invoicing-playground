<?php

declare(strict_types=1);

use App\Models\Organisation;
use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

it('advertises oauth discovery metadata pointing at passport', function () {
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk()->assertJson([
        'authorization_endpoint' => url('/oauth/authorize'),
        'token_endpoint' => url('/oauth/token'),
        'registration_endpoint' => url('/oauth/register'),
        'scopes_supported' => ['mcp:use'],
    ]);
});

it('advertises the protected resource pointing at the authorization server', function () {
    $response = $this->getJson('/.well-known/oauth-protected-resource');

    $response->assertOk()->assertJson([
        'resource' => url('/'),
        'authorization_servers' => [url('/')],
        'scopes_supported' => ['mcp:use'],
    ]);
});

it('dynamically registers a new oauth client', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test MCP Client',
        'redirect_uris' => ['http://localhost:9999/callback'],
    ]);

    $response->assertCreated()->assertJson([
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'redirect_uris' => ['http://localhost:9999/callback'],
        'scope' => 'mcp:use',
    ]);

    expect($response->json('client_id'))->not->toBeNull();
});

it('rejects dynamic client registration with no redirect uri', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Test MCP Client'])
        ->assertStatus(400)
        ->assertJson(['error' => 'invalid_redirect_uri']);
});

it('completes the full authorization code flow and calls the mcp endpoint with the issued token', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test MCP Client',
        redirectUris: ['http://localhost:9999/callback'],
        confidential: false,
    );

    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $authorizeResponse = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'http://localhost:9999/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]));

    $authorizeResponse->assertOk();

    $approveResponse = $this->actingAs($user)->post('/oauth/authorize', [
        'auth_token' => session('authToken'),
    ]);

    $approveResponse->assertRedirect();
    $redirectUri = $approveResponse->headers->get('Location');

    parse_str((string) parse_url((string) $redirectUri, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('code');

    $tokenResponse = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => 'http://localhost:9999/callback',
        'code' => $query['code'],
        'code_verifier' => $codeVerifier,
    ]);

    $tokenResponse->assertOk();
    $accessToken = $tokenResponse->json('access_token');
    expect($accessToken)->not->toBeNull();

    $mcpResponse = $this->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson('/mcp/invoicing', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'organisation.get', 'arguments' => []],
        ]);

    $mcpResponse->assertOk();
    expect($mcpResponse->json('result.isError'))->not->toBe(true);
});

it('denies an mcp call with no token', function () {
    $this->postJson('/mcp/invoicing', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'organisation.get', 'arguments' => []],
    ])->assertUnauthorized();
});

it('accepts a passport-issued token via Passport::actingAs on the mcp endpoint', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['organisation_id' => $organisation->id]);

    Passport::actingAs($user, ['mcp:use']);

    $response = $this->postJson('/mcp/invoicing', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'organisation.get', 'arguments' => []],
    ]);

    $response->assertOk();
    expect($response->json('result.isError'))->not->toBe(true);
});
