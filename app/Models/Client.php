<?php

namespace App\Models;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_name', 'email'];

    protected static function booted(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            $query->where('clients.organization_id', app(CurrentOrganization::class)->get()->id);
        });
        static::creating(function (Client $client) {
            $client->organization_id = app(CurrentOrganization::class)->get()->id;
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
