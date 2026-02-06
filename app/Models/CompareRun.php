<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompareRun extends Model
{
    protected $fillable = [
        'report_item_id',
        'client_url',
        'competitor_url',
        'location_code',
        'language_code',
        'status',
        'error_message',
        'serp_topics_json',
        'serp_intent',
        'similarity_score',
        'serp_fit_score',
        'diff_json',
        'actions_json',
        'client_snapshot_json',
        'competitor_snapshot_json',
    ];

    protected $casts = [
        'serp_topics_json' => 'array',
        'diff_json' => 'array',
        'actions_json' => 'array',
        'client_snapshot_json' => 'array',
        'competitor_snapshot_json' => 'array',
        'similarity_score' => 'decimal:2',
        'serp_fit_score' => 'integer',
        'location_code' => 'integer',
    ];

    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class);
    }
}
