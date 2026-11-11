# Paridade CCDAE → GeoVeil 2

Meta ativa: concluir navegação, formulários, mapas e módulos. Uma rota existente não significa paridade funcional.

## Prioridade: Distritos
- [x] Corrigir escala de população e contornos no Leaflet.
- [x] Mapa de distritos por cidade integrado ao cadastro; começa pela seleção de cidade como o original e diferencia os distritos por cores categóricas.
- [x] Sem cidade selecionada, a tela mostra a mensagem centralizada “Escolha o seu foco” sobre o mapa nos temas claro e escuro, como o original; conferido no navegador em `/admin/distritos`.
- [x] O mapa do cadastro usa o layout `embedded` reutilizável do GeoVeil 2: 600 px, sem a barra e os painéis do explorador. Cores distintas e popup ao selecionar distrito conferidos no navegador com Cáceres.
- [x] Clicar em um distrito abre o popup sem alterar o enquadramento, como no original; `fitOnSelect(false)` foi acrescentado à API do mapa e conferido no Leaflet (escala e transformação iguais antes/depois do clique).
- [x] Seleção de cidade controla somente o mapa, como no `ManageDistritos` original; a busca e a paginação da tabela permanecem independentes.
- [x] Trocar a cidade preserva busca, ordenação e página da tabela; limpar a busca mantém a cidade selecionada e a ordenação.
- [x] No Leaflet, as cores seguem a ordem dos distritos da cidade e a paleta do mapa original; polígonos e pontos compartilham a mesma atribuição de cores.
- [x] Ao selecionar uma cidade, o mapa Leaflet enquadra automaticamente os distritos carregados, como a página original.
- [x] Criar/editar distrito: nome, cidade, população e consumo; fonte da população exibida como somente leitura como no original.
- [x] Desenhar/editar polígono ou retângulo, apagar/restaurar fronteira e calcular centro; recorte de sobreposição via PostGIS validado por consulta somente leitura.
- [x] Busca por atributo no mapa, seleção de distrito e acesso direto ao formulário de edição.
- [x] Ao selecionar um distrito, o popup do mapa e o painel de detalhes oferecem "Editar Distrito" para a conta master e abrem o formulário em modal; a ação é declarada na camada GeoVeil 2 e funciona com Leaflet ou MapLibre.
- [x] Distritos sem geometria: criar células de Voronoi a partir dos centros, com ponto para distrito isolado, preservando a seleção e o acesso à edição.
- [x] Listagem: mostrar latitude e longitude e ordenar pelas colunas do original.
- [x] Listagem: colunas opcionais de criação/atualização, exclusão individual e em lote para a conta master; o modal fecha e atualiza a tabela após exclusão.
- [x] Em `/admin/distritos`, ordenação, busca e limpeza permanecem no slug original; conferido no navegador com a cidade Cáceres.
- [x] Abrir criação/edição de distrito em modal na listagem e após seleção no mapa, usando o mesmo formulário e validação da página direta.
- [ ] Comparação visual detalhada do mapa e modal de distritos com o original.
  - O recode foi conferido visualmente em claro e escuro com Cáceres: sete cores categóricas distintas e polígonos carregados. A comparação lado a lado com o original autenticado ainda está pendente.

## Inventário de páginas e recursos originais

Auditoria de URLs em 11/11/2026: 68 dos 74 caminhos únicos sob `/admin`, `/energia`, `/internet` e `/transito` têm equivalente no recode. Os seis caminhos restantes são `/admin/activity-logs/create`, `/admin/activity-logs/{id}/edit` e quatro rotas de `/admin/shield/roles`. No original, `ActivityLogForm` não declara campos, a tabela não oferece criar/editar, e a tabela PostgreSQL `roles` nem existe no banco compartilhado. Esses caminhos ainda não constituem funcionalidade migrada; caso as telas do Shield passem a ser usadas, exigirão migração de esquema e política de acesso específica.

