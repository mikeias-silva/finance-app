<?php

namespace App\Http\Controllers;

use App\Models\RecurringIncome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Receitas que se repetem todo ciclo e caem junto do salário — gratificação,
 * ajuda de custo. O salário em si continua em /salaries, que só admite um valor
 * vigente por vez.
 */
class RecurringIncomeController extends Controller
{
    public function index(): View
    {
        $incomes = RecurringIncome::query()
            ->orderBy('description')
            ->get();

        $activeTotal = RecurringIncome::query()
            ->where('is_active', true)
            ->sum('amount');

        return view('recurring-incomes.index', compact('incomes', 'activeTotal'));
    }

    public function create(): View
    {
        return view('recurring-incomes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        RecurringIncome::create($validated);

        return redirect()
            ->route('recurring-incomes.index')
            ->with('success', 'Receita recorrente cadastrada com sucesso.');
    }

    public function show(RecurringIncome $recurringIncome): RedirectResponse
    {
        return redirect()->route('recurring-incomes.edit', $recurringIncome);
    }

    public function edit(RecurringIncome $recurringIncome): View
    {
        return view('recurring-incomes.edit', [
            'income' => $recurringIncome,
        ]);
    }

    public function update(Request $request, RecurringIncome $recurringIncome): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        $recurringIncome->update($validated);

        return redirect()
            ->route('recurring-incomes.index')
            ->with('success', 'Receita recorrente atualizada com sucesso.');
    }

    public function destroy(RecurringIncome $recurringIncome): RedirectResponse
    {
        $recurringIncome->delete();

        return redirect()
            ->route('recurring-incomes.index')
            ->with('success', 'Receita recorrente excluída com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
