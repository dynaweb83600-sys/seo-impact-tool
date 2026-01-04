<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportItemContentSuggestion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'secondary_keywords' => 'array',
        'outline_h2' => 'array',
        'questions_faq' => 'array',
        'internal_links_to' => 'array',
        'internal_links_from' => 'array',
        'proof' => 'array',
        'sources' => 'array',
        'raw_json' => 'array',
        'ai_generated_at' => 'datetime',
    ];

    // Relation existante
    public function item(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'report_item_id');
    }

    // ✅ Alias attendu par le reste du code
    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'report_item_id');
    }
}
