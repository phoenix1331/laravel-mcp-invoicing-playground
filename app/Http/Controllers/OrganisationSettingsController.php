<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganisationRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class OrganisationSettingsController extends Controller
{
    /**
     * Display the caller's organisation settings.
     */
    public function edit(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('settings.organisation', ['organisation' => $user->organisation()->firstOrFail()]);
    }

    /**
     * Update the caller's organisation settings.
     */
    public function update(UpdateOrganisationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $organisation = $user->organisation()->firstOrFail();

        $data = $request->safe()->only(['name', 'address', 'vat_number']);

        if ($request->hasFile('logo')) {
            if ($organisation->logo_path) {
                Storage::disk('public')->delete($organisation->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store("organisations/{$organisation->id}", 'public');
        }

        $organisation->update($data);

        return redirect()->route('settings.organisation');
    }
}
