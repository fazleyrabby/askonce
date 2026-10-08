<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RequestItem extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['label', 'help_text', 'type', 'required', 'position'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }
}
