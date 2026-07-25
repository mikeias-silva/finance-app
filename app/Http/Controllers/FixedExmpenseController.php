<?php

namespace App\Http\Controllers;

use App\Models\FixedExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixedExmpenseController extends Controller
{
    public function index(): View
    {
        $expenses = FixedExpense::query()
            ->orderBy('description')
            ->get();

        $activeTotal = FixedExpense::query()
            ->where('is_active', true)
            ->sum('amount');

        return view('fixed-expenses.index', compact('expenses', 'activeTotal'));
    }

    public function create(): View
    {
        return view('fixed-expenses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        FixedExpense::create($validated);

        return redirect()
            ->route('fixed-expenses.index')
            ->with('success', 'Gasto fixo cadastrado com sucesso.');
    }

    public function show(FixedExpense $fixedExpense): RedirectResponse
    {
        return redirect()->route('fixed-expenses.edit', $fixedExpense);
    }

    public function edit(FixedExpense $fixedExpense): View
    {
        return view('fixed-expenses.edit', [
            'expense' => $fixedExpense,
        ]);
    }

    public function update(Request $request, FixedExpense $fixedExpense): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        $fixedExpense->update($validated);

        return redirect()
            ->route('fixed-expenses.index')
            ->with('success', 'Gasto fixo atualizado com sucesso.');
    }

    public function destroy(FixedExpense $fixedExpense): RedirectResponse
    {
        $fixedExpense->delete();

        return redirect()
            ->route('fixed-expenses.index')
            ->with('success', 'Gasto fixo excluído com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
