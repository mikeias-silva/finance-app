<?php

namespace App\Http\Controllers;

use App\Services\ProjectionService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ProjectionService $service)
    {
        $months = (int) $request->input('months', 6);
        $months = max(1, min(24, $months)); // entre 1 e 24

        $from       = now()->startOfMonth();
        $to         = $from->copy()->addMonths($months - 1);
        $projection = $service->project($from, $to);
        $installmentsMonth = (string) $request->input('installments_month', $projection->keys()->first());

        if (!$projection->has($installmentsMonth)) {
            $installmentsMonth = (string) $projection->keys()->first();
        }

        $selectedInstallmentsMonth = $projection->get($installmentsMonth);

        return view('dashboard.index', compact(
            'projection',
            'months',
            'installmentsMonth',
            'selectedInstallmentsMonth'
        ));
    }
}
