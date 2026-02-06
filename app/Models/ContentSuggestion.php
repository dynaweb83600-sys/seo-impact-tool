<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSuggestion extends Model
{
    protected $fillable = [
        'report_item_id',
        'target_url',
        'domain',
        'content_type',
        'format_variant',
        'angle_note',
        'intent',
        'serp_intent',
        'priority_score',
        'priority_label',
        'primary_keyword',
        'secondary_keywords_json',
        'entities_json',
        'suggested_title',
        'suggested_slug',
        'outline_h2',
        'outline_h3',
        'must_have_json',
        'material_stats_json',
        'similarity_group',
        'similarity_hash',
        'similarity_score',
        'dedup_decision',
        'generation_status',
        'generation_model',
        'generation_prompt_version',
        'generated_html',
        'generated_at',
    ];

    protected $casts = [
        'secondary_keywords_json' => 'array',
        'entities_json' => 'array',
        'outline_h2' => 'array',
        'outline_h3' => 'array',
        'must_have_json' => 'array',
        'material_stats_json' => 'array',
        'priority_score' => 'integer',
        'similarity_score' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class);
    }

    public static function calculateSimilarityHash(string $title, array $keywords = []): string
    {
        $normalized = mb_strtolower(trim($title));
        $normalized = preg_replace('/[^a-z0-9\s]/u', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        $kwString = implode(' ', array_map('mb_strtolower', $keywords));
        $combined = $normalized . ' ' . $kwString;
        
        return substr(md5($combined), 0, 16);
    }
}
