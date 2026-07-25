<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $cards = CreditCard::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $purchases = CreditCardPurchase::query()
            ->with('creditCard')
            ->when($request->filled('card'), function ($query) use ($request) {
                $query->where('credit_card_id', $request->integer('card'));
            })
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.index', compact('purchases', 'cards'));
    }

    public function create(): View
    {
        $cards = CreditCard::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('purchases.create', compact('cards'));
    }

    public function store(Request $request): RedirectResponse
    {
        CreditCardPurchase::create($this->validateData($request));

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Compra parcelada cadastrada com sucesso.');
    }

    public function show(CreditCardPurchase $purchase): RedirectResponse
    {
        return redirect()->route('purchases.edit', $purchase);
    }

    public function edit(CreditCardPurchase $purchase): View
    {
        $cards = CreditCard::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('purchases.edit', [
            'purchase' => $purchase,
            'cards' => $cards,
        ]);
    }

    public function update(Request $request, CreditCardPurchase $purchase): RedirectResponse
    {
        $purchase->update($this->validateData($request));

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Compra parcelada atualizada com sucesso.');
    }

    public function destroy(CreditCardPurchase $purchase): RedirectResponse
    {
        $purchase->delete();

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Compra parcelada excluída com sucesso.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'credit_card_id' => ['required', 'exists:credit_cards,id'],
            'description' => ['required', 'string'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['required', 'integer', 'min:1', 'max:60'],
            'purchased_at' => ['required', 'date'],
        ]);
    }
}
