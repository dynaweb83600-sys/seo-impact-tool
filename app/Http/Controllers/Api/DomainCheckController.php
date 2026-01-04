<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchDomainMetricsJob;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainCheckController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'domains' => ['required', 'string'],
        ]);

        // split "1 par ligne" ou "virgule"
        //$domains = preg_split('/[\s,]+/', trim($request->input('domains')));
        $domains = collect(preg_split('/[\r\n,]+/', $request->input('domains')))
    ->map(fn($d) => strtolower(trim($d)))
    ->filter()
    ->unique()
    ->values()
    ->toArray();

		$domains = array_values(array_filter(array_map('strtolower', $domains)));

        if (count($domains) === 0) {
            return response()->json(['message' => 'Aucun domaine fourni'], 422);
        }

        $token = (string) Str::uuid();

        $report = Report::create([
            'user_id' => auth()->id(),      // ok car tu es loggé
            'status' => 'pending',
            'requested_count' => count($domains),
            'access_token' => $token,
        ]);

        // Si QUEUE_CONNECTION=sync => s'exécute immédiatement
        FetchDomainMetricsJob::dispatch($report->id, $domains);

        return response()->json([
            'report_id' => $report->id,
            'access_token' => $token,
        ]);
    }
}
