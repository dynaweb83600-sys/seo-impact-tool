<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class DomainCheckerStatusController extends Controller
{
    public function show(string $token)
    {
        $report = Report::where('access_token', $token)->latest()->first();

        if (!$report) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // pas besoin d'items ici si tu veux juste status/progress
        return response()->json([
            'report_id' => $report->id,
            'status' => $report->status,
            'requested_count' => (int)$report->requested_count,
            'processed_count' => (int)$report->processed_count,
        ]);
    }
}
