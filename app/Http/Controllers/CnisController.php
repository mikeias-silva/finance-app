<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Smalot\PdfParser\Parser;
use Carbon\Carbon;

class CnisController extends Controller
{
    public function index()
    {
        return view('cnis');
    }

    public function importar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|mimes:pdf|max:5120',
        ]);

        $path = $request->file('arquivo')->store('cnis');
        $fullPath = Storage::path($path);

        $parser = new Parser();
        $pdf = $parser->parseFile($fullPath);
        $texto = $pdf->getText();

        Storage::delete($path);

        // Se o texto estiver vazio, o PDF é uma imagem escaneada ou está corrompido
        if (empty(trim($texto))) {
            return back()->withErrors(['arquivo' => 'Não foi possível extrair texto deste PDF.']);
        }

        $remuneracoes = [];
        $somaTotal = 0;
        $quantidade = 0;

        $dataCortePlanoReal = Carbon::create(1994, 7, 1)->startOfMonth();
        $historicoSalarial = $this->obterHistoricoSalarioMinimo();

        // Quebra o documento de forma mais segura. Se não achar "Seq.", tenta por "Origem do Vínculo"
        $blocosVinculos = preg_split('/Seq\.(?:\s+|\n+)?\d+/i', $texto);
        if (count($blocosVinculos) <= 1) {
            $blocosVinculos = preg_split('/Origem do Vínculo/i', $texto);
        }

        // Só remove o cabeçalho inicial se o texto foi realmente particionado
        if (count($blocosVinculos) > 1) {
            array_shift($blocosVinculos);
        }

        foreach ($blocosVinculos as $bloco) {
            if (strlen(trim($bloco)) < 30) continue;

            [$cnpj, $empresa, $inicio, $fim] = $this->extrairDadosCabecalho($bloco);

            // REGEX NOVA: Captura Mês (1), Ano (2) e Valor com ou sem ponto (3).
            // Tolera espaços nulos, tabs e quebras de linha entre a data e o valor.
            $padraoRemuneracao = '/(0[1-9]|1[0-2])\/(\d{4})[\s\n\t]*([\d\.]+,\d{2})/';
            preg_match_all($padraoRemuneracao, $bloco, $matches);

            // Acesso pelo índice 3 agora, que é o grupo de captura do valor monetário
            if (!empty($matches[3])) {
                foreach ($matches[3] as $index => $valorBruto) {
                    $mes = $matches[1][$index];
                    $ano = $matches[2][$index];
                    $competenciaString = "$mes/$ano";

                    $dataCompetencia = Carbon::create((int)$ano, (int)$mes, 1)->startOfMonth();

                    if ($dataCompetencia->lessThan($dataCortePlanoReal)) {
                        continue;
                    }

                    $valorLimpo = str_replace(['.', ','], ['', '.'], $valorBruto);
                    $valorFloat = (float) $valorLimpo;

                    $salarioMinimoVigente = $this->encontrarMinimoDaEpoca($dataCompetencia, $historicoSalarial);

                    if ($valorFloat < $salarioMinimoVigente) {
                        continue;
                    }

                    $remuneracoes[] = [
                        'empresa' => $empresa,
                        'cnpj' => $cnpj,
                        'inicio' => $inicio,
                        'fim' => $fim,
                        'competencia' => $competenciaString,
                        'valor' => $valorFloat,
                        'minimo_epoca' => $salarioMinimoVigente
                    ];

                    $somaTotal += $valorFloat;
                    $quantidade++;
                }
            }
        }

        $media = $quantidade > 0 ? $somaTotal / $quantidade : 0;

        return view('cnis', [
            'remuneracoes' => $remuneracoes,
            'total_meses' => $quantidade,
            'soma_total' => $somaTotal,
            'media' => $media,
            'sucesso' => true
        ]);
    }

    private function extrairDadosCabecalho(string $bloco): array
    {
        $cnpj = 'Não identificado';
        if (preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\b\d{10,14}\b/', $bloco, $cnpjMatches)) {
            $cnpj = $cnpjMatches[0];
        }

        // Remove quebras de linha para facilitar o Regex do nome da empresa
        $blocoLimpo = str_replace(["\n", "\r", "\t"], " ", $bloco);
        $empresa = 'Empresa não identificada';

        if ($cnpj !== 'Não identificado') {
            $cnpjEscapado = preg_quote($cnpj, '/');
            $padraoEmpresa = '/' . $cnpjEscapado . '(.*?)(?:Empregado|Contribuinte|Trabalhador|Data Início|Matrícula|Tipo Filiado|Público|Não Cooperado)/i';

            if (preg_match($padraoEmpresa, $blocoLimpo, $empMatches)) {
                $empresa = $empMatches[1];
            }
        } else {
            if (preg_match('/Origem do Vínculo(.*?)(?:Matrícula|Tipo Filiado|Empregado|Data Início|Não Cooperado|\d{2}\/)/i', $blocoLimpo, $empMatches)) {
                $empresa = $empMatches[1];
            }
        }

        $empresa = trim(preg_replace('/\s+/', ' ', $empresa));
        // Se a busca falhou e engoliu o texto todo, trunca para não quebrar a View
        if (strlen($empresa) > 80) {
            $empresa = substr($empresa, 0, 80) . '...';
        }

        $inicio = 'N/A';
        $fim = 'Ativo / Não informado';

        if (preg_match_all('/(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/\d{4}/', $bloco, $dataMatches)) {
            $inicio = $dataMatches[0][0] ?? 'N/A';
            if (isset($dataMatches[0][1])) {
                $fim = $dataMatches[0][1];
            }
        }

        return [$cnpj, $empresa, $inicio, $fim];
    }

    private function obterHistoricoSalarioMinimo(): array
    {
        return Cache::remember('historico_salario_minimo_bcb', now()->addDays(30), function () {
            $url = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.1619/dados?formato=json';
            $response = Http::timeout(10)->get($url);

            // Fallback fortalecido caso o container Docker não consiga acessar a API externa
            if ($response->failed()) {
                return [
                    '2024-01' => 1412.00,
                    '2023-05' => 1320.00,
                    '2023-01' => 1302.00,
                    '2022-01' => 1212.00,
                    '2021-01' => 1100.00,
                    '2020-02' => 1045.00,
                    '2020-01' => 1039.00,
                ];
            }

            $dados = $response->json();
            $historico = [];

            foreach ($dados as $item) {
                $data = Carbon::createFromFormat('d/m/Y', $item['data'])->format('Y-m');
                $historico[$data] = (float) $item['valor'];
            }

            krsort($historico);
            return $historico;
        });
    }

    private function encontrarMinimoDaEpoca(Carbon $dataCompetencia, array $historicoSalarial): float
    {
        foreach ($historicoSalarial as $anoMes => $valor) {
            $dataVigencia = Carbon::createFromFormat('Y-m', $anoMes)->startOfMonth();
            if ($dataCompetencia->greaterThanOrEqualTo($dataVigencia)) {
                return $valor;
            }
        }
        return 0.0;
    }
}
