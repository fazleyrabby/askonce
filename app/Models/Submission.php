<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['request_item_id', 'value'];

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }
}
