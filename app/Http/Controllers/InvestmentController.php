<?php

namespace App\Http\Controllers;

use App\Models\CdiRate;
use App\Models\InvestmentBox;
use App\Models\InvestmentTransaction;
use App\Services\BillingPeriod;
use App\Services\InvestmentService;
use App\Services\ProjectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestmentController extends Controller
{
    public function index(InvestmentService $service, ProjectionService $projectionService): View
    {
        $boxes = InvestmentBox::with('transactions')->where('is_active', true)->get();
        $cdiRate = CdiRate::orderByDesc('effective_from')->first();

        $today = now()->startOfDay();
        $calendarMonth = $today->copy()->startOfMonth();
        $period = BillingPeriod::for($calendarMonth);
        $from = $today->lte($period['end']) ? $calendarMonth : $calendarMonth->copy()->addMonth();
        $projection = $projectionService->project($from, $from->copy());
        $currentProjection = $projection->first();
        $projectedBalance = $currentProjection ? max(0, $currentProjection['balance']) : 0;

        $boxData = $boxes->map(fn (InvestmentBox $box) => [
            'box' => $box,
            'balance' => $service->currentBalance($box),
            'yield' => $service->calculateYield($box),
            'total' => $service->totalWithYield($box),
            'available' => $service->availableCapacity($box),
        ]);

        $totalInvested = $boxData->sum('balance');
        $totalYield = $boxData->sum('yield');
        $totalWithYield = $boxData->sum('total');

        return view('investments.index', compact(
            'boxData',
            'cdiRate',
            'projectedBalance',
            'totalInvested',
            'totalYield',
            'totalWithYield',
            'boxes'
        ));
    }

    public function deposit(Request $request, InvestmentBox $box, InvestmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transacted_at' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $currentBalance = $service->currentBalance($box);

        if ($box->max_amount && ($currentBalance + $data['amount']) > $box->max_amount) {
            return back()->with('error', 'Valor excede o teto da caixinha. Disponível: R$ '.number_format($box->max_amount - $currentBalance, 2, ',', '.'));
        }

        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'deposit',
            'amount' => $data['amount'],
            'transacted_at' => $data['transacted_at'],
            'notes' => $data['notes'],
        ]);

        return back()->with('success', 'Depósito de R$ '.number_format($data['amount'], 2, ',', '.')." realizado em {$box->name}.");
    }

    public function withdrawal(Request $request, InvestmentBox $box, InvestmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transacted_at' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $currentBalance = $service->currentBalance($box);

        if ($data['amount'] > $currentBalance) {
            return back()->with('error', 'Saldo insuficiente. Saldo atual: R$ '.number_format($currentBalance, 2, ',', '.'));
        }

        InvestmentTransaction::create([
            'investment_box_id' => $box->id,
            'type' => 'withdrawal',
            'amount' => $data['amount'],
            'transacted_at' => $data['transacted_at'],
            'notes' => $data['notes'],
        ]);

        return back()->with('success', 'Saque de R$ '.number_format($data['amount'], 2, ',', '.')." realizado de {$box->name}.");
    }

    public function storeCdi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rate' => 'required|numeric|min:0|max:100',
            'effective_from' => 'required|date',
        ]);

        CdiRate::create($data);

        return back()->with('success', 'Taxa CDI de '.number_format($data['rate'], 2, ',', '.').'% a.a. cadastrada.');
    }

    public function storeBox(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'rate_percentage' => 'required|numeric|min:1|max:500',
            'max_amount' => 'nullable|numeric|min:0',
        ]);

        InvestmentBox::create($data + ['is_active' => true]);

        return back()->with('success', "Caixinha {$data['name']} criada com sucesso.");
    }
}
