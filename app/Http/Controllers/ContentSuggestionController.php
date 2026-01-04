<?php
// app/Http/Controllers/ContentSuggestionController.php
namespace App\Http\Controllers;

use App\Models\ReportItemContentSuggestion;
use App\Services\Seo\GenerateSeoContentService;
use Illuminate\Http\Request;

class ContentSuggestionController extends Controller
{
    public function generate(int $id, GenerateSeoContentService $generator)
    {
		
		set_time_limit(180);
		ini_set('max_execution_time', 180);

		
        $s = ReportItemContentSuggestion::with('reportItem')->findOrFail($id);

        $html = $generator->generateFromSuggestion($s);

        $s->generated_html = $html;
        $s->generated_at = now();
        $s->save();

        return response()->json([
            'ok' => true,
            'id' => $s->id,
            'generated_at' => $s->generated_at,
            'generated_html' => $s->generated_html,
        ]);
    }
}
