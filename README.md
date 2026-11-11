# CCDAE Recode

Reconstrucao incremental do Centro de Coleta de Dados Ambientais e Espaciais, usando o GeoVeil como camada declarativa de visualizacao geoespacial.

## Objetivo

Substituir os mapas isolados e a logica geoespacial duplicada do CCDAE por modulos verticais Laravel que compartilham contratos, fontes e camadas do GeoVeil. O projeto original permanece como referencia funcional e fonte de dados; ele nao e alterado por este recode.

## Estado atual

- Laravel 12 instalado.
- GeoVeil 2 integrado como pacote local em `packages/geoveil2`, substituindo o GeoVeil anterior.
- Mapa de população por distrito em `/mapas/distritos`; formulários de distritos, incidentes de energia/internet, infrações de trânsito, postes, antenas, equipamentos, centrais de distribuição e cabeamentos.
- Docker configurado para compartilhar o PostgreSQL/TimescaleDB do CCDAE.
- O modelo `Distrito` usa `geo.distritos` no PostgreSQL e `distritos` no SQLite de testes.
- Testes funcionais de mapas, autenticação e formulários com banco SQLite isolado.

## Executar com o banco do CCDAE

O CCDAE original precisa estar com `postgres-ccdae` ativo na rede externa `rede_local`.

```bash
cd /home/zucchi/Projetos/CCDAE
docker compose up -d postgres martin

cd /home/zucchi/Projetos/ccdae-recode
./bin/ccdae-docker up -d
```

Abra `http://localhost:8788`. O recode usa as credenciais de `../CCDAE/.env` apenas para interpolar a conexao com o mesmo banco; nenhuma senha e copiada para este repositorio.

Para acompanhar ou encerrar:

```bash
./bin/ccdae-docker logs -f app
./bin/ccdae-docker down
```

## Seguranca do banco compartilhado

O Compose do recode sobe somente a aplicacao. Ele nao declara outro PostgreSQL, nao executa migrations na inicializacao e usa cache, sessoes e filas fora do banco compartilhado.

Nao execute `migrate:fresh`, `db:wipe` ou seeders do recode contra o PostgreSQL do CCDAE. A migration local de distritos existe apenas para a suite isolada em SQLite.

## Desenvolvimento e testes isolados

```bash
docker run --rm \
  -v /home/zucchi/Projetos:/workspace \
  -w /workspace/ccdae-recode \
  composer:2 php artisan test

npm install
npm run build
```

## Direcao arquitetural

Consulte [o plano do remake](docs/GEOVEIL2_REMAKE.md) e [a API do pacote](packages/geoveil2/README.md). O [roadmap anterior](docs/MIGRATION_ROADMAP.md) permanece como histórico. Cada modulo deve entregar conjuntamente modelo, persistencia, fonte GeoVeil, camada, pagina e teste. Recursos ausentes no GeoVeil devem ser implementados e testados no pacote antes de receberem uma dependencia de producao no CCDAE Recode.

## GeoVeil 2

O mapa de distritos usa agora o pacote novo. A tela padrão usa superfície 3D no MapLibre e permite alternar para distritos 2D no Leaflet. O layout escuro foi comparado ao CCDAE original. Configuração: `app/Http/Controllers/Map/DistrictMapController.php`. Busca por cidade, seleção, legenda, indicadores e gráfico compartilham o mesmo estado.

O mapa global de infraestrutura usa camadas do GeoVeil 2 e oferece a aba «Visualizador 3D» do CCDAE por distrito. As construções vêm da tabela OSM do banco original; o relevo Terrarium está habilitado por padrão como no CCDAE e pode ser ajustado com `MAP_TERRAIN_ENABLED`, `MAP_TERRAIN_TILE_URL`, `MAP_TERRAIN_ZOOM` e `MAP_TERRAIN_EXAGGERATION`.

Para os testes JavaScript: `npm --prefix packages/geoveil2 test`. Geocodificação externa é opcional; configure o endpoint e o identificador em `.env` conforme o README do pacote.

## Conta master

A identidade única de administração do CCDAE e do GeoVeil 2 é `master@ccdae.local`. Ela usa a tabela `public.users` do PostgreSQL compartilhado com o CCDAE original. O e-mail é um identificador local, não precisa receber mensagens. A senha inicial é aleatória e fica apenas em `storage/app/private/master-credential.txt` (permissão 0600), criada pelo comando `php artisan ccdae:provision-master` dentro do contêiner. O comando não altera contas existentes. Depois do primeiro login, use **Alterar senha** e remova o arquivo de credencial inicial. As rotas de edição verificam o e-mail master no servidor.

Na página **Saúde do Sistema**, **Executar coleta** coloca os jobs de clima e trânsito na tabela `jobs` compartilhada, com payload compatível com o CCDAE original. O worker do projeto original deve estar ativo para executá-los; o recode não processa esses jobs. O sinal de vida do worker é lido do cache de banco com o prefixo `CCDAE_ORIGINAL_CACHE_PREFIX` (`laravel-cache-` por padrão). A ação está disponível apenas para a conta master.
