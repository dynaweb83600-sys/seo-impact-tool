<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimValidation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'replacement_titles' => 'array',
        'raw_validator_json' => 'array',
    ];
}
