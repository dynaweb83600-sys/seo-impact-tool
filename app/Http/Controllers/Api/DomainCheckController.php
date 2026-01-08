<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchDomainMetricsJob;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\FetchGscForReportJob;


class DomainCheckController extends Controller
{
    public function check(Request $request)
	{
		$request->validate([
			'domains' => ['required', 'string'],
		]);

		$domains = collect(preg_split('/[\r\n,]+/', $request->input('domains')))
			->map(fn($d) => strtolower(trim($d)))
			->filter()
			->unique()
			->values()
			->toArray();

		if (count($domains) === 0) {
			return response()->json(['message' => 'Aucun domaine fourni'], 422);
		}

		$accessToken = (string) Str::uuid();

		$report = Report::create([
			'user_id' => auth()->id(),
			'status' => 'pending',
			'requested_count' => count($domains),
			'access_token' => $accessToken,
		]);

		$user = auth()->user();

		// Juste un flag: connecté ou non
		$hasGsc = !empty($user->gsc_connected) && !empty($user->gsc_token_json);

		$report->update([
			'gsc_connected' => $hasGsc,
			'gsc_property'  => $hasGsc ? 'multi' : null,
		]);

		$chain = [];

		if ($hasGsc) {
			$chain[] = new FetchGscForReportJob($report->id, (int) auth()->id());
		}

		FetchDomainMetricsJob::withChain($chain)
			->dispatch($report->id, $domains);

		return response()->json([
			'report_id' => $report->id,
			'access_token' => $accessToken,
		]);
	}

}
