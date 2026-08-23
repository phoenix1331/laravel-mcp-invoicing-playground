<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InviteTeamMemberRequest;
use App\Http\Requests\SetTeamMemberRoleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Display the caller's organisation team list. Owner only, per the UI spec.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($user->can('create', User::class), 403);

        $members = User::query()
            ->where('organisation_id', $user->organisation_id)
            ->orderBy('name')
            ->get();

        return view('settings.team', ['members' => $members]);
    }

    /**
     * Invite a new member to the caller's organisation.
     */
    public function store(InviteTeamMemberRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $temporaryPassword = Str::password(20);

        User::create([
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
            'password' => $temporaryPassword,
            'organisation_id' => $user->organisation_id,
            'role' => $request->string('role')->value(),
        ]);

        return redirect()
            ->route('settings.team')
            ->with('temporaryPassword', $temporaryPassword);
    }

    /**
     * Change a team member's role.
     */
    public function update(SetTeamMemberRoleRequest $request, User $user): RedirectResponse
    {
        $user->update(['role' => $request->string('role')->value()]);

        return redirect()->route('settings.team');
    }
}
