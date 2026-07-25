<?php

namespace App\Console\Commands;

use App\Services\PluggyService;
use Illuminate\Console\Command;

class PluggyConnect extends Command
{
    protected $signature = 'pluggy:connect {institution : Nome da instituição, ex: Nubank ou Caixa}';
    protected $description = 'Conecta uma instituição via Open Finance no Pluggy e retorna o itemId gerado';

    public function handle(PluggyService $pluggy): int
    {
        $institution = $this->argument('institution');

        $connector = $pluggy->findConnector($institution);

        if (! $connector) {
            $this->error("Nenhum conector encontrado para \"{$institution}\".");
            return self::FAILURE;
        }

        $this->info("Conector encontrado: {$connector['name']} (id {$connector['id']})");

        $item = $pluggy->createItem($connector['id']);
        $itemId = $item['id'];

        $this->showOauthLinkIfAny($item);

        $this->newLine();
        $this->info('Aguardando você autorizar no navegador...');

        $status = $item['status'] ?? 'UPDATING';
        $shownParameterAt = $item['parameter']['data'] ?? null;
        $attempts = 0;

        while (in_array($status, ['UPDATING', 'WAITING_USER_INPUT'], true) && $attempts < 60) {
            sleep(5);
            $item = $pluggy->getItem($itemId);
            $status = $item['status'] ?? $status;
            $attempts++;

            $currentParameter = $item['parameter']['data'] ?? null;
            if ($currentParameter && $currentParameter !== $shownParameterAt) {
                $shownParameterAt = $currentParameter;
                $this->showOauthLinkIfAny($item);
            }

            $this->line("Status atual: {$status}");
        }

        if ($status !== 'UPDATED') {
            $this->error("Conexão não concluída (status final: {$status}). itemId gerado: {$itemId}");
            if (in_array($status, ['LOGIN_ERROR', 'OUTDATED'], true)) {
                $this->comment('O link de autorização provavelmente expirou. Rode o comando novamente e autorize mais rápido.');
            }
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Conexão concluída com sucesso!');
        $this->line("itemId: {$itemId}");
        $this->newLine();
        $this->info("Adicione no seu .env: PLUGGY_ITEM_ID_".strtoupper(str_replace(' ', '_', $institution))."={$itemId}");

        return self::SUCCESS;
    }

    private function showOauthLinkIfAny(array $item): void
    {
        $parameter = $item['parameter'] ?? null;

        if (! $parameter || ($parameter['type'] ?? null) !== 'oauth' || empty($parameter['data'])) {
            return;
        }

        $this->newLine();
        $this->info('Abra este link no navegador para autorizar (expira rápido, em poucos minutos):');
        $this->line($parameter['data']);

        if (! empty($parameter['expiresAt'])) {
            $this->comment("Expira em: {$parameter['expiresAt']}");
        }
    }
}
