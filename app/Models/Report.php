<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'requested_count',
        'access_token',
        'completed_at',
    ];

    public function items()
    {
        return $this->hasMany(ReportItem::class);
    }
}
