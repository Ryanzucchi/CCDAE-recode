<?php

use App\Http\Controllers\Map\DistrictMapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/mapas/distritos');
Route::get('/admin', \App\Http\Controllers\Map\AdminDashboardController::class)
    ->middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->name('admin.dashboard');
Route::get('/admin/indicadores/clima', [\App\Http\Controllers\Map\AdminDashboardController::class, 'climateCount'])
    ->middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->name('admin.dashboard.climate-count');
Route::get('/mapas/distritos', DistrictMapController::class)->name('maps.districts');

Route::get('/mapas/localizar', \App\Http\Controllers\Map\GeocodeController::class)
    ->middleware('throttle:20,1')->name('maps.geocode');
Route::get('/tiles/{source}', [\App\Http\Controllers\Map\VectorTileController::class, 'tilejson'])->name('maps.tilejson');
Route::get('/tiles/{source}/{z}/{x}/{y}', [\App\Http\Controllers\Map\VectorTileController::class, 'tile'])
    ->whereNumber('z')->whereNumber('x')->whereNumber('y')->name('maps.vector-tile');
Route::get('/mapas/infraestrutura-janela/{module}/{layer}', \App\Http\Controllers\Map\InfrastructureViewportController::class)
    ->whereIn('module', ['energia', 'internet', 'global'])->whereNumber('layer')->name('maps.infrastructure-viewport');
Route::get('/mapas/construcoes-janela', \App\Http\Controllers\Map\BuildingViewportController::class)->name('maps.building-viewport');
Route::get('/mapas/infraestrutura-cena', \App\Http\Controllers\Map\InfrastructureSceneController::class)->name('maps.infrastructure-scene');

Route::get('/distritos', \App\Http\Controllers\Map\DistrictIndexController::class)->name('districts.index');
Route::get('/mapas/{page}', \App\Http\Controllers\Map\AnalyticMapController::class)
    ->whereIn('page', ['clima', 'infraestrutura', 'transito'])->name('maps.analytic');
Route::get('/admin/mapa-populacao', DistrictMapController::class)->name('original-admin.map-population');
Route::get('/admin/mapa-clima', \App\Http\Controllers\Map\AnalyticMapController::class)
    ->defaults('page', 'clima')->name('original-admin.map-climate');
Route::get('/admin/mapa-infraestrutura', \App\Http\Controllers\Map\AnalyticMapController::class)
    ->defaults('page', 'infraestrutura')->name('original-admin.map-infrastructure');
Route::get('/admin/mapa-transito', \App\Http\Controllers\Map\AnalyticMapController::class)
    ->defaults('page', 'transito')->name('original-admin.map-traffic');
Route::get('/admin/distritos', \App\Http\Controllers\Map\DistrictIndexController::class)->name('original-admin.districts');

foreach (['energia', 'internet', 'transito'] as $module) {
    Route::get('/'.$module, \App\Http\Controllers\Map\ModuleController::class)->name($module);
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\AuthController::class, 'create'])->name('login');
    Route::post('/login', [\App\Http\Controllers\AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'destroy'])->middleware('auth')->name('logout');
