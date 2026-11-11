# GeoVeil 2

Pacote Laravel + frontend para declarar uma área de trabalho geoespacial em PHP. Implementação nova, sem dependência do GeoVeil 1, Filament, Livewire ou Alpine. O CCDAE recode é a primeira aplicação consumidora.

## Uso

```php
use GeoVeil\Map;
use GeoVeil\Plugins\MapLibre;
use GeoVeil\Layers\PolygonLayer;
use GeoVeil\Filters\SelectFilter;
use GeoVeil\Widgets\{Stat, BarChart};

$map = Map::make('territorio')
    ->plugin(MapLibre::make())
    ->title('População por distrito')
    ->center(-57.681, -16.071)
    ->zoom(12)
    ->layers([
        PolygonLayer::make('distritos')
            ->label('Distritos')
            ->source(Distrito::query())
            ->geometry('geojson')
            ->properties(['nome', 'cidade', 'populacao'])
            ->colorBy('populacao', [10000, 20000, 30000], [
                '#ccfbf1', '#5eead4', '#14b8a6', '#0f766e',
            ])
            ->popup(['nome' => 'Distrito', 'populacao' => 'População']),
    ])
    ->filters([
        SelectFilter::make('cidade')->label('Cidade')->layer('distritos'),
    ])
    ->widgets([
        Stat::make('População')->sum('distritos', 'populacao'),
        Stat::make('Distritos')->count('distritos'),
        BarChart::make('Comparação')->source('distritos', 'nome', 'populacao'),
    ]);
```

```blade
<x-geoveil::map :map="$map" />
```

```js
import { boot } from '@geoveil/core';
boot();
```

O frontend importa seu CSS e os estilos dos motores. O adaptador MapLibre inclui seu worker usando o mecanismo de assets do Vite. A aplicação deve carregar esse entrypoint com Vite. Não existe etapa manual de copiar arquivos compilados para `public/vendor`.

## Instalar em outro projeto

Este pacote ainda é local, não foi publicado em registros públicos. Requer PHP 8.2+ e Laravel 12.

1. Disponibilize esta pasta no novo projeto, por exemplo em `packages/geoveil2`.
2. Adicione ao `composer.json` um repositório `{"type":"path","url":"packages/geoveil2"}`.
3. Execute `composer require geoveil/geoveil:2.0.0-dev`.
4. Execute `npm install ./packages/geoveil2`.
5. Importe `boot` no entrypoint, adicione o componente Blade e compile os assets.

O ServiceProvider é descoberto pelo Composer. O pacote não instala tabelas, não registra rotas públicas e não escolhe modelos, conexões ou permissões. Isso pertence à aplicação consumidora.

## Motores e capacidades

| Recurso | MapLibre | Leaflet |
|---|---|---|
| PolygonLayer | Sim | Sim |
| PointLayer | Sim | Sim |
| LineLayer | Sim | Sim |
| HeatmapLayer | Sim | Não |
| BuildingLayer | Sim | Não |
| SurfaceLayer (superfície interpolada 3D) | Sim | Não |
| Filtros, indicadores, barras e séries | Sim | Sim |

Troque apenas `->plugin(Leaflet::make())`. Camadas incompatíveis geram uma exceção em PHP. Não há fallback silencioso.

`MapLibre::make()->tiles($templateUrl, $attribution)` e a mesma API no Leaflet configuram uma base raster. O padrão usa tiles OSM; configure infraestrutura adequada ao volume da aplicação. Estilos vetoriais completos e fontes MVT ainda não estão implementados.

Um novo motor implementa `GeoVeil\Contracts\MapPlugin` em PHP e registra seu adaptador JavaScript:

```js
import { registerPlugin, boot } from '@geoveil/core';
registerPlugin('meu-motor', async (container, config, select, report) => {
    // Retorne update(arraysDeFeatures), fit(features), destroy().
    // select(layerId, featureId) sincroniza o painel.
    // report(message) apresenta erros visíveis.
    return meuAdaptador;
});
boot();
```

## Dados e interação

