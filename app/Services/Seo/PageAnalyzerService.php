<?php

namespace App\Services\Seo;

use App\Models\Report;
use App\Models\ReportPage;

class PageAnalyzerService
{
    public function analyze(Report $report): void
    {
        // 1️⃣ Homepage (toujours)
        $this->storePage($report, $report->domain, 'home');

        // 2️⃣ Pages issues des mots-clés (via DataForSEO / SeedKeyword)
        if (method_exists($report, 'keywords')) {
            foreach ($report->keywords->take(10) as $keyword) {
                if (!empty($keyword->url)) {
                    $this->storePage($report, $keyword->url, 'content');
                }
            }
        }

        // 3️⃣ Pages avec backlinks (Moz / DataForSEO)
        if (method_exists($report, 'backlinks')) {
            foreach ($report->backlinks->take(10) as $link) {
                $this->storePage($report, $link->target_url, 'content');
            }
        }
    }

    protected function storePage(Report $report, string $url, string $type): void
    {
        ReportPage::firstOrCreate(
            [
                'report_id' => $report->id,
                'url' => $url,
            ],
            [
                'page_type' => $type,
            ]
        );
    }
	
	public function populateFromRankedKeywords(\App\Models\ReportItem $ri, array $rkBody): void
	{
		// TODO: implémenter plus tard
		return;
	}

}
