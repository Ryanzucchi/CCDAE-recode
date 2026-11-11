<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use GeoVeil\Filters\SelectFilter;
use GeoVeil\Layers\PolygonLayer;
use GeoVeil\Layers\SurfaceLayer;
use GeoVeil\Map;
use GeoVeil\Plugins\Leaflet;
use GeoVeil\Plugins\MapLibre;
use GeoVeil\Widgets\BarChart;
use GeoVeil\Widgets\Stat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DistrictMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['engine' => 'nullable|in:maplibre,leaflet']);
        $map = Map::make('districts-map')
            ->plugin($request->query('engine') === 'leaflet' ? Leaflet::make() : MapLibre::make())
            ->title('Distribuição populacional')->theme('dark')->layout('map')
            ->center(-57.681, -16.071)
            ->zoom(12)
            ->layers([
                ($request->query('engine') === 'leaflet'
                    ? PolygonLayer::make('distritos')->colorBy('populacao', [500, 1000, 1500, 2000, 2500, 3000], ['#440154', '#443983', '#31688e', '#21918c', '#35b779', '#90d743', '#fde725'])
                    : SurfaceLayer::make('distritos')->value('populacao')->altitude(1850)->influence(0.005))
                    ->label($request->query('engine') === 'leaflet' ? 'Distritos e população' : 'População')
                    ->source(Distrito::query())
                    ->geometry('geojson')
                    ->properties(['nome', 'cidade', 'populacao'])
                    ->popup(['nome' => 'Distrito', 'cidade' => 'Cidade', 'populacao' => 'População']),
            ])
            ->filters([
                SelectFilter::make('cidade')->label('Cidade')->layer('distritos'),
            ])
            ->widgets([
                Stat::make('Distritos')->count('distritos'),
                Stat::make('População')->sum('distritos', 'populacao'),
                BarChart::make('População por distrito')->source('distritos', 'nome', 'populacao'),
            ]);

        if (config('geoveil.geocoder_endpoint')) {
            $map->geocoder(route('maps.geocode'));
        }

        return view('maps.districts', compact('map'));
    }
}
