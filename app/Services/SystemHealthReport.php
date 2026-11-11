<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthReport
{
    public function __construct(private readonly MapData $data) {}

    public function get(): array
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $latency = round((microtime(true) - $start) * 1000, 2);
        $driver = DB::connection()->getDriverName();
        $size = $driver === 'pgsql' ? (DB::selectOne('SELECT pg_size_pretty(pg_database_size(current_database())) AS size')->size ?? '—') : 'Banco de testes';
        $latest = [];
        foreach (['Clima' => ['climate', 'temperatura_registrada'], 'Trânsito' => ['mobility', 'registro_transitos']] as $label => [$schema, $tableName]) {
            $table = $this->data->table($schema, $tableName);
            $latest[$label] = $this->data->exists($table) ? DB::table($table)->orderByDesc('timestamp')->value('timestamp') : null;
        }
        $queue = [];
        foreach (['jobs' => 'Jobs pendentes', 'failed_jobs' => 'Jobs falhados'] as $table => $label) {
            $queue[$label] = Schema::hasTable($table) ? DB::table($table)->count() : null;
        }
        $worker = ['running' => false, 'text' => 'Nenhum sinal do Queue Worker.'];
        if (Schema::hasTable('cache')) {
            $signal = DB::table('cache')
                ->where('key', config('ccdae.original_cache_prefix').'queue_worker_last_seen')
                ->where('expiration', '>', now()->timestamp)->value('value');
            if (is_string($signal) && preg_match('/^i:([0-9]+);$/', $signal, $matches)) {
                $seconds = max(0, now()->timestamp - (int) $matches[1]);
                $worker = ['running' => $seconds <= 600,
                    'text' => ($seconds <= 600 ? 'Ativo' : 'Inativo')." (último sinal: há {$seconds}s)"];
            }
        }
        $total = @disk_total_space('/'); $free = @disk_free_space('/');
        $diskPercent = $total && $free !== false ? round(100 * (1 - $free / $total), 1) : null;
        $memoryLimit = ini_get('memory_limit');
        $memoryMb = round(memory_get_usage(true) / 1048576, 2);
        $alerts = [];
        $martin = ['online' => false, 'latency' => null, 'sources' => 0];
        try {
            $startMartin = microtime(true);
            $response = Http::timeout(2)->acceptJson()->get(rtrim(config('geoveil.martin_url'), '/').'/catalog');
            $martin['online'] = $response->successful();
            if ($martin['online']) {
                $martin['latency'] = round((microtime(true) - $startMartin) * 1000, 2);
                $martin['sources'] = count($response->json('tiles', []));
            }
        } catch (\Throwable) {}
        if (! $martin['online']) $alerts[] = 'Martin indisponível. Os mapas vetoriais podem não aparecer.';
        elseif ($martin['latency'] > 500) $alerts[] = 'Latência alta no catálogo Martin.';
        if ($latency > 150) $alerts[] = 'Latência alta do banco de dados.';
        if (($queue['Jobs falhados'] ?? 0) > 0) $alerts[] = 'Existem jobs falhados na fila.';
        if (($queue['Jobs pendentes'] ?? 0) > 50) $alerts[] = 'Há mais de 50 jobs pendentes.';
        if (! $worker['running']) $alerts[] = 'Nenhum sinal recente do Queue Worker do CCDAE original.';
        if ($diskPercent !== null && $diskPercent > 85) $alerts[] = 'Espaço em disco acima de 85% de uso.';
        foreach ($latest as $label => $stamp) {
            if ($stamp && \Carbon\Carbon::parse($stamp)->lt(now()->subHours(2))) $alerts[] = "Última leitura de $label há mais de duas horas.";
        }
        $collection = [];
        $climate = $this->data->table('climate', 'temperatura_registrada');
        $daily = $this->data->exists($climate) ? DB::table($climate)
            ->where('timestamp', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(timestamp) as day, COUNT(*) as total')
            ->groupByRaw('DATE(timestamp)')->pluck('total', 'day')->all() : [];
        for ($days = 6; $days >= 0; $days--) {
            $day = now()->subDays($days);
            $collection[] = ['label' => $day->format('d/m'), 'count' => 7 * (int) ($daily[$day->format('Y-m-d')] ?? 0)];
        }
        return compact('latency', 'driver', 'size', 'latest', 'queue', 'worker', 'diskPercent', 'memoryLimit', 'memoryMb', 'alerts', 'collection', 'martin');
    }
}
