-- Executado automaticamente na primeira vez que o container sobe
-- O banco e o usuário já são criados via variáveis de ambiente no docker-compose

-- Extensões úteis para Laravel / datas / UUIDs
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";   -- buscas parciais rápidas

-- Timezone padrão da sessão
SET timezone = 'America/Sao_Paulo';
