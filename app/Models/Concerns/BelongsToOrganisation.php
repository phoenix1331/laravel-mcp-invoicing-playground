<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organisation;
use App\Models\Scopes\BelongsToOrganisationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new BelongsToOrganisationScope);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
