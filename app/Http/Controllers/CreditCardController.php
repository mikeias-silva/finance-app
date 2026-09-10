<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardController extends Controller
{
    public function index(): View
    {
        $cards = CreditCard::withCount('purchases')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('credit-cards.index', compact('cards'));
    }

    public function create(): View
    {
        return view('credit-cards.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        CreditCard::create($validated);

        return redirect()
            ->route('credit-cards.index')
            ->with('success', 'Cartão cadastrado com sucesso.');
    }

    public function show(CreditCard $creditCard): RedirectResponse
    {
        return redirect()->route('credit-cards.edit', $creditCard);
    }

    public function edit(CreditCard $creditCard): View
    {
        return view('credit-cards.edit', [
            'card' => $creditCard,
        ]);
    }

    public function update(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['is_active'] = $request->boolean('is_active');

        $creditCard->update($validated);

        return redirect()
            ->route('credit-cards.index')
            ->with('success', 'Cartão atualizado com sucesso.');
    }

    public function destroy(CreditCard $creditCard): RedirectResponse
    {
        try {
            $creditCard->delete();
        } catch (QueryException $e) {
            return redirect()
                ->route('credit-cards.index')
                ->with('error', 'Não é possível excluir: existem compras vinculadas a este cartão.');
        }

        return redirect()
            ->route('credit-cards.index')
            ->with('success', 'Cartão excluído com sucesso.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'old_closing_day' => ['nullable', 'integer', 'between:1,31'],
            'old_due_day' => ['nullable', 'integer', 'between:1,31'],
            'closing_changed_at' => ['nullable', 'date'],
            'billing_skip_months' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // "2026-07, 2026-08" -> ["2026-07", "2026-08"]
        $validated['billing_skip_months'] = collect(explode(',', (string) $request->input('billing_skip_months')))
            ->map(fn ($m) => trim($m))
            ->filter()
            ->values()
            ->all();

        if (empty($validated['billing_skip_months'])) {
            $validated['billing_skip_months'] = null;
        }

        return $validated;
    }
}
