<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportPage extends Model
{
    protected $fillable = [
        'report_id',
        'url',
        'page_type',
        'impressions',
        'avg_position',
        'word_count',
        'backlinks_count',
        'internal_links_in',
        'score',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}