- Fontes implementadas: consulta Eloquent e `GeoJson::make($featureCollection)`.
- `geometry('geojson')` lê Polygon, MultiPolygon, LineString etc. Pontos também podem usar `coordinates('longitude', 'latitude')`.
- `properties([...])` é uma lista explícita de campos enviados ao navegador. Campos de `popup`, escala e altura são incluídos automaticamente. Inclua também os campos usados por filtros e widgets.
- IDs de mapas e camadas devem ser únicos em seu escopo; `base` é reservado. IDs de features devem ser únicos dentro de cada camada.
- Fontes Eloquent são clonadas: o pacote preserva a consulta original e seu limite. Mais de 10.000 registros inline geram erro, sem truncamento silencioso. Grandes volumes exigirão fontes remotas/MVT.
- Busca textual e filtros atuam no conjunto já carregado. Não substituem autorização nem filtragem no servidor.
- `popup([...])` define os atributos mostrados no painel de detalhes ao selecionar uma feature.
- Busca, filtros e visibilidade atualizam mapa, lista, gráficos e indicadores. Seleção atualiza detalhes e indicadores e enquadra a geometria. As barras mantêm o contexto da comparação.
- Os eventos DOM `geoveil:select` e `geoveil:geocode` permitem integração com a aplicação.
- `mount(element, config)` também é exportado; o retorno possui `destroy()` para limpar recursos em navegações geridas pela aplicação.
- Cor sem medição: cinza. Ausência de valor não vira uma classe populacional válida.

## Séries temporais

Use `DateRangeFilter::make('timestamp')->layer('medicoes')` e `LineChart::make('Temperatura')->source('medicoes', 'timestamp', 'valor')`. Inclua `timestamp` e `valor` nas propriedades da camada. Cada feature é uma observação com ID próprio. Datas devem incluir fuso horário; filtros por dia usam UTC e incluem o último dia inteiro.

O gráfico ordena observações pelo tempo, ignora valores ausentes e fornece uma lista acessível das primeiras 50 medições. Não agrega estações automaticamente: filtre a estação ou forneça uma série já agregada no backend. Timeline com reprodução ainda não foi implementada.

## Geocodificação

`Map::geocoder($endpointDaAplicacao)` habilita busca explícita por lugar/endereço. O endpoint recebe `q` e devolve:

```json
{"results":[{"label":"Lugar","longitude":-57.68,"latitude":-16.07}]}
```

O contrato `GeoVeil\Contracts\Geocoder` permite trocar o provedor. O adaptador `Geocoding\Nominatim` aceita endpoint e User-Agent, usa timeout, cache de 24 horas e limite global de uma chamada a cada dois segundos. Não faz autocomplete.

O CCDAE oferece `/mapas/localizar`, com validação e limite por cliente. Configure `GEOVEIL_GEOCODER_ENDPOINT` e `GEOVEIL_GEOCODER_USER_AGENT` para habilitar a busca na tela. Não há provedor público habilitado automaticamente. Em várias instâncias, use cache compartilhado para compartilhar o limite global.

