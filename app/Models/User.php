<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'organisation_id', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Sanctum's trait covers both the manual bearer tokens issued from
     * /settings/tokens and the `api` guard's OAuth tokens (driver: passport,
     * used by MCP clients such as Claude Desktop) - Passport's TokenGuard
     * only ever calls withAccessToken() on this model, which Sanctum's
     * version already handles generically: it stores whatever token object
     * is passed, and tokenCan() then calls ->can() on it, which both
     * Sanctum's PersonalAccessToken and Passport's AccessToken/
     * TransientToken implement. No separate Passport trait is needed.
     *
     * @use HasFactory<UserFactory>
     */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
