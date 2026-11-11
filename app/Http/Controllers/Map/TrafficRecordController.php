<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Services\{MapData, TrafficRecordResource};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrafficRecordController extends Controller
{
    public static function recordKey(int $viaId, string $stamp): string
    {
        return $viaId.'-'.Carbon::parse($stamp)->format('Ymd\THis');
    }

    public function index(Request $request, MapData $data, TrafficRecordResource $catalog)
    {
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'sort' => ['nullable', Rule::in(['via_nome', 'timestamp', 'veiculos_total', 'velocidade_media', 'altura_media_veiculos', 'altura_maxima_veiculos'])],
            'direction' => 'nullable|in:asc,desc',
        ]);
        $resource = $catalog->get();
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $sort = $input['sort'] ?? 'timestamp';
        $direction = $input['direction'] ?? 'desc';
        $items = DB::table($data->table('mobility', 'registro_transitos').' as r')
            ->leftJoin($data->table('mobility', 'via_transitos').' as v', 'v.id', '=', 'r.via_transito_id')
            ->when($input['q'] ?? null, fn ($query, $q) => $query->where('v.nome', $operator, '%'.$q.'%'))
            ->select('r.*', 'v.nome as via_nome')
            ->orderBy($sort === 'via_nome' ? 'v.nome' : 'r.'.$sort, $direction)
            ->orderByDesc('r.timestamp')->orderBy('r.via_transito_id')
            ->simplePaginate(20)->withQueryString();
        return view('maps.traffic-records', compact('resource', 'items', 'sort', 'direction'));
    }

    public function show(Request $request, MapData $data)
    {
        [$viaId, $stamp] = $this->key($request);
        $record = DB::table($data->table('mobility', 'registro_transitos').' as r')
            ->leftJoin($data->table('mobility', 'via_transitos').' as v', 'v.id', '=', 'r.via_transito_id')
            ->where('r.via_transito_id', $viaId)->where('r.timestamp', $stamp)
            ->select('r.*', 'v.nome as via_nome')->first();
        abort_unless($record, 404);
        return view('maps.traffic-record-show', compact('record'));
    }

    public function create(TrafficRecordResource $catalog)
    {
        $resource = $catalog->get(); $record = null; $values = [];
        return view('maps.traffic-record-form', compact('resource', 'record', 'values'));
    }

    public function edit(Request $request, MapData $data, TrafficRecordResource $catalog)
    {
        [$viaId, $stamp] = $this->key($request);
        $record = DB::table($data->table('mobility', 'registro_transitos'))->where('via_transito_id', $viaId)->where('timestamp', $stamp)->first();
        abort_unless($record, 404);
        $resource = $catalog->get($record);
        $values = (array) $record;
        $values['timestamp'] = Carbon::parse($record->timestamp)->format('Y-m-d\TH:i:s');
        return view('maps.traffic-record-form', compact('resource', 'record', 'values'));
    }

    public function store(Request $request, MapData $data)
    {
        $input = $this->validated($request, $data);
        $table = $data->table('mobility', 'registro_transitos');
        $this->assertAvailable($table, (int) $input['via_transito_id'], $input['timestamp']);
        DB::table($table)->insert($input);
        return redirect()->route($request->routeIs('original-traffic.records.*') ? 'original-traffic.records.edit' : 'traffic.records.edit',
            $request->routeIs('original-traffic.records.*') ? ['record' => self::recordKey((int) $input['via_transito_id'], $input['timestamp'])] : ['via_id' => $input['via_transito_id'], 'at' => $input['timestamp']])->with('status', 'Leitura salva.');
    }

    public function update(Request $request, MapData $data)
    {
        [$viaId, $stamp] = $this->key($request);
        $table = $data->table('mobility', 'registro_transitos');
        abort_unless(DB::table($table)->where('via_transito_id', $viaId)->where('timestamp', $stamp)->exists(), 404);
        $input = $this->validated($request, $data);
        if ($viaId !== (int) $input['via_transito_id'] || $stamp !== $input['timestamp']) $this->assertAvailable($table, (int) $input['via_transito_id'], $input['timestamp']);
        DB::table($table)->where('via_transito_id', $viaId)->where('timestamp', $stamp)->update($input);
        return redirect()->route($request->routeIs('original-traffic.records.*') ? 'original-traffic.records.edit' : 'traffic.records.edit',
            $request->routeIs('original-traffic.records.*') ? ['record' => self::recordKey((int) $input['via_transito_id'], $input['timestamp'])] : ['via_id' => $input['via_transito_id'], 'at' => $input['timestamp']])->with('status', 'Leitura salva.');
    }

    public function destroyBatch(Request $request, MapData $data)
    {
        $input = $request->validate([
            'selected' => 'required|array|min:1|max:20',
            'selected.*' => ['required', 'string', 'regex:/^[1-9][0-9]*\|[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/'],
        ]);
        $keys = array_map(function (string $value) {
            [$viaId, $stamp] = explode('|', $value, 2);
            validator(['at' => $stamp], ['at' => 'date_format:Y-m-d H:i:s'])->validate();
            return [(int) $viaId, $stamp];
        }, $input['selected']);
        if (count(array_unique($input['selected'])) !== count($input['selected'])) {
            throw ValidationException::withMessages(['selected' => 'Selecione cada leitura uma vez.']);
        }
        $deleted = DB::transaction(function () use ($keys, $data) {
            $table = $data->table('mobility', 'registro_transitos');
            $count = 0;
            foreach ($keys as [$viaId, $stamp]) {
                $count += DB::table($table)->where('via_transito_id', $viaId)->where('timestamp', $stamp)->delete();
            }
            return $count;
        });
        return redirect()->route($request->routeIs('original-traffic.records.*') ? 'original-traffic.records.index' : 'traffic.records.index')->with('status', "$deleted leituras excluídas.");
    }

    public function destroy(Request $request, MapData $data)
    {
        [$viaId, $stamp] = $this->key($request);
        $deleted = DB::table($data->table('mobility', 'registro_transitos'))
            ->where('via_transito_id', $viaId)->where('timestamp', $stamp)->delete();
        abort_unless($deleted, 404);
        return redirect()->route($request->routeIs('original-traffic.records.*') ? 'original-traffic.records.index' : 'traffic.records.index')->with('status', 'Leitura excluída.');
    }

    private function key(Request $request): array
    {
        if ($record = $request->route('record')) {
            [$viaId, $compact] = explode('-', $record, 2);
            $stamp = Carbon::createFromFormat('!Ymd\THis', $compact);
            abort_unless($stamp && $stamp->format('Ymd\THis') === $compact, 404);
            return [(int) $viaId, $stamp->format('Y-m-d H:i:s')];
        }
        $input = validator($request->query(), ['via_id' => 'required|integer|min:1', 'at' => 'required|date_format:Y-m-d H:i:s'])->validate();
        return [(int) $input['via_id'], $input['at']];
    }

    private function validated(Request $request, MapData $data): array
    {
        $input = $request->validate([
            'via_transito_id' => ['required', 'integer', Rule::exists($data->table('mobility', 'via_transitos'), 'id')],
            'timestamp' => 'required|date', 'veiculos_total' => 'nullable|integer|min:0',
            'velocidade_media' => 'nullable|numeric|min:0', 'altura_media_veiculos' => 'nullable|numeric|min:0',
            'altura_maxima_veiculos' => 'nullable|numeric|min:0', 'indice_congestionamento' => 'nullable|integer|min:0',
            'nivel_servico' => 'nullable|string|max:255',
        ]);
        $input['timestamp'] = Carbon::parse($input['timestamp'])->format('Y-m-d H:i:s');
        return $input;
    }

    private function assertAvailable(string $table, int $viaId, string $stamp): void
    {
        if (DB::table($table)->where('via_transito_id', $viaId)->where('timestamp', $stamp)->exists()) throw ValidationException::withMessages(['timestamp' => 'Já existe uma leitura desta via nesse horário.']);
    }
}