foreach (['admin', 'energia', 'internet', 'transito'] as $panel) {
    Route::get('/'.$panel.'/login', function () use ($panel) {
        $destination = $panel === 'admin' ? route('maps.districts') : route($panel);
        if (auth()->check()) return redirect($destination);
        session()->put('url.intended', $destination);
        return redirect()->route('login');
    })->name('original-auth.'.$panel.'.login');
    Route::post('/'.$panel.'/logout', [\App\Http\Controllers\AuthController::class, 'destroy'])
        ->middleware('auth')->name('original-auth.'.$panel.'.logout');
}
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/distritos/novo', [\App\Http\Controllers\Map\DistrictEditorController::class, 'create'])->name('districts.create');
    Route::post('/distritos', [\App\Http\Controllers\Map\DistrictEditorController::class, 'store'])->name('districts.store');
    Route::get('/distritos/{district}/editar', [\App\Http\Controllers\Map\DistrictEditorController::class, 'edit'])->name('districts.edit');
    Route::put('/distritos/{district}', [\App\Http\Controllers\Map\DistrictEditorController::class, 'update'])->name('districts.update');
    Route::delete('/distritos/{district}', [\App\Http\Controllers\Map\DistrictEditorController::class, 'destroy'])->name('districts.destroy');
    Route::post('/distritos/excluir-lote', [\App\Http\Controllers\Map\DistrictEditorController::class, 'destroyBatch'])->name('districts.destroy-batch');
});
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/conta/senha', [\App\Http\Controllers\MasterPasswordController::class, 'edit'])->name('master.password.edit');
    Route::put('/conta/senha', [\App\Http\Controllers\MasterPasswordController::class, 'update'])->name('master.password.update');
});
Route::get('/energia/incidentes', [\App\Http\Controllers\Map\EnergyIncidentController::class, 'index'])->name('energy.incidents.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/energia/incidentes/novo', [\App\Http\Controllers\Map\EnergyIncidentController::class, 'create'])->name('energy.incidents.create');
    Route::post('/energia/incidentes', [\App\Http\Controllers\Map\EnergyIncidentController::class, 'store'])->name('energy.incidents.store');
    Route::get('/energia/incidentes/{id}/editar', [\App\Http\Controllers\Map\EnergyIncidentController::class, 'edit'])->name('energy.incidents.edit');
    Route::put('/energia/incidentes/{id}', [\App\Http\Controllers\Map\EnergyIncidentController::class, 'update'])->name('energy.incidents.update');
    Route::post('/energia/incidentes/excluir-lote', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'energy'])->name('energy.incidents.destroy-batch');
    Route::delete('/energia/incidentes/{id}', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'energyOne'])->whereNumber('id')->name('energy.incidents.destroy');
});
Route::get('/internet/incidentes', [\App\Http\Controllers\Map\InternetIncidentController::class, 'index'])->name('internet.incidents.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/internet/incidentes/novo', [\App\Http\Controllers\Map\InternetIncidentController::class, 'create'])->name('internet.incidents.create');
    Route::post('/internet/incidentes', [\App\Http\Controllers\Map\InternetIncidentController::class, 'store'])->name('internet.incidents.store');
    Route::get('/internet/incidentes/{id}/editar', [\App\Http\Controllers\Map\InternetIncidentController::class, 'edit'])->name('internet.incidents.edit');
    Route::put('/internet/incidentes/{id}', [\App\Http\Controllers\Map\InternetIncidentController::class, 'update'])->name('internet.incidents.update');
    Route::post('/internet/incidentes/excluir-lote', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'internet'])->name('internet.incidents.destroy-batch');
    Route::delete('/internet/incidentes/{id}', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'internetOne'])->whereNumber('id')->name('internet.incidents.destroy');
});
Route::get('/transito/infracoes', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'index'])->name('traffic.infractions.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/transito/infracoes/novo', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'create'])->name('traffic.infractions.create');
    Route::post('/transito/infracoes', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'store'])->name('traffic.infractions.store');
    Route::get('/transito/infracoes/{id}/editar', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'edit'])->name('traffic.infractions.edit');
    Route::put('/transito/infracoes/{id}', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'update'])->name('traffic.infractions.update');
    Route::post('/transito/infracoes/excluir-lote', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'traffic'])->name('traffic.infractions.destroy-batch');
    Route::delete('/transito/infracoes/{id}', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'trafficOne'])->whereNumber('id')->name('traffic.infractions.destroy');
});
// As páginas do Filament usam slugs próprios, diferentes dos tipos internos do GeoVeil.
foreach ([
    'energia/mapa-energia' => ['energia', 'densidade'],
    'energia/mapa-consumo' => ['energia', 'consumo'],
    'internet/mapa-rede' => ['internet', 'densidade-rede'],
    'internet/mapa-demanda' => ['internet', 'demanda'],
    'transito/mapa-volume-veiculos' => ['transito', 'volume'],
    'transito/mapa-infracoes' => ['transito', 'infracoes'],
    'transito/mapa-acidentes' => ['transito', 'acidentes'],
    'transito/mapa-obras' => ['transito', 'obras'],
] as $slug => [$module, $type]) {
    Route::get('/'.$slug, \App\Http\Controllers\Map\ModuleIntensityMapController::class)
        ->defaults('module', $module)->defaults('type', $type)
        ->name('original-map.'.str_replace('/', '.', $slug));
}
Route::get('/{module}/mapa-{type}', \App\Http\Controllers\Map\ModuleIntensityMapController::class)
    ->whereIn('module', ['energia', 'internet', 'transito'])
    ->whereIn('type', ['densidade', 'densidade-rede', 'consumo', 'populacao', 'demanda', 'infracoes', 'volume', 'acidentes', 'obras'])
    ->name('module.intensity');
