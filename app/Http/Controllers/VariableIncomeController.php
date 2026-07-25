<?php

namespace App\Http\Controllers;

use App\Models\VariableIncome;
use App\Services\BillingPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VariableIncomeController extends Controller
{
    public function index(): View
    {
        $period = BillingPeriod::for(now());

        $incomes = VariableIncome::query()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(20);

        $currentMonthTotal = VariableIncome::query()
            ->whereBetween('received_at', [
                $period['start']->toDateString(),
                $period['end']->toDateString(),
            ])
            ->sum('amount');

        $totalAmount = VariableIncome::query()->sum('amount');

        return view('variable-incomes.index', compact('incomes', 'currentMonthTotal', 'totalAmount'));
    }

    public function create(): View
    {
        return view('variable-incomes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        VariableIncome::create($this->validateData($request));

        return redirect()
            ->route('variable-incomes.index')
            ->with('success', 'Rendimento variável cadastrado com sucesso.');
    }

    public function show(VariableIncome $variableIncome): RedirectResponse
    {
        return redirect()->route('variable-incomes.edit', $variableIncome);
    }

    public function edit(VariableIncome $variableIncome): View
    {
        return view('variable-incomes.edit', compact('variableIncome'));
    }

    public function update(Request $request, VariableIncome $variableIncome): RedirectResponse
    {
        $variableIncome->update($this->validateData($request));

        return redirect()
            ->route('variable-incomes.index')
            ->with('success', 'Rendimento variável atualizado com sucesso.');
    }

    public function destroy(VariableIncome $variableIncome): RedirectResponse
    {
        $variableIncome->delete();

        return redirect()
            ->route('variable-incomes.index')
            ->with('success', 'Rendimento variável excluído com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_at' => ['required', 'date'],
        ]);
    }
}
