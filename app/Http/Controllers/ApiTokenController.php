<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * Display the caller's API tokens.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $tokens = $user->tokens()->latest()->get();

        return view('settings.tokens', ['tokens' => $tokens]);
    }

    /**
     * Create a new API token for the caller.
     */
    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken($request->string('name')->value());

        return redirect()
            ->route('settings.tokens')
            ->with('plainTextToken', $token->plainTextToken);
    }

    /**
     * Revoke an API token belonging to the caller.
     */
    public function destroy(PersonalAccessToken $token): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($token->tokenable_id === $user->id && $token->tokenable_type === User::class, 404);

        $token->delete();

        return redirect()->route('settings.tokens');
    }
}
