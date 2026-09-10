<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryController extends Controller
{
    public function index(): View
    {
        $salaries = Salary::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $current = $salaries->first();

        return view('salaries.index', compact('salaries', 'current'));
    }

    public function create(): View
    {
        return view('salaries.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Salary::create($this->validateData($request));

        return redirect()
            ->route('salaries.index')
            ->with('success', 'Salário cadastrado com sucesso.');
    }

    public function show(Salary $salary): RedirectResponse
    {
        return redirect()->route('salaries.edit', $salary);
    }

    public function edit(Salary $salary): View
    {
        return view('salaries.edit', compact('salary'));
    }

    public function update(Request $request, Salary $salary): RedirectResponse
    {
        $salary->update($this->validateData($request));

        return redirect()
            ->route('salaries.index')
            ->with('success', 'Salário atualizado com sucesso.');
    }

    public function destroy(Salary $salary): RedirectResponse
    {
        $salary->delete();

        return redirect()
            ->route('salaries.index')
            ->with('success', 'Salário excluído com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
        ]);
    }
}
