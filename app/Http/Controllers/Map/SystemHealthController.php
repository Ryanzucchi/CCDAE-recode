<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Jobs\CollectClimateData;
use App\Jobs\CollectTrafficData;
use App\Services\SystemHealthReport;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function __invoke(SystemHealthReport $health)
    {
        $stats = $health->get();
        $events = Schema::hasTable('activity_log') ? DB::table('activity_log')
            ->whereIn('log_name', ['climate_collection', 'traffic_collection'])
            ->orderByDesc('id')->limit(20)->get(['id', 'created_at', 'description', 'log_name']) : collect();
        return view('maps.system-health', compact('stats', 'events'));
    }

    public function clearQueue(Request $request)
    {
        abort_unless(Schema::hasTable('jobs'), 503);
        $deleted = DB::transaction(fn () => DB::table('jobs')->delete());
        return redirect()->route('system.health')->with('status', "$deleted jobs pendentes removidos da fila.");
    }

    public function runCollector()
    {
        abort_unless(Schema::hasTable('jobs') && config('queue.connections.database.driver') === 'database', 503);
        $trafficQueued = DB::transaction(function () {
            Queue::connection('database')->push(new CollectClimateData);
            $traffic = new CollectTrafficData;
            $unique = new UniqueLock(Cache::store('database'));
            if ($unique->acquire($traffic)) {
                try {
                    Queue::connection('database')->push($traffic);
                } catch (\Throwable $exception) {
                    $unique->release($traffic);
                    throw $exception;
                }
                return true;
            }
            return false;
        });
        $message = $trafficQueued
            ? 'Jobs de Coleta (Clima e Trânsito) disparados com sucesso!'
            : 'Coleta de clima disparada. A coleta de trânsito já está em andamento ou aguarda na fila.';
        return redirect()->route('system.health')->with('status', $message);
    }
}
