<?php

namespace App\Http\Controllers;

use App\Models\VariableExpense;
use App\Services\BillingPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VariableExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->input('month', now()->format('Y-m'));

        $period = BillingPeriod::for(Carbon::parse($month . '-01'));
        $start  = $period['start'];
        $end    = $period['end'];

        $query = VariableExpense::query()
            ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()]);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $expenses = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $monthTotal = VariableExpense::query()
            ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->input('category')))
            ->sum('amount');

        $categories = VariableExpense::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('variable-expenses.index', compact('expenses', 'monthTotal', 'categories', 'month', 'start', 'end'));
    }

    public function create(): View
    {
        return view('variable-expenses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        VariableExpense::create($this->validateData($request));

        return redirect()->route('variable-expenses.index')->with('success', 'Movimentação cadastrada com sucesso.');
    }

    public function show(VariableExpense $variableExpense): RedirectResponse
    {
        return redirect()->route('variable-expenses.edit', $variableExpense);
    }

    public function edit(VariableExpense $variableExpense): View
    {
        return view('variable-expenses.edit', compact('variableExpense'));
    }

    public function update(Request $request, VariableExpense $variableExpense): RedirectResponse
    {
        $variableExpense->update($this->validateData($request));

        return redirect()->route('variable-expenses.index')->with('success', 'Movimentação atualizada com sucesso.');
    }

    public function destroy(VariableExpense $variableExpense): RedirectResponse
    {
        $variableExpense->delete();

        return redirect()->route('variable-expenses.index')->with('success', 'Movimentação excluída com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category'    => ['nullable', 'string', 'max:100'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'paid_at'     => ['required', 'date'],
        ]);
    }
}
