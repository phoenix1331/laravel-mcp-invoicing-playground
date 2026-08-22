<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organisation_id', 'name', 'email', 'address'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToOrganisation, HasFactory;

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