### Admin
- [ ] Admin/Pages/MapaClima.php
- [ ] Admin/Pages/MapaInfraestrutura.php
- [ ] Admin/Pages/MapaPopulacao.php
- [ ] Admin/Pages/MapaTransito.php
- [ ] Admin/Pages/SystemHealth.php
- [ ] Admin/Resources/ActivityLogs/ActivityLogResource.php
- [ ] Admin/Resources/ActivityLogs/Pages/CreateActivityLog.php
- [ ] Admin/Resources/ActivityLogs/Pages/EditActivityLog.php
- [ ] Admin/Resources/ActivityLogs/Pages/ListActivityLogs.php
- [ ] Admin/Resources/ActivityLogs/Pages/ViewActivityLog.php
- [ ] Admin/Resources/Antenas/AntenaResource.php
- [ ] Admin/Resources/Antenas/Pages/CreateAntena.php
- [ ] Admin/Resources/Antenas/Pages/EditAntena.php
- [ ] Admin/Resources/Antenas/Pages/ListAntenas.php
- [ ] Admin/Resources/Cabeamentos/CabeamentoResource.php
- [ ] Admin/Resources/Cabeamentos/Pages/CreateCabeamento.php
- [ ] Admin/Resources/Cabeamentos/Pages/EditCabeamento.php
- [ ] Admin/Resources/Cabeamentos/Pages/ListCabeamentos.php
- [ ] Admin/Resources/CentralDistribuicaos/CentralDistribuicaoResource.php
- [ ] Admin/Resources/CentralDistribuicaos/Pages/CreateCentralDistribuicao.php
- [ ] Admin/Resources/CentralDistribuicaos/Pages/EditCentralDistribuicao.php
- [ ] Admin/Resources/CentralDistribuicaos/Pages/ListCentralDistribuicaos.php
- [ ] Admin/Resources/ChuvaRegistradas/ChuvaRegistradaResource.php
- [ ] Admin/Resources/ChuvaRegistradas/Pages/ManageChuvaRegistradas.php
- [ ] Admin/Resources/Distritos/DistritoResource.php
- [ ] Admin/Resources/Distritos/Pages/ManageDistritos.php
- [ ] Admin/Resources/EquipamentoInfraestruturas/EquipamentoInfraestruturaResource.php
- [ ] Admin/Resources/EquipamentoInfraestruturas/Pages/CreateEquipamentoInfraestrutura.php
- [ ] Admin/Resources/EquipamentoInfraestruturas/Pages/EditEquipamentoInfraestrutura.php
- [ ] Admin/Resources/EquipamentoInfraestruturas/Pages/ListEquipamentoInfraestruturas.php
- [ ] Admin/Resources/IndiceUVS/IndiceUVResource.php
- [ ] Admin/Resources/IndiceUVS/Pages/ManageIndiceUVS.php
- [ ] Admin/Resources/ParticulaArs/Pages/ManageParticulaArs.php
- [ ] Admin/Resources/ParticulaArs/ParticulaArResource.php
- [ ] Admin/Resources/Postes/Pages/CreatePoste.php
- [ ] Admin/Resources/Postes/Pages/EditPoste.php
- [ ] Admin/Resources/Postes/Pages/ListPostes.php
- [ ] Admin/Resources/Postes/PosteResource.php
- [ ] Admin/Resources/PressaoAtmosfericas/Pages/ManagePressaoAtmosfericas.php
- [ ] Admin/Resources/PressaoAtmosfericas/PressaoAtmosfericaResource.php
- [ ] Admin/Resources/RadiacaoSolars/Pages/ManageRadiacaoSolars.php
- [ ] Admin/Resources/RadiacaoSolars/RadiacaoSolarResource.php
- [ ] Admin/Resources/RegistroTransitos/Pages/CreateRegistroTransito.php
- [ ] Admin/Resources/RegistroTransitos/Pages/EditRegistroTransito.php
- [ ] Admin/Resources/RegistroTransitos/Pages/ListRegistroTransitos.php
- [ ] Admin/Resources/RegistroTransitos/Pages/ViewRegistroTransito.php
- [ ] Admin/Resources/RegistroTransitos/RegistroTransitoResource.php
- [ ] Admin/Resources/TemperaturaRegistradas/Pages/ManageTemperaturaRegistradas.php
- [ ] Admin/Resources/TemperaturaRegistradas/TemperaturaRegistradaResource.php
- [ ] Admin/Resources/VentoRegistrados/Pages/ManageVentoRegistrados.php
- [ ] Admin/Resources/VentoRegistrados/VentoRegistradoResource.php
- [ ] Admin/Resources/ViaTransitos/Pages/CreateViaTransito.php
- [ ] Admin/Resources/ViaTransitos/Pages/EditViaTransito.php
- [ ] Admin/Resources/ViaTransitos/Pages/ListViaTransitos.php
- [ ] Admin/Resources/ViaTransitos/Pages/ViewViaTransito.php
- [ ] Admin/Resources/ViaTransitos/ViaTransitoResource.php

