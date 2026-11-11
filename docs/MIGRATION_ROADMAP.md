> Histórico: a direção atual está em [GEOVEIL2_REMAKE.md](GEOVEIL2_REMAKE.md). As referências abaixo ao GeoVeil anterior não descrevem o estado do remake.

# Roadmap de migracao

## Principios

1. O CCDAE original e referencia e fonte de dados, nao base para copia indiscriminada.
2. Cada mapa nasce de uma camada GeoVeil; JavaScript geoespacial especifico deve ser excecao documentada.
3. Dados simulados e dados reais precisam carregar proveniencia explicita.
4. O recode compartilha o banco do CCDAE sem assumir propriedade sobre suas migrations.
5. Um recurso so e considerado pronto quando PHP, payload, navegador e teste de integracao funcionam.

## Marcos

### M0 - Fundacao

- [x] Laravel 12 limpo.
- [x] GeoVeil como dependencia local.
- [x] Pipeline Vite e Alpine.
- [x] Primeiro mapa de distritos.
- [x] Ambiente Docker Compose conectado a rede compartilhada `rede_local`.
- [x] Reutilizacao segura do PostgreSQL/PostGIS/TimescaleDB do CCDAE.
- [ ] Contratos de leitura para os schemas `geo`, `climate`, `infrastructure`, `mobility`, `energy` e `internet`.

### M1 - Territorio

- [x] Ler distritos reais de `geo.distritos`.
- [ ] Corrigir e adotar `ChoroplethLayer` do GeoVeil.
- [ ] Legenda de classes populacionais.
- [ ] Busca por distrito e enquadramento automatico.

### M2 - Clima e series temporais

- [ ] Estacoes meteorologicas.
- [ ] Temperatura, chuva, UV, pressao, radiacao, vento e particulas.
- [ ] Implementar timeline real no GeoVeil.
- [ ] Politica `latest per station` e filtros por intervalo.

### M3 - Infraestrutura

- [ ] Postes, antenas e centrais.
- [ ] Cabeamento eletrico e fibra.
- [ ] Clustering, simbolos e estilos orientados por propriedade.
- [ ] Edificacoes e extrusoes 3D.

### M4 - Operacao e risco

- [ ] Incidentes e ordens de manutencao.
- [ ] Desgaste e probabilidade de falha.
- [ ] Priorizacao de manutencao com indicadores auditaveis.
- [ ] Correlacao entre clima, transito e falhas.

### M5 - Escala

- [ ] Carregamento por viewport.
- [ ] MVT via Martin/PostGIS.
- [ ] Observabilidade e metas de desempenho.
- [ ] Testes de navegador para todos os controles GeoVeil.

## Dependencias prioritarias no GeoVeil

Antes dos mapas avancados, o pacote precisa entregar de ponta a ponta:

- expressoes e escalas de cor tipadas;
- legenda renderizada;
- filtros e busca;
- timeline com filtragem efetiva das features;
- estilos dinamicos de raio e largura;
- simbolos e icones;
- validacao das expressoes MapLibre;
- testes reais no navegador.
