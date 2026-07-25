<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador de CNIS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f9; font-size: 14px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .result-box { background: #eef; padding: 15px; margin-top: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background-color: #f8f9fa; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Importador de Extrato CNIS (PDF)</h2>

    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('cnis.importar') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div style="margin-bottom: 15px;">
            <label for="arquivo"><strong>Selecione o arquivo extrato.pdf:</strong></label><br><br>
            <input type="file" name="arquivo" id="arquivo" accept="application/pdf" required>
        </div>
        <button type="submit" style="padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Processar Arquivo
        </button>
    </form>

    @if(isset($sucesso))
        <div class="result-box">
            <h3>Resultado do Processamento</h3>
            <p><strong>Total de Competências Consideradas:</strong> {{ $total_meses }}</p>
            <p><strong>Soma Total das Remunerações (≥ Mínimo):</strong> R$ {{ number_format($soma_total, 2, ',', '.') }}</p>
            <p><strong>Média Geral:</strong> R$ {{ number_format($media, 2, ',', '.') }}</p>
        </div>

        <h4>Detalhamento Analítico</h4>
        <table>
            <thead>
            <tr>
                <th>Empresa (Origem do Vínculo)</th>
                <th>CNPJ / Código</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Competência</th>
                <th>Valor (R$)</th>
                <th>Mínimo da Época (R$)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($remuneracoes as $rem)
                <tr>
                    <td>{{ $rem['empresa'] }}</td>
                    <td>{{ $rem['cnpj'] }}</td>
                    <td>{{ $rem['inicio'] }}</td>
                    <td>{{ $rem['fim'] }}</td>
                    <td><strong>{{ $rem['competencia'] }}</strong></td>
                    <td>R$ {{ number_format($rem['valor'], 2, ',', '.') }}</td>
                    <td style="color: #666;">R$ {{ number_format($rem['minimo_epoca'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

</body>
</html>
