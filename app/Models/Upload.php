<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['disk', 'path', 'original_name', 'size', 'mime_type'];
}
