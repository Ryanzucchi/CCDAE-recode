<?php
namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use App\Services\{MapData, OccurrenceListing};
use GeoVeil\Forms\{Form, Field};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InternetIncidentController extends Controller
{
    private const TYPES = ['rompimento_fibra' => 'Rompimento de fibra', 'queda_servico' => 'Queda de serviço', 'degradacao' => 'Lentidão ou degradação', 'falha_antena' => 'Falha de antena', 'falha_equipamento' => 'Falha de equipamento', 'interferencia' => 'Interferência de sinal', 'vandalismo' => 'Vandalismo', 'outro' => 'Outro'];
    private const CAUSES = ['clima' => 'Evento climático', 'obra' => 'Obra ou escavação', 'acidente' => 'Acidente', 'energia' => 'Falha de energia', 'equipamento' => 'Falha de equipamento', 'configuracao' => 'Configuração', 'sobrecarga' => 'Sobrecarga', 'vandalismo' => 'Vandalismo', 'desconhecida' => 'Desconhecida', 'outra' => 'Outra'];
    private const SEVERITIES = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
    private const STATUSES = ['registrado' => 'Registrado', 'em_atendimento' => 'Em atendimento', 'resolvido' => 'Resolvido', 'cancelado' => 'Cancelado'];

    public function index(Request $request, MapData $data, OccurrenceListing $listing)
    {
        return view('maps.occurrences', $listing->viewData('internet', $request, $data));
    }
    public function create(MapData $data) { return $this->editor($data, null); }
    public function edit(MapData $data, int $id)
    {
        $incident = DB::table($data->table('internet', 'incidentes_internet'))->where('id', $id)->first();
        abort_unless($incident, 404);
        return $this->editor($data, $incident);
    }
    private function editor(MapData $data, ?object $incident)
    {
        $districts = Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
        $options = [];
        foreach ([['antena_id','antenas','codigo_patrimonio'], ['cabeamento_id','cabeamentos','nome'], ['equipamento_id','equipamentos_infraestrutura','nome']] as [$field, $table, $label]) {
            $options[$field] = [];
            if ($incident?->$field) $options[$field][$incident->$field] = DB::table($data->table('infrastructure', $table))->where('id', $incident->$field)->value($label) ?? 'Ativo #'.$incident->$field;
        }
        $form = Form::make('internet-incident')->section('Ocorrência', [
            Field::select('tipo', self::TYPES)->label('Tipo')->required(), Field::select('causa', self::CAUSES)->label('Causa'),
            Field::datetime('ocorrido_em')->label('Data e hora')->required()->default(now()->format('Y-m-d\TH:i')),
            Field::datetime('resolvido_em')->label('Resolvido em'),
            Field::select('severidade', self::SEVERITIES)->label('Severidade')->required()->default('media'),
            Field::select('situacao', self::STATUSES)->label('Situação')->required()->default('registrado'),
            Field::number('usuarios_afetados')->label('Usuários afetados')->step('1'),
            Field::textarea('descricao')->label('Descrição'),
        ])->section('Ativo afetado', [
            Field::select('distrito_id', $districts)->label('Distrito'),
            Field::remoteSelect('antena_id', route('resource.lookup', 'antenas'), $options['antena_id'])->label('Antena'),
            Field::remoteSelect('cabeamento_id', route('resource.lookup', ['kind' => 'cabeamentos', 'domain' => 'internet']), $options['cabeamento_id'])->label('Cabeamento'),
            Field::remoteSelect('equipamento_id', route('resource.lookup', 'equipamentos'), $options['equipamento_id'])->label('Equipamento de rede'),
        ])->section('Localização', [Field::point()->label('Ponto afetado')->required()]);
        $values = $incident ? (array) $incident : [];
        if ($incident) foreach (['ocorrido_em', 'resolvido_em'] as $field) if ($values[$field]) $values[$field] = \Carbon\Carbon::parse($values[$field])->format('Y-m-d\TH:i');
        return view('maps.internet-incident-form', compact('form', 'values', 'incident'));
    }
    public function store(Request $request, MapData $data) { return $this->save($request, $data); }
    public function update(Request $request, MapData $data, int $id)
    {
        abort_unless(DB::table($data->table('internet', 'incidentes_internet'))->where('id', $id)->exists(), 404);
        return $this->save($request, $data, $id);
    }
    private function save(Request $request, MapData $data, ?int $id = null)
    {
        $input = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TYPES))], 'causa' => ['nullable', Rule::in(array_keys(self::CAUSES))],
            'ocorrido_em' => 'required|date', 'resolvido_em' => 'nullable|date|after_or_equal:ocorrido_em',
            'severidade' => ['required', Rule::in(array_keys(self::SEVERITIES))], 'situacao' => ['required', Rule::in(array_keys(self::STATUSES))],
            'usuarios_afetados' => 'nullable|integer|min:0', 'descricao' => 'nullable|string|max:10000',
            'distrito_id' => ['nullable','integer',Rule::exists((new Distrito)->getTable(), 'id')],
            'antena_id' => ['nullable','integer',Rule::exists($data->table('infrastructure','antenas'), 'id')],
            'cabeamento_id' => ['nullable','integer',Rule::exists($data->table('infrastructure','cabeamentos'), 'id')],
            'equipamento_id' => ['nullable','integer',Rule::exists($data->table('infrastructure','equipamentos_infraestrutura'), 'id')],
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
        ]);
        $input['updated_at'] = now();
        $table = $data->table('internet', 'incidentes_internet');
        if ($id) DB::table($table)->where('id', $id)->update($input);
        else { $input['created_at'] = now(); $input['registrado_por'] = $request->user()->id; $id = DB::table($table)->insertGetId($input); }
        return redirect()->route($request->routeIs('original-occurrence.*') ? 'original-occurrence.internet.edit' : 'internet.incidents.edit', $id)->with('status', 'Ocorrência salva.');
    }
}
