<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportsPageController extends Controller
{
    public function index(Request $request)
    {
        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('reports.index', compact('reports'));
    }

    public function show(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id, 403);

        $report->load('items');

        return view('reports.show', compact('report'));
    }
}