### Energia
- [ ] Energia/Pages/EnergiaDashboard.php
- [ ] Energia/Pages/MapaConsumo.php
- [ ] Energia/Pages/MapaEnergia.php
- [ ] Energia/Resources/Incidentes/IncidenteEnergiaResource.php
- [ ] Energia/Resources/Incidentes/Pages/CreateIncidente.php
- [ ] Energia/Resources/Incidentes/Pages/EditIncidente.php
- [ ] Energia/Resources/Incidentes/Pages/ListIncidentes.php

### Internet
- [ ] Internet/Pages/InternetDashboard.php
- [ ] Internet/Pages/MapaDemanda.php
- [ ] Internet/Pages/MapaRede.php
- [ ] Internet/Resources/Incidentes/IncidenteInternetResource.php
- [ ] Internet/Resources/Incidentes/Pages/CreateIncidente.php
- [ ] Internet/Resources/Incidentes/Pages/EditIncidente.php
- [ ] Internet/Resources/Incidentes/Pages/ListIncidentes.php

### Transito
- [ ] Transito/Pages/MapaAcidentes.php
- [ ] Transito/Pages/MapaInfracoes.php
- [ ] Transito/Pages/MapaObras.php
- [ ] Transito/Pages/MapaTransito.php
- [ ] Transito/Pages/MapaVolumeVeiculos.php
- [ ] Transito/Pages/TransitoDashboard.php
- [ ] Transito/Resources/Infracoes/InfracaoTransitoResource.php
- [ ] Transito/Resources/Infracoes/Pages/CreateInfracao.php
- [ ] Transito/Resources/Infracoes/Pages/EditInfracao.php
- [ ] Transito/Resources/Infracoes/Pages/ListInfracoes.php

## Mapas de intensidade migrados
- [x] Fuso padrão do recode alinhado a `America/Cuiaba`, como no CCDAE original, para preservar horários dos formulários e janelas dos mapas; a janela de acidentes de 12:21 a 12:21 do dia seguinte foi conferida no navegador.
- [x] Densidade da rede elétrica: postes por célula e superfície 3D, com controle de altitude.
- [x] Densidade elétrica e da rede de internet usam a indexação `floor` da grade de 0,00115° do serviço original; teste com dois postes próximos confirma que não são unidos por arredondamento.
- [x] Densidade elétrica comparada diretamente com `EnergyAnalyticsService` original em sessão de leitura com esquemas de domínio antes de `public`: ambos produziram 4.556 células e soma 31.255; o SHA-256 da lista ordenada de coordenadas e valores foi idêntico (`f527bd4261c963f59a0abfe19837447e0999a0c01ad6204cd3e5daae004ad081`).
- [x] Cabos de internet usam `ST_SimplifyPreserveTopology(geom, 0.00003)` no PostgreSQL, como o serviço original, com fallback GeoJSON nos testes SQLite.
- [x] Densidade da rede de internet comparada diretamente com `InternetAnalyticsService` original na mesma sessão de leitura: 4.682 células e soma 109.263 em ambos. Após ordenar antenas, equipamentos e cabos por ID, o SHA-256 das coordenadas e valores também coincidiu (`5b5f3a33539fed6073bb237801f1ece90a3a490c1b06159c1aece9ddb8e94888`).
- [ ] Comparação numérica dos demais mapas com o serviço original no banco atual: o `search_path` original começa por `public`, cujas tabelas `distritos`, `postes`, `antenas`, `equipamentos_infraestrutura` e `cabeamentos` estão vazias. Os dados usados pelo recode estão nos esquemas `geo` e `infrastructure` (47 distritos, 31.255 postes, 14 antenas, 90.907 equipamentos e 62.261 cabos). Densidade elétrica e rede de internet já foram comparadas com o código original em sessões de leitura que priorizaram os esquemas de domínio; os outros mapas ainda exigem a mesma verificação.
- [x] Densidade da rede de internet: antenas, equipamentos e cabeamentos de fibra amostrados, com superfície 3D.
- [x] Infrações: intensidade geográfica com janela inicial de 24 horas como o original, período ajustável até 31 dias, seleção de distrito e modo Leaflet 2D.
- [x] Volume médio, acidentes e obras de trânsito: leituras das últimas 24 horas projetadas no traçado das vias a cada 180 m, células de 0,0014° como o serviço original, seleção de distrito, superfície 3D e cores específicas no Leaflet.
- [x] Consumo e população de energia e demanda potencial de internet: amostragem dos polígonos distritais com as fórmulas do original, superfície 3D, filtros e Leaflet 2D.
- [ ] Comparação visual e numérica detalhada de cada mapa com as páginas originais.

