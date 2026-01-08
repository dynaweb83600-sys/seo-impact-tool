<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'requested_count',
        'processed_count',
        'access_token',
        'completed_at',
    ];

    protected $casts = [
        'requested_count' => 'integer',
        'processed_count' => 'integer',
        'completed_at' => 'datetime',
    ];

	public function items()
	{
		return $this->hasMany(\App\Models\ReportItem::class);
	}

}