Route::get('/transito/mapa-transito', \App\Http\Controllers\Map\AnalyticMapController::class)
    ->defaults('page', 'transito')->name('original-traffic.map');

// Caminhos da navegação Filament original, mantidos durante a migração.
foreach (\App\Services\InfrastructureResourceCatalog::ORIGINAL_SLUGS as $kind => $legacy) {
    Route::get('/admin/'.$legacy, fn (\Illuminate\Http\Request $request) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
        ->index($request, $kind, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
        ->name('original-resource.'.$kind.'.index');
    Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () use ($kind, $legacy) {
        Route::get('/admin/'.$legacy.'/create', fn () => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->create($kind, app(\App\Services\InfrastructureResourceCatalog::class)))
            ->name('original-resource.'.$kind.'.create');
        Route::post('/admin/'.$legacy, fn (\Illuminate\Http\Request $request) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->store($request, $kind, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
            ->name('original-resource.'.$kind.'.store');
        Route::get('/admin/'.$legacy.'/{id}/edit', fn (int $id) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->edit($kind, $id, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
            ->whereNumber('id')->name('original-resource.'.$kind.'.edit');
        Route::put('/admin/'.$legacy.'/{id}/edit', fn (\Illuminate\Http\Request $request, int $id) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->update($request, $kind, $id, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
            ->whereNumber('id')->name('original-resource.'.$kind.'.update');
        Route::delete('/admin/'.$legacy.'/{id}/edit', fn (\Illuminate\Http\Request $request, int $id) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->destroy($request, $kind, $id, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
            ->whereNumber('id')->name('original-resource.'.$kind.'.destroy');
        Route::post('/admin/'.$legacy.'/excluir-lote', fn (\Illuminate\Http\Request $request) => app(\App\Http\Controllers\Map\InfrastructureResourceController::class)
            ->destroyBatch($request, $kind, app(\App\Services\InfrastructureResourceCatalog::class), app(\App\Services\MapData::class)))
            ->name('original-resource.'.$kind.'.destroy-batch');
    });
}
foreach (\App\Services\ClimateResource::ORIGINAL_SLUGS as $kind => $legacy) {
    Route::get('/admin/'.$legacy, fn (\Illuminate\Http\Request $request) => app(\App\Http\Controllers\Map\ClimateResourceController::class)
        ->__invoke($kind, $request, app(\App\Services\MapData::class), app(\App\Services\ClimateResource::class)))
        ->name('original-climate.'.$kind.'.index');
}
foreach (['energia' => \App\Http\Controllers\Map\EnergyIncidentController::class,
    'internet' => \App\Http\Controllers\Map\InternetIncidentController::class] as $module => $controller) {
    Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () use ($module, $controller) {
        Route::get('/'.$module.'/incidentes/create', [$controller, 'create'])->name('original-occurrence.'.$module.'.create');
        Route::post('/'.$module.'/incidentes/create', [$controller, 'store'])->name('original-occurrence.'.$module.'.store');
        Route::get('/'.$module.'/incidentes/{id}/edit', [$controller, 'edit'])->whereNumber('id')->name('original-occurrence.'.$module.'.edit');
        Route::put('/'.$module.'/incidentes/{id}/edit', [$controller, 'update'])->whereNumber('id')->name('original-occurrence.'.$module.'.update');
        Route::delete('/'.$module.'/incidentes/{id}/edit', [\App\Http\Controllers\Map\OccurrenceBatchController::class, $module === 'energia' ? 'energyOne' : 'internetOne'])
            ->whereNumber('id')->name('original-occurrence.'.$module.'.destroy');
    });
}
Route::get('/admin/via-transitos', [\App\Http\Controllers\Map\RoadResourceController::class, 'index'])->name('original-roads.index');
Route::get('/admin/via-transitos/{id}', [\App\Http\Controllers\Map\RoadResourceController::class, 'show'])->whereNumber('id')->name('original-roads.show');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/via-transitos/create', [\App\Http\Controllers\Map\RoadResourceController::class, 'create'])->name('original-roads.create');
    Route::post('/admin/via-transitos', [\App\Http\Controllers\Map\RoadResourceController::class, 'store'])->name('original-roads.store');
    Route::get('/admin/via-transitos/{id}/edit', [\App\Http\Controllers\Map\RoadResourceController::class, 'edit'])->whereNumber('id')->name('original-roads.edit');
    Route::put('/admin/via-transitos/{id}/edit', [\App\Http\Controllers\Map\RoadResourceController::class, 'update'])->whereNumber('id')->name('original-roads.update');
    Route::delete('/admin/via-transitos/{id}/edit', [\App\Http\Controllers\Map\RoadResourceController::class, 'destroy'])->whereNumber('id')->name('original-roads.destroy');
    Route::post('/admin/via-transitos/excluir-lote', [\App\Http\Controllers\Map\RoadResourceController::class, 'destroyBatch'])->name('original-roads.destroy-batch');
});
Route::get('/admin/registro-transitos', [\App\Http\Controllers\Map\TrafficRecordController::class, 'index'])->name('original-traffic.records.index');
Route::get('/admin/registro-transitos/{record}', [\App\Http\Controllers\Map\TrafficRecordController::class, 'show'])->where('record', '[0-9]+-[0-9]{8}T[0-9]{6}')->name('original-traffic.records.show');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/registro-transitos/create', [\App\Http\Controllers\Map\TrafficRecordController::class, 'create'])->name('original-traffic.records.create');
    Route::post('/admin/registro-transitos', [\App\Http\Controllers\Map\TrafficRecordController::class, 'store'])->name('original-traffic.records.store');
    Route::get('/admin/registro-transitos/{record}/edit', [\App\Http\Controllers\Map\TrafficRecordController::class, 'edit'])->where('record', '[0-9]+-[0-9]{8}T[0-9]{6}')->name('original-traffic.records.edit');
    Route::put('/admin/registro-transitos/{record}/edit', [\App\Http\Controllers\Map\TrafficRecordController::class, 'update'])->where('record', '[0-9]+-[0-9]{8}T[0-9]{6}')->name('original-traffic.records.update');
    Route::delete('/admin/registro-transitos/{record}/edit', [\App\Http\Controllers\Map\TrafficRecordController::class, 'destroy'])->where('record', '[0-9]+-[0-9]{8}T[0-9]{6}')->name('original-traffic.records.destroy');
    Route::post('/admin/registro-transitos/excluir-lote', [\App\Http\Controllers\Map\TrafficRecordController::class, 'destroyBatch'])->name('original-traffic.records.destroy-batch');
});
Route::get('/transito/infracoes/infracao-transitos', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'index'])->name('original-occurrence.transito.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/transito/infracoes/infracao-transitos/create', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'create'])->name('original-occurrence.transito.create');
    Route::post('/transito/infracoes/infracao-transitos/create', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'store'])->name('original-occurrence.transito.store');
    Route::get('/transito/infracoes/infracao-transitos/{id}/edit', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'edit'])->whereNumber('id')->name('original-occurrence.transito.edit');
    Route::put('/transito/infracoes/infracao-transitos/{id}/edit', [\App\Http\Controllers\Map\TrafficInfractionController::class, 'update'])->whereNumber('id')->name('original-occurrence.transito.update');
    Route::delete('/transito/infracoes/infracao-transitos/{id}/edit', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'trafficOne'])->whereNumber('id')->name('original-occurrence.transito.destroy');
    Route::post('/transito/infracoes/infracao-transitos/excluir-lote', [\App\Http\Controllers\Map\OccurrenceBatchController::class, 'traffic'])->name('original-occurrence.transito.destroy-batch');
});
Route::get('/admin/infraestrutura/{kind}', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'index'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/infraestrutura/{kind}/novo', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'create'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.create');
    Route::post('/admin/infraestrutura/{kind}', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'store'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.store');
    Route::get('/admin/infraestrutura/{kind}/{id}/editar', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'edit'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.edit');
    Route::put('/admin/infraestrutura/{kind}/{id}', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'update'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.update');
    Route::post('/admin/infraestrutura/{kind}/excluir-lote', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'destroyBatch'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->name('infrastructure.resource.destroy-batch');
    Route::delete('/admin/infraestrutura/{kind}/{id}', [\App\Http\Controllers\Map\InfrastructureResourceController::class, 'destroy'])->whereIn('kind', ['postes', 'antenas', 'equipamentos_infraestrutura', 'centrais_distribuicao', 'cabeamentos'])->whereNumber('id')->name('infrastructure.resource.destroy');
});
Route::get('/admin/lookup/{kind}', \App\Http\Controllers\Map\ResourceLookupController::class)
    ->middleware(['auth', \App\Http\Middleware\EnsureMaster::class, 'throttle:60,1'])
    ->whereIn('kind', ['postes', 'centrais', 'antenas', 'cabeamentos', 'equipamentos', 'vias'])
    ->name('resource.lookup');
Route::get('/admin/vias', [\App\Http\Controllers\Map\RoadResourceController::class, 'index'])->name('roads.index');
Route::get('/admin/vias/{id}', [\App\Http\Controllers\Map\RoadResourceController::class, 'show'])->whereNumber('id')->name('roads.show');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/vias/novo', [\App\Http\Controllers\Map\RoadResourceController::class, 'create'])->name('roads.create');
    Route::post('/admin/vias', [\App\Http\Controllers\Map\RoadResourceController::class, 'store'])->name('roads.store');
    Route::get('/admin/vias/{id}/editar', [\App\Http\Controllers\Map\RoadResourceController::class, 'edit'])->name('roads.edit');
    Route::put('/admin/vias/{id}', [\App\Http\Controllers\Map\RoadResourceController::class, 'update'])->name('roads.update');
    Route::post('/admin/vias/excluir-lote', [\App\Http\Controllers\Map\RoadResourceController::class, 'destroyBatch'])->name('roads.destroy-batch');
    Route::delete('/admin/vias/{id}', [\App\Http\Controllers\Map\RoadResourceController::class, 'destroy'])->whereNumber('id')->name('roads.destroy');
});
Route::get('/admin/registros-transito', [\App\Http\Controllers\Map\TrafficRecordController::class, 'index'])->name('traffic.records.index');
Route::get('/admin/registros-transito/ver', [\App\Http\Controllers\Map\TrafficRecordController::class, 'show'])->name('traffic.records.show');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/activity-logs', [\App\Http\Controllers\Map\ActivityLogController::class, 'index'])->name('activity.logs.index');
    Route::get('/admin/activity-logs/{id}', [\App\Http\Controllers\Map\ActivityLogController::class, 'show'])->name('activity.logs.show');
    Route::post('/admin/activity-logs/excluir-lote', [\App\Http\Controllers\Map\ActivityLogController::class, 'destroyBatch'])->name('activity.logs.destroy-batch');
    Route::get('/admin/system-health', \App\Http\Controllers\Map\SystemHealthController::class)->name('system.health');
    Route::post('/admin/system-health/limpar-fila', [\App\Http\Controllers\Map\SystemHealthController::class, 'clearQueue'])->name('system.health.clear-queue');
    Route::post('/admin/system-health/executar-coleta', [\App\Http\Controllers\Map\SystemHealthController::class, 'runCollector'])->name('system.health.run-collector');
});
Route::get('/admin/clima/{kind}', \App\Http\Controllers\Map\ClimateResourceController::class)
    ->whereIn('kind', array_keys(\App\Services\ClimateResource::TYPES))->name('climate.resources.index');
Route::middleware(['auth', \App\Http\Middleware\EnsureMaster::class])->group(function () {
    Route::get('/admin/registros-transito/novo', [\App\Http\Controllers\Map\TrafficRecordController::class, 'create'])->name('traffic.records.create');
    Route::post('/admin/registros-transito', [\App\Http\Controllers\Map\TrafficRecordController::class, 'store'])->name('traffic.records.store');
    Route::get('/admin/registros-transito/editar', [\App\Http\Controllers\Map\TrafficRecordController::class, 'edit'])->name('traffic.records.edit');
    Route::put('/admin/registros-transito/editar', [\App\Http\Controllers\Map\TrafficRecordController::class, 'update'])->name('traffic.records.update');
    Route::post('/admin/registros-transito/excluir-lote', [\App\Http\Controllers\Map\TrafficRecordController::class, 'destroyBatch'])->name('traffic.records.destroy-batch');
    Route::delete('/admin/registros-transito/editar', [\App\Http\Controllers\Map\TrafficRecordController::class, 'destroy'])->name('traffic.records.destroy');
});