## Cadastros administrativos migrados
- [x] Postes: listagem paginada, busca por patrimônio/distrito, formulário com localização e edição pela conta master.
- [x] Antenas: listagem paginada, busca por patrimônio/distrito, formulário com localização e edição pela conta master.
- [x] Equipamentos: listagem, campos físicos, vínculos espaciais e localização por ponto.
- [x] Centrais: listagem, campos, tipo legado preservado e editor reutilizável de ponto, polígono ou retângulo, com centro calculado; áreas já salvas podem ter os vértices arrastados no mapa, como no editor original.
- [x] Cabeamentos: listagem, nós de origem/destino, traçado manual ou derivado dos nós e extensão calculada.
- [x] Cabeamentos: ao trocar poste ou central no formulário, o traçado e a extensão são atualizados em tempo real; o servidor recalcula a linha mesmo que a edição envie a geometria antiga. Um traçado desenhado manualmente depois da troca prevalece, como no formulário original. Teste cobre troca de nó, rota manual e coordenadas do seletor remoto.
- [x] Busca remota para vínculos com postes, centrais, antenas, equipamentos, cabos e vias; encontra registros além dos primeiros 1.000.
- [x] As cinco listagens de infraestrutura mostram as colunas visíveis do Filament original, pesquisam os campos permitidos e ordenam apenas as colunas ordenáveis do original.
- [x] As cinco listagens permitem mostrar/ocultar as colunas opcionais "Criado em" e "Atualizado em", com preferência salva por recurso no navegador; a opção é declarada no recurso reutilizável GeoVeil 2.
- [x] Exclusão em lote de postes, antenas, equipamentos, centrais e cabeamentos, restrita à conta master com seleção validada e confirmação.
- [x] Ação Excluir individual nos formulários de edição de infraestrutura, vias e registros de trânsito; leituras usam a chave composta via + horário.
- [x] Vias de trânsito: listagem, busca, filtro por distrito e editor de traçado LineString/MultiLineString; mantém o identificador OSM e valores legados ao editar.
- [x] Registros de trânsito: listagem com busca por via, criação e edição pela chave composta via + horário, sem atingir outra leitura; paginação sem contagem integral da tabela.
- [x] Registros de trânsito: visualização detalhada por via + horário e ordenação pelas colunas ordenáveis do original.
- [x] Vias de trânsito: ficha de consulta com os campos do original, ordenação nas colunas ordenáveis e busca por nome/tipo/congestionamento/impacto; caminhos originais de listagem, criação, consulta, edição e exclusão atendidos diretamente.
- [x] Vias e leituras: exclusão em lote restrita à conta master, com seleção explícita, confirmação e validação de até 20 itens por vez; leituras usam a chave via + horário.
- [ ] Paridade visual fina com as tabelas Filament e ações dos demais cadastros.
- [ ] Comparação visual fina das tabelas e formulários de infraestrutura com o Filament original.

## Séries climáticas
- [x] Sete listagens pelo catálogo `GeoVeil\Resources\Resource`: temperatura, vento, chuva, pressão, radiação, UV e partículas; filtros por nome/distrito e paginação sem contagem integral.
- [x] Leitura no esquema `climate` compartilhado, conferida com registros reais de temperatura.
- [x] Ordenação por cada coluna permitida no original, data/hora no formato local e faixa visual da temperatura.
- [ ] Paridade visual completa das tabelas climáticas. As sete páginas originais exibem `CreateAction`, mas temperatura, vento e chuva não implementam `form()`; pressão, radiação, UV e partículas usam `components([])`. Não há campos funcionais de criação a migrar sem definir um novo comportamento.

## Recursos migrados com a API de formulários do GeoVeil 2
- [x] Incidentes de energia: listagem, criação, edição, ponto no mapa, validação e conta master.
- [x] Incidentes de internet: listagem, criação, edição, ponto no mapa, validação e conta master.
- [x] Infrações de trânsito: listagem, criação, edição, ponto no mapa, validação e conta master.
- [x] Seletores remotos nos incidentes e infrações; incidentes de internet filtram os cabeamentos de telecomunicações, enquanto incidentes de energia aceitam qualquer cabeamento, como os formulários originais.
- [x] Incidentes de energia/internet e infrações: listagem reutilizável com colunas originais, situação, busca e ordenação das colunas permitidas pelo Filament.
- [x] Etiquetas visuais das colunas de tipo, causa, severidade e situação dos incidentes e situação das infrações, declaradas no recurso GeoVeil 2 como no Filament original.
- [x] Exclusão em lote de incidentes de energia/internet e infrações, restrita à conta master, com seleção explícita e confirmação.
- [x] Ação Excluir individual nas páginas de edição de incidentes de energia, incidentes de internet e infrações de trânsito.
- [ ] Comparação visual tela a tela desses recursos com o original.

