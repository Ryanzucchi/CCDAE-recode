# GeoVeil 2 — arquitetura e cobertura

## Decisão

Reconstrução independente em `packages/geoveil2`. Mantém o nome Composer `geoveil/geoveil`, agora na versão local `2.0.0-dev`, substituindo a dependência do diretório irmão. A implementação anterior em `../GeoVeil` foi preservada.

Configuração PHP → contrato JSON v2 → workspace JavaScript → adaptador do motor.

O pacote concentra componentes geográficos e analíticos. Modelos, regras de cálculo, endpoints autenticados, persistência, importação e autorizações pertencem à aplicação. Não há dependência do Filament nem de modelos CCDAE dentro do pacote.

## Cobertura do objetivo CCDAE

| Área | Entrega atual | Próximo trabalho |
|---|---|---|
| Território | Distritos, população, legendas, filtro de cidade, seleção, indicadores e barras | Edição e validação topológica PostGIS |
| Localização | Contrato, adaptador Nominatim configurável e busca explícita no frontend | Provedor operacional e geocodificação reversa |
| Clima | Camadas pontuais, filtro temporal, widget de séries | Fontes por estação, latest-per-station e timeline |
| Infraestrutura | Pontos, linhas, polígonos; heatmap e extrusão no adaptador MapLibre | Adaptadores dos modelos CCDAE, símbolos, clustering; teste de navegador 3D |
| Energia / internet | Componentes de visualização reutilizáveis | Migrar serviços analíticos, unidades e proveniência |
| Trânsito | Linhas e suporte genérico a filtro temporal | Fonte de fluxo, timeline e reprodução |
| Grandes volumes | Limite explícito para fonte Eloquent inline | Fonte por viewport, MVT/Martin, cache e cancelamento |
| Operação | Painel de detalhes e eventos de seleção | Formulários, incidentes, ações autorizadas e auditoria |

## Testes e limites

- Testes PHP em container sem rede, SQLite em memória. Nenhuma migration rodou contra o PostgreSQL compartilhado.
- Testes de estado JavaScript: composição de busca/filtros/seleção, intervalo UTC, totais, cores, limites geográficos e séries.
- Verificação manual de navegador com quatro polígonos sintéticos: ambos os motores, filtros, seleção e estado vazio. Série temporal e recorte de datas também verificados com geometrias sincronizadas.
- A aplicação de produção continua usando a fonte real `Distrito::query()`. A prévia de verificação não é uma rota de produção e não substitui dados reais.
- Falta validar os módulos completos com a base real; não foi declarada paridade com o CCDAE original.

## Sequência recomendada

1. Validar a ergonomia da API e os componentes entregues com o usuário.
2. Implementar editor de pontos/linhas/polígonos com contratos de salvamento autorizados.
3. Entregar clima completo com estações, séries, unidades, proveniência e timeline.
4. Migrar infraestrutura e os serviços analíticos de energia/internet.
5. Migrar trânsito e escalar fontes com consultas espaciais e MVT.

Cada etapa precisa entregar configuração PHP, comportamento real no frontend e verificação de integração. Nenhuma classe declarativa conta como recurso concluído sem renderização correspondente.

## Dependências

MapLibre atualizado para a série 6.16 e correções JavaScript compatíveis aplicadas; auditoria npm após as correções: zero alertas. A auditoria Composer identificou cinco avisos nas dependências preexistentes (Laravel, CommonMark, Symfony HttpFoundation e Mime). Essas dependências PHP não foram atualizadas em bloco durante o remake; devem ser corrigidas e verificadas antes de uma publicação operacional.

Resultado final desta entrega: 17 testes PHP (34 asserções), 6 testes JavaScript, formatação PHP e build de produção aprovados. Geocodificação foi verificada por HTTP simulado; nenhum serviço externo de busca foi habilitado.

## Comparação com o original — 4 de novembro de 2026

O original foi aberto em `http://ccdae.local/admin/mapa-populacao`. O erro de login foi resolvido limpando apenas as views compiladas. A aplicação original permaneceu sem alterações de código ou dados.

A comparação mostrou que o mapa de população usa uma superfície interpolada 3D, não polígonos planos. O remake agora usa `SurfaceLayer` com o mesmo núcleo de interpolação e escala de altura, legenda de dez faixas e controle de corte por intensidade. O painel recebeu tema escuro, navegação lateral e mapa central amplo; filtros e indicadores são painéis recolhíveis. Links de módulos ainda não migrados estão explicitamente identificados como links para o original.

Também foi corrigida a conexão do servidor HTTP do recode: `artisan serve` descartava as variáveis de ambiente do Docker e carregava o SQLite de demonstração. `--no-reload` preserva as variáveis e faz a página usar `geo.distritos` no PostgreSQL compartilhado. Nenhuma migration ou seeder foi executado. A base contém 47 distritos; o intervalo populacional observado nos dois mapas é 262 a 3.359.

Validação: 18 testes PHP (37 asserções), 7 testes JavaScript e build; teste visual da superfície e corte de intensidade sobre a base compartilhada. Esta entrega aproxima a tela de população; os demais módulos continuam pendentes de migração.

Tema: acrescentado seletor claro/escuro no cabeçalho, com persistência local e atualização em tempo real dos dois adaptadores. A troca não recria a malha, não limpa filtros e não altera os dados.

### Navegação e distritos (05/11/2026)

O layout compartilhado `layouts/ccdae.blade.php` agora atende ao mapa e à listagem local `/distritos`. A listagem consulta os distritos existentes, permite busca por nome/cidade e paginação de 15 registros. O menu tem botão de expansão, fechamento por Escape, devolução de foco e fundo clicável no celular. As entradas de clima, infraestrutura e trânsito continuam identificadas como acesso ao original; esses módulos ainda não têm paridade no remake.

A revisão de sintaxe PHP/JavaScript passou. A comparação visual desta etapa ficou pendente porque `ccdae.local` recusou conexão. O teste automatizado excedeu o tempo de execução no ambiente; não considerar esta etapa homologada apenas pela revisão de sintaxe.

Atualização: os serviços voltaram a responder. Build concluído e listagem real com 47 distritos verificada no navegador, incluindo busca sem distinção de maiúsculas e recolhimento do menu. O PHP do host não tem driver SQLite (14 testes passaram, 5 bloqueados por driver); validação adicional solicitada no contêiner.

### Mapas de clima, infraestrutura e trânsito (05/11/2026)

As rotas `/mapas/clima`, `/mapas/infraestrutura` e `/mapas/transito` agora são locais e usam as camadas do GeoVeil 2, com seleção de distrito, MapLibre/Leaflet e temas compartilhados. Clima oferece sete métricas, origem/fonte e seleção das últimas 24 leituras disponíveis na janela de 24 horas. Trânsito oferece velocidades, direção/manutenção nos detalhes e seleção de leituras históricas. Infraestrutura oferece postes, antenas, centrais, equipamentos e cabeamentos, com aviso explícito de limite de 10.000 registros por camada e filtro por distrito.

Ainda pendentes para equivalência com o original: reprodução animada do histórico, partículas de vento e fluxo, cena dedicada de infraestrutura 3D, construções e carregamento por tiles para grandes volumes. Essas funcionalidades não devem ser apresentadas como migradas.

Validação: build concluído; 21 testes e 54 asserções passaram com SQLite em memória no contêiner. Páginas reais abertas no navegador; seleção histórica de trânsito conferida. Para testes no Docker, sobrescrever APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array e SESSION_DRIVER=array (o ambiente de execução normal usa PostgreSQL compartilhado).
