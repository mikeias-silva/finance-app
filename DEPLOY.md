# Deploy — Oracle Cloud (Ubuntu, VM.Standard.E2.1.Micro)

Máquina: 1 OCPU, **1 GB de RAM**. IP: `137.131.228.2`.
Banco: SQLite (arquivo em `database/database.sqlite`).

> A aplicação sobe **sem autenticação**, acessível publicamente. Decisão tomada
> conscientemente para esta fase; ver a seção final quando for fechar o acesso.

---

## 1. Swap — faça isso antes de tudo

Com 1 GB, o `composer install` e o build da imagem podem morrer por falta de
memória. 2 GB de swap resolvem e custam só disco.

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

## 2. Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker --version && docker compose version
```

## 3. Liberar a porta 80 — dois lugares, não um

Esta é a parte que costuma travar o deploy na Oracle. **Não basta o firewall do
Ubuntu**: a OCI tem uma camada de rede própria, e a imagem Ubuntu vem com regras
de iptables que descartam tudo que não seja SSH.

**3a. Security List da OCI** (pelo painel web):
Networking → Virtual Cloud Networks → sua VCN → Security Lists → Default →
*Add Ingress Rule*:

| Campo | Valor |
|---|---|
| Source CIDR | `0.0.0.0/0` |
| IP Protocol | TCP |
| Destination Port Range | `80` |

**3b. iptables na máquina:**

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo netfilter-persistent save
```

Sem o passo 3b a porta responde de dentro da máquina mas não de fora — o sintoma
é `curl localhost` funcionar e o navegador dar timeout.

## 4. Código e configuração

```bash
sudo mkdir -p /opt/finance && sudo chown $USER:$USER /opt/finance
git clone <URL-DO-SEU-REPO> /opt/finance
cd /opt/finance
```

O `.env` **não vem no repositório** (está no `.gitignore`, e é onde ficam as
credenciais do Pluggy). Crie no servidor:

```bash
cat > .env <<'EOF'
APP_NAME="Finance App"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://137.131.228.2
APP_KEY=
APP_PASSWORD_HASH=

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_LIFETIME=43200
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr

PLUGGY_CLIENT_ID=
PLUGGY_CLIENT_SECRET=
PLUGGY_ITEM_ID_NUBANK=
PLUGGY_ITEM_ID_CAIXA=
EOF

chmod 600 .env
```

Preencha as quatro variáveis do Pluggy com os valores do seu `.env` local.

A `APP_PASSWORD_HASH` é a senha única de acesso ao app — sem ela o login
rejeita qualquer tentativa e o sistema fica inacessível. Gere o hash com:

```bash
docker compose -f docker-compose.prod.yml run --rm php php artisan app:password
```

e cole a linha impressa no `.env`, **com as aspas simples**: sem elas o
phpdotenv lê o `$2y$` como interpolação de variável e o hash chega mutilado.

## 5. Banco e permissões

```bash
mkdir -p database storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
touch database/database.sqlite

# uid 1000 é o usuário 'app' dentro do container
sudo chown -R 1000:1000 storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache
```

## 6. Subir

```bash
cd /opt/finance
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm php composer install --no-dev --optimize-autoloader --no-interaction
docker compose -f docker-compose.prod.yml run --rm php php artisan key:generate --force
docker compose -f docker-compose.prod.yml run --rm php php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d
```

`key:generate` grava a `APP_KEY` no `.env`. O compose recusa subir sem ela — é
proposital, porque sem `APP_KEY` a criptografia de sessão do Laravel quebra.

Otimizações de produção (rode depois de cada deploy):

```bash
docker compose -f docker-compose.prod.yml exec php php artisan config:cache
docker compose -f docker-compose.prod.yml exec php php artisan route:cache
docker compose -f docker-compose.prod.yml exec php php artisan view:cache
```

## 7. Verificar

```bash
curl -sI http://localhost/          # de dentro da máquina
curl -sI http://137.131.228.2/      # do seu computador
docker compose -f docker-compose.prod.yml ps
docker stats --no-stream            # confira o consumo de RAM
free -h
```

---

## Atualizar depois

```bash
cd /opt/finance
git pull
docker compose -f docker-compose.prod.yml run --rm php composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml run --rm php php artisan migrate --force
docker compose -f docker-compose.prod.yml restart php
docker compose -f docker-compose.prod.yml exec php php artisan config:cache
```

O `restart php` é necessário porque o opcache de produção está com
`validate_timestamps = 0` — sem reiniciar, o PHP continua servindo o código antigo.

## Backup do banco

SQLite é um arquivo só, então backup é cópia. Vale automatizar:

```bash
# no crontab -e
0 3 * * * cd /opt/finance && cp database/database.sqlite database/backup-$(date +\%Y\%m\%d).sqlite && find database -name 'backup-*.sqlite' -mtime +14 -delete
```

## Memória — o que esperar

| Componente | Aproximado |
|---|---|
| Ubuntu + Docker | 250–320 MB |
| nginx | 10 MB |
| php-fpm (até 4 workers) | 200–280 MB |
| **Sobra** | **~350 MB** |

Se `docker stats` mostrar o php perto de 420 MB, algo está segurando memória:
reduza `pm.max_children` em `docker/php/www.conf` de 4 para 2.

---

## Quando for fechar o acesso

A aplicação tem 72 rotas, 30 delas de escrita, todas abertas. As opções
discutidas, em ordem de segurança:

1. **Tailscale** — tira a máquina da internet; só seus dispositivos alcançam.
2. **Magic link + remember cookie** (Laravel, URLs assinadas) — exige HTTPS para
   o cookie não trafegar legível. Sem domínio, dá para usar DuckDNS + Let's Encrypt.
3. **Basic Auth no nginx** — mais simples, protege tudo de uma vez.
