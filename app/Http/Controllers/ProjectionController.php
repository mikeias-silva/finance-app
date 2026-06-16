<?php

namespace App\Http\Controllers;

use App\Services\ProjectionService;
use Illuminate\Support\Facades\Request;

class ProjectionController extends Controller
{
    public function index(Request $request, ProjectionService $projectionService)
    {
        $months = $request->input('months', 6);
        $from = now()->startOfMonth();
        $to = $from->copy()->addMonths($months -1);

        $projection = $projectionService->project($from, $to);

        return view ('finance.projection', compact('months', 'projection'));
    }
}