## Verificação transversal
- [x] Conta master compartilhada presente em `public.users`; os formulários migrados exigem essa identidade no servidor.
- [x] Caminhos originais de login e saída dos quatro painéis usam a mesma sessão; o login devolve ao painel de origem.
- [ ] Navegação e slugs /admin, /energia, /internet, /transito.
- [ ] Temas claro/escuro e telas pequenas.
- [x] Histórico de trânsito agrupado por hora como no original, com a leitura mais recente de cada via em cada intervalo; seleção, avanço automático e atualização sem recarregar; vias sem leitura ficam cinza.
- [x] Detalhes das leituras de trânsito incluem volume, congestionamento, acidentes, obras, alagamento, origem, fonte e confiança quando disponíveis.
- [x] Setas de direção do fluxo nas vias MapLibre, com orientação para início→fim, fim→início ou ambos, como no mapa original.
- [x] O GeoVeil 2 abre popup de atributos em camadas Leaflet/MapLibre mesmo sem ação de edição; o popup Leaflet permanece após a seleção redesenhar a camada. Verificado no navegador com distrito de Cáceres.
- [x] Popup de vias no Leaflet mostra os sete campos e valores derivados do original (congestionamento, velocidade, direção, impacto, volume, ocorrências e origem); confirmado no navegador com uma via real e em teste do histórico.
- [ ] Comparar a aparência exata do popup de trânsito com o original autenticado; infraestrutura 3D e carregamento de grandes volumes.
- [x] Testes de formulários com banco SQLite isolado, sem gravações de teste no banco compartilhado.

### Navegação atual
- [x] Separação visual em quatro painéis (Admin, Energia, Internet, Trânsito), com menus próprios e alternância persistente entre páginas.
- [x] Compatibilidade de entrada para caminhos Filament já migrados: mapas, listagens, formulários de infraestrutura, incidentes, vias e séries climáticas; parâmetros de filtro preservados.
- [x] Oito slugs originais de mapas de energia, internet e trânsito abrem diretamente a página GeoVeil correspondente; o menu usa esses endereços.
- [x] Os slugs originais de mapas Admin, distritos e mapa de trânsito do painel Trânsito abrem diretamente sem redirecionamento; o menu Admin usa esses caminhos.
- [x] Os cinco recursos de infraestrutura usam diretamente os slugs Filament originais para lista, criação, edição, gravação e exclusão; o menu e os retornos após salvar preservam esses caminhos.
- [x] Incidentes de energia/internet e infrações de trânsito usam os caminhos Filament originais para criação, edição, gravação e exclusão; a listagem de infrações também abre diretamente no slug original.
- [x] Vias de trânsito usam os caminhos originais para lista, criação, consulta, edição, gravação e exclusão. As sete listagens climáticas abrem diretamente nos slugs originais, com os filtros preservados.
- [x] Registros de trânsito abrem diretamente em `/admin/registro-transitos`, `/create`, `/{registro}` e `/{registro}/edit`; a chave composta via + horário é codificada no segmento do registro. Criação, edição e exclusão mantêm o caminho original.
- [x] `/admin` abre o painel administrativo para a conta master, com os quatro indicadores e o gráfico de sete dias do Dashboard original. A contagem climática exata é carregada separadamente e guardada em cache por um minuto para não atrasar a página; o total real medido foi 1.756.531 registros em 47 distritos.

