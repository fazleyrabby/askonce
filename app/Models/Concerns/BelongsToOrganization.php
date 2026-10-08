<?php

namespace App\Models\Concerns;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('organization_id'), app(CurrentOrganization::class)->get()->id);
        });
        static::creating(function (Model $model): void {
            $model->organization_id = app(CurrentOrganization::class)->get()->id;
        });
    }
}
