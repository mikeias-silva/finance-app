<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverview;
use Illuminate\Http\Request;

/**
 * Segunda versão da tela inicial, convivendo com a primeira até uma das duas ser
 * escolhida. A projeção é a mesma; muda a organização: indicadores olhando para
 * frente, a tabela de ciclos e o detalhe de cada mês na própria página.
 */
class Dashboard2Controller extends Controller
{
    public function index(Request $request, DashboardOverview $overview)
    {
        $months = max(1, min(24, (int) $request->input('months', 6)));

        return view('dashboard2.index', ['months' => $months] + $overview->build($months));
    }
}
