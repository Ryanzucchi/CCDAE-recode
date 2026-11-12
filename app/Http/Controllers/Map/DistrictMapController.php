<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\DistrictMetricCells;
use GeoVeil\Filters\SelectFilter;
use GeoVeil\Layers\PolygonLayer;
use GeoVeil\Layers\SurfaceLayer;
use GeoVeil\Map;
use GeoVeil\Plugins\Leaflet;
use GeoVeil\Plugins\MapLibre;
use GeoVeil\Sources\GeoJson;
use GeoVeil\Widgets\BarChart;
use GeoVeil\Widgets\Stat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DistrictMapController extends Controller
{
    public function __invoke(Request $request, DistrictMetricCells $metrics): View
    {
        $request->validate(['engine' => 'nullable|in:maplibre,leaflet']);
        $original = $request->routeIs('original-admin.map-population');
        $leaflet = $request->query('engine') === 'leaflet';
        $districtIds = $leaflet ? Distrito::query()->orderBy('id')->pluck('id')->all() : [];
        $districtColors = array_map(fn ($index) => $this->districtColor($index), range(0, max(1, count($districtIds) - 1)));
        $sampled = null;
        if ($original && !$leaflet) {
            $cells = $metrics->cells('populacao');
            $sampled = GeoJson::make(['type' => 'FeatureCollection', 'features' => array_map(
                fn (array $cell, int $index) => [
                    'type' => 'Feature', 'id' => $index + 1,
                    'geometry' => ['type' => 'Point', 'coordinates' => [$cell['lng'], $cell['lat']]],
                    'properties' => ['name' => $cell['name'], 'value' => $cell['value']],
                ], $cells, array_keys($cells),
            )]);
        }
        $map = Map::make('districts-map')
            ->plugin($leaflet ? Leaflet::make() : MapLibre::make())
            ->title('Distribuição populacional')->theme('dark')->layout($original ? 'embedded' : 'map')
            ->center(-57.681, -16.071)
            ->zoom(12)
            ->fitOnLoad($original)
            ->layers([
                ($leaflet
                    ? PolygonLayer::make('distritos')->colorByCategory('id', $districtColors, $districtIds)
                    : SurfaceLayer::make('distritos')->value($sampled ? 'value' : 'populacao')->altitude(1850)->influence(0.005))
                    ->label($leaflet ? 'Distritos e população' : 'População')
                    ->source($sampled ?? Distrito::query())
                    ->geometry('geojson')
                    ->properties(['id', 'nome', 'cidade', 'populacao'])
                    ->popup($sampled ? ['name' => 'Distrito', 'value' => 'População'] : ['nome' => 'Distrito', 'cidade' => 'Cidade', 'populacao' => 'População']),
            ]);
        if (!$original) $map->filters([
                SelectFilter::make('cidade')->label('Cidade')->layer('distritos'),
            ])
            ->widgets([
                Stat::make('Distritos')->count('distritos'),
                Stat::make('População')->sum('distritos', 'populacao'),
                BarChart::make('População por distrito')->source('distritos', 'nome', 'populacao'),
            ]);

        if (!$original && config('geoveil.geocoder_endpoint')) {
            $map->geocoder(route('maps.geocode'));
        }

        return view('maps.districts', compact('map'));
    }

    private function districtColor(int $index): string
    {
        $hue = fmod($index * 137.508, 360) / 60;
        $chroma = 0.75 * 0.86;
        $secondary = $chroma * (1 - abs(fmod($hue, 2) - 1));
        $offset = 0.86 - $chroma;
        [$red, $green, $blue] = match ((int) floor($hue)) {
            0 => [$chroma, $secondary, 0],
            1 => [$secondary, $chroma, 0],
            2 => [0, $chroma, $secondary],
            3 => [0, $secondary, $chroma],
            4 => [$secondary, 0, $chroma],
            default => [$chroma, 0, $secondary],
        };
        return sprintf('#%02x%02x%02x', round(($red + $offset) * 255), round(($green + $offset) * 255), round(($blue + $offset) * 255));
    }
}
