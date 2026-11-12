<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, OccurrenceListing};
use GeoVeil\Forms\{Field, Form};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrafficInfractionController extends Controller
{
    private const STATUSES = ['registrada' => 'Registrada', 'notificada' => 'Notificada', 'cancelada' => 'Cancelada', 'paga' => 'Paga'];

    public function index(Request $request, MapData $data, OccurrenceListing $listing)
    {
        return view('maps.occurrences', $listing->viewData('transito', $request, $data));
    }

    public function create(MapData $data) { return $this->editor($data, null); }

    public function edit(MapData $data, int $id)
    {
        $infraction = DB::table($data->table('mobility', 'infracoes_transito'))->where('id', $id)->first();
        abort_unless($infraction, 404);
        return $this->editor($data, $infraction);
    }

    private function editor(MapData $data, ?object $infraction)
    {
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        $roads = [];
        if ($infraction?->via_transito_id) $roads[$infraction->via_transito_id] = DB::table($data->table('mobility', 'via_transitos'))->where('id', $infraction->via_transito_id)->value('nome') ?? 'Via #'.$infraction->via_transito_id;
        $form = Form::make('traffic-infraction')->section('Dados da infração', [
            Field::text('tipo')->label('Tipo')->required()->maxLength(80),
            Field::text('codigo')->label('Código')->maxLength(40),
            Field::text('placa')->label('Placa')->maxLength(10),
            Field::datetime('ocorrido_em')->label('Data e hora')->required()->default(now()->format('Y-m-d\TH:i')),
            Field::remoteSelect('via_transito_id', route('resource.lookup', 'vias'), $roads)->label('Via'),
            Field::select('distrito_id', $districts)->label('Distrito')->searchable(),
            Field::number('valor')->label('Valor (R$)')->step('0.01')->min(0),
            Field::number('pontos')->label('Pontos')->step('1')->min(0)->max(20)->default(0),
            Field::select('situacao', self::STATUSES)->label('Situação')->required()->default('registrada'),
            Field::textarea('descricao')->label('Descrição')->maxLength(500)->rows(4),
        ])->section('Localização', [Field::point()->label('Local da infração')->required()], 'Marque no mapa o local exato da ocorrência.');
        $values = $infraction ? (array) $infraction : [];
        if ($infraction && $values['ocorrido_em']) $values['ocorrido_em'] = \Carbon\Carbon::parse($values['ocorrido_em'])->format('Y-m-d\TH:i');
        return view('maps.traffic-infraction-form', compact('form', 'values', 'infraction'));
    }

    public function store(Request $request, MapData $data) { return $this->save($request, $data); }

    public function update(Request $request, MapData $data, int $id)
    {
        abort_unless(DB::table($data->table('mobility', 'infracoes_transito'))->where('id', $id)->exists(), 404);
        return $this->save($request, $data, $id);
    }

    private function save(Request $request, MapData $data, ?int $id = null)
    {
        $input = $request->validate([
            'tipo' => 'required|string|max:80', 'codigo' => 'nullable|string|max:40', 'placa' => 'nullable|string|max:10',
            'ocorrido_em' => 'required|date',
            'via_transito_id' => ['nullable', 'integer', Rule::exists($data->table('mobility', 'via_transitos'), 'id')],
            'distrito_id' => ['nullable', 'integer', Rule::exists((new Distrito)->getTable(), 'id')],
            'valor' => 'nullable|numeric|min:0|max:99999999.99', 'pontos' => 'required|integer|between:0,20',
            'situacao' => ['required', Rule::in(array_keys(self::STATUSES))], 'descricao' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
        ]);
        $input['updated_at'] = now();
        $table = $data->table('mobility', 'infracoes_transito');
        if ($id) DB::table($table)->where('id', $id)->update($input);
        else {
            $input['created_at'] = now();
            $input['registrado_por'] = $request->user()->id;
            $id = DB::table($table)->insertGetId($input);
        }
        return redirect()->route($request->routeIs('original-occurrence.*') ? 'original-occurrence.transito.edit' : 'traffic.infractions.edit', $id)->with('status', 'Infração salva.');
    }
}
