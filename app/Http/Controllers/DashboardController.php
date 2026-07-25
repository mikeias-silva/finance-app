<?php

namespace App\Http\Controllers;

use App\Models\InvestmentBox;
use App\Services\BillingPeriod;
use App\Services\InvestmentService;
use App\Services\ProjectionService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ProjectionService $service, InvestmentService $investmentService)
    {
        $months = (int) $request->input('months', 6);
        $months = max(1, min(24, $months)); // entre 1 e 24

        // Determina o mês de faturamento atual com base nos dias úteis.
        // Se hoje já passou o penúltimo dia útil do mês corrente, estamos no próximo período.
        $today         = now()->addDay(1)->startOfDay();
        $calendarMonth = $today->copy()->startOfMonth();
        $currentPeriod = BillingPeriod::for($calendarMonth);
        $from          = $today->lte($currentPeriod['end'])
            ? $calendarMonth
            : $calendarMonth->copy()->addMonth();

        $to         = $from->copy()->addMonths($months - 1);
        $projection = $service->project($from, $to);
        $installmentsMonth = (string) $request->input('installments_month', $projection->keys()->first());

        if (!$projection->has($installmentsMonth)) {
            $installmentsMonth = (string) $projection->keys()->first();
        }

        $selectedInstallmentsMonth = $projection->get($installmentsMonth);

        $investmentBoxes = InvestmentBox::with('transactions')->where('is_active', true)->get();
        $investmentData  = $investmentBoxes->map(fn($box) => [
            'box'     => $box,
            'balance' => $investmentService->currentBalance($box),
            'yield'   => $investmentService->calculateYield($box),
            'total'   => $investmentService->totalWithYield($box),
        ]);
        $totalInvested   = $investmentData->sum('balance');
        $totalYield      = $investmentData->sum('yield');
        $totalWithYield  = $investmentData->sum('total');

        return view('dashboard.index', compact(
            'projection',
            'months',
            'installmentsMonth',
            'selectedInstallmentsMonth',
            'investmentData',
            'totalInvested',
            'totalYield',
            'totalWithYield'
        ));
    }
}
