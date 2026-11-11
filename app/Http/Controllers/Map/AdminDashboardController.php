<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, SystemHealthReport};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function __invoke(MapData $data, SystemHealthReport $health)
    {
        $climateCount = Cache::get('admin-dashboard-climate-count');
        $stats = [
            'Distritos Cobertos' => [Distrito::query()->count(), 'Distritos urbanos ativos'],
            'Registros de Clima' => [$climateCount, 'Temperaturas, ventos, chuva, etc.'],
            'Fila de Processamento' => [Schema::hasTable('jobs') ? DB::table('jobs')->count() : null, 'Jobs pendentes na fila'],
            'Erros da Fila' => [Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null, 'Jobs falhados no banco'],
        ];
        $collection = $health->get()['collection'];
        return view('maps.admin-dashboard', compact('stats', 'collection'));
    }

    public function climateCount(MapData $data)
    {
        $total = Cache::remember('admin-dashboard-climate-count', 60, function () use ($data) {
            $total = 0;
            foreach (['temperatura_registrada', 'vento_registrado', 'chuva_registrada',
                'pressao_atmosferica', 'radiacao_solar', 'indice_uv', 'particulas_ar'] as $name) {
                $table = $data->table('climate', $name);
                if ($data->exists($table)) $total += DB::table($table)->count();
            }
            return $total;
        });
        return response()->json(['count' => $total]);
    }
}
