<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportItem extends Model
{
    protected $guarded = [];
	
	
	protected $casts = [
		'content_suggestions' => 'array',
		'raw_json' => 'array',
		'top_anchors' => 'array',
		'competitors' => 'array',
		'domain_created_at' => 'date',
		'domain_age_years' => 'float',
		'traffic_etv' => 'float',
		'ai_tooltips' => 'array',
		'ai_diagnosis' => 'array',
		'ai_details' => 'array',
		'ai_generated_at' => 'datetime',
	    'seed_keywords' => 'array',
		'topic_profile' => 'array',
	];


    // CPC moyen en €
    public function avgCpcEur(): float
    {
        // Mets ce que tu veux dans .env : SEO_AVG_CPC_EUR=1.2
        return (float) (config('seo.avg_cpc_eur') ?? env('SEO_AVG_CPC_EUR', 1.2));
    }

    public function dofollowRatio(): ?float
    {
        $total = (int) ($this->inbound_links ?? 0);
        $dof   = (int) ($this->dofollow_links ?? 0);

        if ($total <= 0) return null;

        return round(($dof / $total) * 100, 1);
    }

    public function nofollowRatio(): ?float
    {
        $total = (int) ($this->inbound_links ?? 0);
        $nof   = (int) ($this->nofollow_links ?? 0);

        if ($total <= 0) return null;

        return round(($nof / $total) * 100, 1);
    }

    public function backlinksPerRefDomain(): ?float
    {
        $rd = (int) ($this->linking_domains ?? 0);
        $bl = (int) ($this->inbound_links ?? 0);

        if ($rd <= 0) return null;

        return round($bl / $rd, 2);
    }

    public function netBacklinks30d(): ?int
    {
        if ($this->new_backlinks_30d === null || $this->lost_backlinks_30d === null) {
            return null;
        }
        return (int) $this->new_backlinks_30d - (int) $this->lost_backlinks_30d;
    }

    public function estimatedSeoValueEur(): ?float
    {
        // Ici on utilise ETV (valeur de trafic estimée) * CPC moyen
        if ($this->traffic_etv === null) return null;
        return round(((float) $this->traffic_etv) * $this->avgCpcEur(), 2);
    }

    public function growthTrend(): string
    {
        // Simple & utile sans historique :
        // - si net backlinks < 0 => "-"
        // - si net backlinks > 0 => "+"
        // - sinon "="
        /*$net = $this->netBacklinks30d();

        if ($net === null) return '='; // pas assez de data
        if ($net < 0) return '-';
        if ($net > 0) return '+';
        return '=';*/
		
		$net = $this->netBacklinks30d();

		if ($net === null) return '→';
		if ($net > 0) return '↑';
		if ($net < 0) return '↓';
		return '→';
		
		
    }
	
	public function domainAgeLabel(): ?string
	{
		if ($this->domain_age_years === null) return null;

		$age = (float) $this->domain_age_years;

		if ($age < 1) return 'Fragile';
		if ($age < 5) return 'Normal';
		return 'Solide';
	}

	public function domainAgeYearsRounded(): ?float
	{
		if ($this->domain_age_years === null) {
			return null;
		}

		return round((float) $this->domain_age_years, 1);
	}

	public function formatEuro(?float $value): ?string
	{
		if ($value === null) return null;
		return number_format($value, 2, ',', ' ') . ' €';
	}
	
	
	public function seoDiagnosis(): array
	{
		$diagnosis = [];

		if (($this->organic_keywords ?? 0) < 50) {
			$diagnosis[] = [
				'type' => 'content',
				'message' => 'Manque de contenu SEO',
				'action' => 'Créer 3 pages SEO + 8 articles'
			];
		}

		if (($this->linking_domains ?? 0) < 50) {
			$diagnosis[] = [
				'type' => 'backlinks',
				'message' => 'Profil de backlinks faible',
				'action' => 'Obtenir 10–20 backlinks thématiques'
			];
		}

		if ($this->backlinksPerRefDomain() !== null && $this->backlinksPerRefDomain() > 10) {
			$diagnosis[] = [
				'type' => 'risk',
				'message' => 'Profil de liens agressif',
				'action' => 'Diversifier les domaines référents'
			];
		}

		return $diagnosis;
	}
	
	public function contentSuggestions(): HasMany
	{
		return $this->hasMany(ReportItemContentSuggestion::class, 'report_item_id');
	}

	public function report()
	{
		return $this->belongsTo(\App\Models\Report::class);
	}

	  // ✅ alias attendu par le controller / query
    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'report_item_id');
    }

}
