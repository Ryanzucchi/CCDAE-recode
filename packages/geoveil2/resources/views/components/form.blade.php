@props(['form', 'action', 'method' => 'POST', 'values' => []])
@php($definition = $form->jsonSerialize())
<form method="post" action="{{ $action }}" class="ccdae-map-card ccdae-form" data-geoveil-form="{{ $definition['id'] }}">
    @csrf @if(strtoupper($method) !== 'POST') @method($method) @endif
    @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @foreach($definition['sections'] as $section)
    <fieldset class="gv-form-section"><legend>{{ $section['label'] }}</legend>
        @if($section['description'])<p class="gv-form-description">{{ $section['description'] }}</p>@endif
        <div class="gv-form-grid" data-columns="{{ $section['columns'] }}">
        @foreach($section['fields'] as $field)
            @php($name = $field['name'])
            @php($value = old($name, $values[$name] ?? $field['default']))
            <div class="gv-form-field">
            <label for="gv-{{ $definition['id'] }}-{{ $name }}">{{ $field['label'] }}</label>
            @if($field['type'] === 'select')
                @if($field['searchable'])<input type="search" data-geoveil-select-filter="gv-{{ $definition['id'] }}-{{ $name }}" aria-label="Buscar {{ $field['label'] }}" placeholder="Buscar {{ mb_strtolower($field['label']) }}" autocomplete="off">@endif
                <select id="gv-{{ $definition['id'] }}-{{ $name }}" name="{{ $name }}" @required($field['required'])>
                    <option value="">Selecione</option>
                    @foreach($field['options'] as $key => $label)<option value="{{ $key }}" @selected((string)$value === (string)$key)>{{ $label }}</option>@endforeach
                </select>
            @elseif($field['type'] === 'remote-select')
                <div data-geoveil-remote-select data-endpoint="{{ $field['endpoint'] }}">
                    <input id="gv-{{ $definition['id'] }}-{{ $name }}" type="search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" placeholder="Digite para buscar" value="{{ $field['options'][$value] ?? '' }}" data-remote-query>
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-remote-value>
                    <div role="listbox" class="gv-remote-results" data-remote-results hidden></div>
                    <p class="gv-remote-status" data-remote-status aria-live="polite"></p>
                </div>
            @elseif($field['type'] === 'checkbox')
                <input type="hidden" name="{{ $name }}" value="0">
                <input id="gv-{{ $definition['id'] }}-{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked((bool)$value)>
            @elseif($field['type'] === 'textarea')
                <textarea id="gv-{{ $definition['id'] }}-{{ $name }}" name="{{ $name }}" @required($field['required']) @if($field['maxLength']) maxlength="{{ $field['maxLength'] }}" @endif @if($field['rows']) rows="{{ $field['rows'] }}" @endif>{{ $value }}</textarea>
            @elseif(in_array($field['type'], ['geometry', 'line'], true))
                <div data-geoveil-geometry data-geometry-mode="{{ $field['type'] === 'line' ? 'line' : 'polygon' }}">
                    <div class="ccdae-form-actions"><button type="button" class="ccdae-theme-toggle" data-geometry-draw>{{ $field['type'] === 'line' ? 'Desenhar linha' : 'Desenhar polígono' }}</button>@if($field['type'] === 'geometry')<button type="button" class="ccdae-theme-toggle" data-geometry-rectangle>Desenhar retângulo</button>@endif<button type="button" class="ccdae-theme-toggle" data-geometry-finish>{{ $field['type'] === 'line' ? 'Concluir linha' : 'Concluir polígono' }}</button><button type="button" class="ccdae-theme-toggle" data-geometry-undo>Desfazer ponto</button><button type="button" class="ccdae-theme-toggle" data-geometry-edit>Editar vértices</button><button type="button" class="ccdae-theme-toggle" data-geometry-clear>Limpar</button></div>
                    <p data-geometry-status role="status">{{ $field['type'] === 'line' ? 'Desenhe o traçado da linha no mapa.' : 'Clique no mapa para marcar um ponto ou desenhe um polígono.' }}</p>
                    <div data-geometry-map style="height:400px" aria-label="{{ $field['label'] }}"></div>
                    <input type="hidden" name="geojson" data-geometry-value value="{{ old('geojson', isset($values['geojson']) ? (is_string($values['geojson']) ? $values['geojson'] : json_encode($values['geojson'])) : '') }}">
                    @if($field['type'] === 'line')<input type="hidden" name="geometry_manual" data-geometry-manual value="{{ old('geometry_manual', '0') }}">@endif
                    @if($field['type'] === 'geometry')
                    <input type="hidden" name="latitude" data-geoveil-latitude value="{{ old('latitude', $values['latitude'] ?? '') }}">
                    <input type="hidden" name="longitude" data-geoveil-longitude value="{{ old('longitude', $values['longitude'] ?? '') }}">
                    @endif
                </div>
            @elseif($field['type'] === 'point')
                <div data-geoveil-point style="height:340px" aria-label="{{ $field['label'] }}"></div>
                <input type="hidden" name="latitude" data-geoveil-latitude value="{{ old('latitude', $values['latitude'] ?? '') }}">
                <input type="hidden" name="longitude" data-geoveil-longitude value="{{ old('longitude', $values['longitude'] ?? '') }}">
                <p>Selecione o ponto no mapa para preencher a localização.</p>
            @else
                <input id="gv-{{ $definition['id'] }}-{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}" value="{{ $value }}" @required($field['required']) @disabled($field['disabled']) @if($field['step']) step="{{ $field['step'] }}" @endif @if($field['maxLength']) maxlength="{{ $field['maxLength'] }}" @endif @if($field['min'] !== null) min="{{ $field['min'] }}" @endif @if($field['max'] !== null) max="{{ $field['max'] }}" @endif>
            @endif
            @if($field['help'])<small>{{ $field['help'] }}</small>@endif
            </div>
        @endforeach
        </div>
    </fieldset>
    @endforeach
    <div class="ccdae-form-actions"><button class="ccdae-theme-toggle" type="submit">Salvar</button><a href="{{ url()->previous() }}">Cancelar</a></div>
</form>