Se optar deliberadamente pelo serviço público Nominatim, leia sua [política](https://operations.osmfoundation.org/policies/nominatim/): limite global de 1 requisição/segundo, identificação da aplicação, atribuição, cache e proibição de autocomplete/consultas sistemáticas. Verifique adequação do uso e das licenças. Instância própria ou provedor contratado pode ser configurado sem mudar o pacote.

## Escopo atual e validação

É a primeira versão funcional do remake, não uma implementação completa de todas as funcionalidades do CCDAE. Busca local, filtros, seleção, legenda, barras e indicadores foram exercitados no navegador com MapLibre e Leaflet e dados sintéticos. Os testes PHP cobrem contratos e integração Laravel; testes JS cobrem filtros, limites temporais, escalas, totais e séries. Geocodificação foi testada com HTTP simulado. A série temporal também foi conferida no navegador com recorte de datas e mapa sincronizado. Heatmap e extrusão 3D ainda precisam de cenários próprios de validação no navegador antes de uso operacional.

Pendências: desenho/edição e persistência de geometrias, geocodificação reversa, clustering, ícones, fontes remotas, MVT/Martin, timeline, busca remota em selects de grandes tabelas, ações genéricas de recurso e migração completa dos módulos de clima, infraestrutura, energia, internet e trânsito.

Documentação das APIs dos motores: [MapLibre](https://maplibre.org/maplibre-gl-js/docs/API/classes/Map/) e [Leaflet 1.9](https://leafletjs.com/reference-1.9.4.html).

## Superfície 3D e layout de mapa

```php
use GeoVeil\Layers\SurfaceLayer;

$map = Map::make('populacao')
    ->plugin(MapLibre::make())
    ->theme('dark')
    ->layout('map')
    ->layers([
        SurfaceLayer::make('distritos')
            ->label('População')
            ->source(Distrito::query())
            ->geometry('geojson')
            ->value('populacao')
            ->altitude(1850)
            ->influence(0.005)
            ->popup(['nome' => 'Distrito', 'populacao' => 'População']),
    ]);
```

A camada aceita polígonos, multipolígonos ou pontos. Polígonos são amostrados numa grade de até 8 × 8 pontos, respeitando seus buracos. Os valores são normalizados, interpolados e renderizados numa malha 3D com dez faixas de cores. A interpolação e a escala de altura foram adaptadas do CCDAE original, separadas das rotas e regras da aplicação. `influence` é expresso em graus; `altitude` controla a altura visual máxima em metros. A superfície é um modelo de distribuição, não uma medição de relevo.

O controle vertical corta a superfície por intensidade relativa, com presets Tudo, 50% e Pico. A legenda acompanha os filtros e o corte. Selecionar uma amostra na superfície abre os detalhes do distrito. Os indicadores continuam agregando distritos, não as amostras da malha.

Superfícies requerem MapLibre; Leaflet rejeita essa capacidade explicitamente. Na aplicação CCDAE, a opção “Distritos 2D” escolhe uma camada de polígonos no Leaflet como visualização alternativa.

`layout('map')` prioriza o mapa e abre filtros/indicadores em painéis sobrepostos. `layout('workspace')` mantém as três colunas. `theme('dark')` ou `theme('light')` definem as cores do painel e da base cartográfica.

### Trocar tema sem reconstruir o mapa

O objeto retornado por `mount()` (também disponível em `element.geoveil`) oferece `setTheme('light')` e `setTheme('dark')`. A troca preserva câmera, seleção, filtros e corte de intensidade. MapLibre atualiza a base raster; Leaflet atualiza somente o estilo dos tiles. As cores dos dados permanecem as mesmas nos dois temas.

`boot(document, { theme: 'light' })` permite aplicar uma preferência da aplicação na inicialização. No CCDAE, o seletor do cabeçalho salva essa preferência em `localStorage`, aplica-a antes da primeira pintura e sincroniza abas abertas.

## Formulários geoespaciais

O pacote também oferece `GeoVeil\Forms\Form` e `Field` para compor seções e um seletor de coordenadas sobre Leaflet. A aplicação define persistência, autorização e validação no servidor.

```php
use GeoVeil\Forms\{Form, Field};

$form = Form::make('ocorrencia')
    ->section('Dados', [
        Field::text('tipo')->label('Tipo')->required(),
        Field::datetime('ocorrido_em')->label('Data e hora')->required(),
        Field::select('situacao', ['registrada' => 'Registrada'])->label('Situação'),
    ])
    ->section('Localização', [Field::point()->label('Local da ocorrência')->required()]);
```

```blade
<x-geoveil::form :form="$form" :values="$values" :action="$url" method="POST" />
```

O campo `point` escreve `latitude` e `longitude` no formulário e recebe essas mesmas chaves em `values` ao editar. O consumidor deve validar ambas as coordenadas e os campos permitidos antes de gravar. A aplicação CCDAE já usa esse componente para incidentes de energia e internet e infrações de trânsito.

## Recursos administrativos reutilizáveis

`GeoVeil\Resources\Resource` descreve título, tabela, formulário e colunas de um cadastro. O CCDAE usa esse contrato para postes e antenas; um mesmo controlador apresenta as listagens e editores.

```php
$resource = Resource::make('postes', 'Postes', 'infrastructure.postes')
    ->form($form)
    ->columns(['codigo_patrimonio' => 'Patrimônio', 'material' => 'Material']);
```

O pacote oferece a descrição do recurso; a aplicação consumidora continua responsável por rotas, consultas, validação, permissões e escrita no banco.

### Geometria editável

`Field::geometry()` adiciona ao formulário um mapa para marcar ponto ou desenhar polígono. O componente escreve `latitude`, `longitude` e `geojson`; no caso do polígono, a aplicação deve validar o GeoJSON e calcular o centro no servidor. O CCDAE usa esse campo no cadastro de centrais de distribuição.

`Field::line()` usa o mesmo editor para desenhar `LineString`; o consumidor valida o GeoJSON e calcula a extensão no servidor. O cadastro de cabeamentos do CCDAE pode derivar o traçado dos postes/centrais conectados quando nenhuma linha manual é informada.

### Busca em relacionamentos grandes

`Field::remoteSelect('poste_id', $endpoint, $selecionado)` cria um seletor que consulta o servidor sob demanda. O endpoint retorna `{"results":[{"id":123,"label":"P-123 #123"}]}` e pode receber `q` ou `id`. O pacote mantém o ID como valor do formulário; o consumidor valida o vínculo no servidor. O CCDAE usa essa API para localizar postes, centrais, cabos, equipamentos, antenas e vias, sem cortar o catálogo nos primeiros mil registros.