## Painéis dos módulos
- [x] Energia e internet: indicadores originais e mapa da rede no próprio painel, com filtro por distrito e troca MapLibre/Leaflet.
- [x] Trânsito: três indicadores do painel original, incluindo veículos na última leitura.
- [x] Painéis de Energia e Internet: indicadores aparecem antes do mapa, com o filtro de distrito junto da rede, descrição e legenda como nas páginas originais. O painel inicial de Trânsito mostra apenas os três indicadores, sem o filtro de distrito que não existe no original. Energia conferida visualmente no navegador em tema claro.
- [x] Energia e internet no MapLibre: carregar postes, cabos e demais ativos por mosaicos vetoriais Martin via GeoVeil 2, sem o corte arbitrário de 5.000 itens. O proxy local restringe as fontes publicadas e permite filtros por distrito.
- [x] Energia e internet no MapLibre: camadas 3D de postes, equipamentos e antenas usam os mosaicos do original, com altura `render_height` e zoom mínimo correspondente.
- [x] Energia e internet no Leaflet: carregar feições da janela visível via GeoVeil 2 e consulta espacial no banco, sem corte de 5.000 itens; recarregar ao mover ou ampliar o mapa.
- [x] Mapa global de infraestrutura: MapLibre usa os mosaicos vetoriais globais do Martin; Leaflet busca a janela visível por camada, sem limitar aos primeiros 10.000 registros. Os níveis de zoom seguem o mapa original para não solicitar centenas de milhares de feições na visão inicial; carregamento de centrais e antenas conferido no navegador.
- [x] Mapa global: vias e centrais poligonais usam as fontes originais no MapLibre e a janela espacial no Leaflet; vias recebem as cores de congestionamento do original, com verde para valores desconhecidos. Os endpoints reais de vias e centrais responderam no PostgreSQL e os dois mapas renderizaram no navegador.
- [x] Mapa global: construções OSM do banco original carregadas por janela espacial em uma camada `BuildingLayer` do GeoVeil 2, com extrusão e base em MapLibre a partir do zoom 14 e controle de visibilidade nas camadas. Consulta real retornou 65 construções na janela testada; compilação e 100 testes passaram.
- [x] Mapa global: aba independente «Visualizador 3D» com o motor Three.js do CCDAE original, alimentado pelo banco por distrito. A consulta inclui cabos ligados a postes ou centrais do distrito; no distrito 77 retornou 434 postes e 892 cabos. Alternância entre mapa e maquete, construções OSM e temas claro/escuro conferidos no navegador.
- [x] Visualizador 3D: relevo Terrarium habilitado por padrão com URL, zoom, exagero e atribuição iguais aos do original; legenda detalhada da maquete. O relevo foi conferido no navegador com dados reais do distrito 77.
- [ ] Visualizador 3D: comparar lado a lado a maquete com o original autenticado, inclusive ferramenta de seleção, aparência e desempenho em distritos densos. A aba disponível do original ainda está na tela de login.
- [ ] Mapa global: comparar visualmente a paleta/altura das construções, a aparência das centrais poligonais onde houver dados e a densidade visual em zoom alto.
- [ ] Energia e internet: comparar visualmente os modelos 3D em aproximação alta com o original e otimizar a renderização Leaflet em áreas com mais de 100 mil feições.
- [ ] Comparação visual 1:1 dos três painéis autenticados no CCDAE original.

## Administração e monitoramento
- [x] Histórico de atividades: listagem paginada, busca por evento/modelo, filtro de canal e detalhes das propriedades; acesso exclusivo à conta master.
- [x] Histórico de atividades: hora e data, nome do usuário, cores dos eventos, ordenação por horário e seções Antes/Depois alinhadas à tabela e ficha originais.
- [x] Histórico de atividades: exclusão em lote apenas pela conta master, com seleção validada e confirmação.
- [x] Saúde do sistema: banco, fila, memória, disco, últimas leituras, alertas, eventos dos coletores e gráfico de sete dias; consultas lidas no banco real sem alterar dados.
- [x] Saúde do sistema: verificar catálogo Martin, fontes e latência; limpar fila por ação explícita da conta master, com confirmação.
- [x] Saúde do sistema lê o sinal de vida do worker original no cache compartilhado e mostra seu estado; os controles Iniciar/Parar reproduzem as instruções do original, sem criar processos duplicados.
- [x] Ação Executar coleta enfileira os jobs de clima e trânsito no PostgreSQL compartilhado para execução pelo worker original; mantém a unicidade da coleta de trânsito pelo cache compartilhado e exige conta master. O recode ainda depende do worker e da implementação dos coletores no CCDAE original.
- [x] Histórico de atividades atualiza linhas e paginação a cada 2 segundos quando a página está visível; pausa durante seleção para exclusão.
- [x] Coluna opcional "ID do Registro" do histórico pode ser alternada e permanece visível após a atualização automática; o seletor reutilizável vive no pacote GeoVeil 2.
- [ ] Comparação visual autenticada do histórico de atividades.
