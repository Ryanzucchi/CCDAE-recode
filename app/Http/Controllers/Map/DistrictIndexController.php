<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\DistrictMapFeatures;
use GeoVeil\Layers\{PointLayer, PolygonLayer};
use GeoVeil\Map;
use GeoVeil\Plugins\Leaflet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DistrictIndexController extends Controller
{
    public function __invoke(Request $request, DistrictMapFeatures $features): View
    {
        $input = $request->validate(['q' => 'nullable|string|max:120', 'cidade' => 'nullable|string|max:255',
            'sort' => 'nullable|in:nome,latitude,longitude,cidade,created_at,updated_at', 'direction' => 'nullable|in:asc,desc']);
        $search = trim($input['q'] ?? '');
        $city = trim($input['cidade'] ?? '');
        $sort = $input['sort'] ?? 'nome';
        $direction = $input['direction'] ?? 'asc';
        $cities = Distrito::query()->whereNotNull('cidade')->distinct()->orderBy('cidade')->pluck('cidade');
        $districts = Distrito::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('nome', '%'.$search.'%')->orWhereLike('cidade', '%'.$search.'%')))
            ->orderBy($sort, $direction)->orderBy('id')->paginate(15)->withQueryString();
        $mapDistricts = Distrito::query()->when($city === '', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($city !== '', fn ($query) => $query->where('cidade', $city))
            ->orderBy('id')->get(['id', 'nome', 'cidade', 'populacao', 'latitude', 'longitude', 'geojson']);
        [$polygonData, $pointData] = $features->build($mapDistricts);
        $colors = ['#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444'];
        $colorOrder = $mapDistricts->pluck('id')->all();
        $polygonLayer = PolygonLayer::make('distritos')->label('Fronteiras dos distritos')
                ->source($polygonData)
                ->popup(['nome' => 'Distrito', 'cidade' => 'Cidade', 'populacao' => 'População'])
                ->colorByCategory('id', $colors, $colorOrder);
        $pointLayer = PointLayer::make('distritos-sem-area')->label('Distritos sem área')
                    ->source($pointData)->popup(['nome' => 'Distrito', 'cidade' => 'Cidade'])
                    ->colorByCategory('id', $colors, $colorOrder);
        if ($request->user()?->email === config('ccdae.master_email')) {
            $actionUrl = route('districts.index', [], false).'/{id}/editar';
            $polygonLayer->recordAction('Editar Distrito', $actionUrl, true);
            $pointLayer->recordAction('Editar Distrito', $actionUrl, true);
        }
        $map = Map::make('district-index-map')->plugin(Leaflet::make())->title('Mapa de distritos')
            ->theme('dark')->layout('embedded')->center(-57.681, -16.071)->zoom(12)->fitOnLoad()->fitOnSelect(false)
            ->layers([$polygonLayer, $pointLayer]);

        return view('maps.district-index', compact('districts', 'search', 'city', 'cities', 'map', 'sort', 'direction'));
    }
}
