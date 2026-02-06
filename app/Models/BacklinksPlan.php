<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinksPlan extends Model
{
    protected $fillable = [
        'report_item_id',
        'domain',
        'status',
        'error_message',
        'rd_current',
        'rd_target_3m',
        'rd_gap',
        'monthly_plan_json',
        'anchor_mix_json',
        'targets_json',
        'link_mix_json',
        'footprints_json',
        'generated_html',
    ];

    protected $casts = [
        'monthly_plan_json' => 'array',
        'anchor_mix_json' => 'array',
        'targets_json' => 'array',
        'link_mix_json' => 'array',
        'footprints_json' => 'array',
        'rd_current' => 'integer',
        'rd_target_3m' => 'integer',
        'rd_gap' => 'integer',
    ];

    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class);
    }
}
