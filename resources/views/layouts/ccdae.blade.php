<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Distribuição populacional') · CCDAE</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('ccdae-theme') === 'light' ? 'light' : 'dark'; } catch (_) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ccdae-shell">
    @php($panel = request()->is('energia*') ? 'energia' : (request()->is('internet*') ? 'internet' : (request()->is('transito*') ? 'transito' : 'admin')))
    <header class="ccdae-topbar">
        <button type="button" class="ccdae-theme-toggle" data-navigation-toggle aria-label="Alternar navegação" aria-controls="main-navigation" aria-expanded="true">☰</button>
        <a href="{{ route('original-admin.map-population') }}" class="ccdae-brand">CCDAE <span>GeoVeil 2</span></a>
        <nav class="ccdae-panel-switcher" aria-label="Painéis">
            @foreach(['admin' => ['Admin', route('original-admin.map-population')], 'energia' => ['Energia', route('energia')], 'internet' => ['Internet', route('internet')], 'transito' => ['Trânsito', route('transito')]] as $key => [$label, $url])
            <a href="{{ $url }}" @if($panel === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ccdae-topbar-actions">
        @auth <a class="ccdae-original" href="{{ route('master.password.edit') }}">Alterar senha</a><form method="post" action="{{ route('original-auth.'.$panel.'.logout') }}">@csrf<button class="ccdae-theme-toggle" type="submit">Sair · {{ auth()->user()->name }}</button></form>
        @else <a class="ccdae-original" href="{{ route('original-auth.'.$panel.'.login') }}">Entrar</a> @endauth
<button type="button" class="ccdae-theme-toggle" data-theme-toggle role="switch" aria-label="Tema escuro" aria-checked="true">Tema escuro</button>
        <a href="http://ccdae.local/admin/mapa-populacao" target="_blank" rel="noopener" class="ccdae-original">Comparar com o original ↗</a></div>
    </header>
    <button class="ccdae-navigation-backdrop" data-navigation-close aria-label="Fechar navegação" hidden></button>
    <aside id="main-navigation" class="ccdae-navigation" aria-label="Navegação principal">
        @if($panel === 'admin')
        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) class="ccdae-nav-active" aria-current="page" @endif>Visão geral</a>
        <p class="ccdae-nav-caption">MAPAS E ANÁLISES</p>
        <a href="{{ route('original-admin.map-population') }}" @if(request()->routeIs('maps.districts', 'original-admin.map-population')) class="ccdae-nav-active" aria-current="page" @endif>Mapa de população</a>
        <a href="{{ route('original-admin.districts') }}" @if(request()->routeIs('districts.*', 'original-admin.districts')) class="ccdae-nav-active" aria-current="page" @endif>Distritos</a>
        @foreach(['clima' => ['Mapa do Clima', 'original-admin.map-climate'], 'infraestrutura' => ['Mapa da Infraestrutura', 'original-admin.map-infrastructure'], 'transito' => ['Mapa de Trânsito', 'original-admin.map-traffic']] as $slug => [$label, $routeName])
        <a href="{{ route($routeName) }}" @if(request()->route('page') === $slug) class="ccdae-nav-active" aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <p class="ccdae-nav-caption">CADASTROS DE INFRAESTRUTURA</p>
        @foreach(['postes' => 'Postes', 'antenas' => 'Antenas', 'equipamentos_infraestrutura' => 'Equipamentos', 'centrais_distribuicao' => 'Centrais de distribuição', 'cabeamentos' => 'Cabeamentos'] as $kind => $label)
        <a href="{{ route('original-resource.'.$kind.'.index') }}" @if(request()->route('kind') === $kind || request()->routeIs('original-resource.'.$kind.'.*')) class="ccdae-nav-active" aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <p class="ccdae-nav-caption">TRÂNSITO</p>
        <a href="{{ route('original-roads.index') }}" @if(request()->routeIs('roads.*', 'original-roads.*')) class="ccdae-nav-active" aria-current="page" @endif>Vias de trânsito</a>
        <a href="{{ route('original-traffic.records.index') }}" @if(request()->routeIs('traffic.records.*', 'original-traffic.records.*')) class="ccdae-nav-active" aria-current="page" @endif>Registros de trânsito</a>
        <p class="ccdae-nav-caption">DADOS CLIMÁTICOS</p>
        @foreach(\App\Services\ClimateResource::TYPES as $kind => [$title])
        <a href="{{ route('original-climate.'.$kind.'.index') }}" @if(request()->routeIs('original-climate.'.$kind.'.index') || (request()->routeIs('climate.resources.index') && request()->route('kind') === $kind)) class="ccdae-nav-active" aria-current="page" @endif>{{ $title }}</a>
        @endforeach
        @if(auth()->user()?->email === config('ccdae.master_email'))
        <p class="ccdae-nav-caption">ADMINISTRAÇÃO</p>
        <a href="{{ route('activity.logs.index') }}" @if(request()->routeIs('activity.logs.*')) class="ccdae-nav-active" aria-current="page" @endif>Histórico de atividades</a>
        <a href="{{ route('system.health') }}" @if(request()->routeIs('system.health')) class="ccdae-nav-active" aria-current="page" @endif>Saúde do Sistema</a>
        @endif
        @elseif($panel === 'energia')
        <p class="ccdae-nav-caption">ENERGIA</p>
        <a href="{{ route('energia') }}" @if(request()->routeIs('energia')) class="ccdae-nav-active" aria-current="page" @endif>Visão geral</a>
        <a href="{{ route('original-map.energia.mapa-energia') }}" @if(request()->routeIs('original-map.energia.mapa-energia') || request()->route('type') === 'densidade') class="ccdae-nav-active" aria-current="page" @endif>Mapa de energia</a>
        <a href="{{ route('original-map.energia.mapa-consumo') }}" @if(request()->routeIs('original-map.energia.mapa-consumo') || request()->route('type') === 'consumo') class="ccdae-nav-active" aria-current="page" @endif>Mapa de consumo</a>
        <a href="{{ route('module.intensity', ['module' => 'energia', 'type' => 'populacao']) }}" @if(request()->route('type') === 'populacao') class="ccdae-nav-active" aria-current="page" @endif>Mapa de população</a>
        <a href="{{ route('energy.incidents.index') }}" @if(request()->routeIs('energy.incidents.*')) class="ccdae-nav-active" aria-current="page" @endif>Incidentes</a>
        @elseif($panel === 'internet')
        <p class="ccdae-nav-caption">INTERNET</p>
        <a href="{{ route('internet') }}" @if(request()->routeIs('internet')) class="ccdae-nav-active" aria-current="page" @endif>Visão geral</a>
        <a href="{{ route('original-map.internet.mapa-rede') }}" @if(request()->routeIs('original-map.internet.mapa-rede') || request()->route('type') === 'densidade-rede') class="ccdae-nav-active" aria-current="page" @endif>Mapa da rede</a>
        <a href="{{ route('original-map.internet.mapa-demanda') }}" @if(request()->routeIs('original-map.internet.mapa-demanda') || request()->route('type') === 'demanda') class="ccdae-nav-active" aria-current="page" @endif>Mapa de demanda</a>
        <a href="{{ route('internet.incidents.index') }}" @if(request()->routeIs('internet.incidents.*')) class="ccdae-nav-active" aria-current="page" @endif>Incidentes</a>
        @else
        <p class="ccdae-nav-caption">TRÂNSITO E MOBILIDADE</p>
        <a href="{{ route('transito') }}" @if(request()->routeIs('transito')) class="ccdae-nav-active" aria-current="page" @endif>Visão geral</a>
        <a href="{{ route('original-traffic.map') }}" @if(request()->routeIs('original-traffic.map')) class="ccdae-nav-active" aria-current="page" @endif>Mapa de trânsito</a>
        @foreach(['mapa-volume-veiculos' => 'Volume de veículos', 'mapa-infracoes' => 'Mapa de infrações', 'mapa-acidentes' => 'Mapa de acidentes', 'mapa-obras' => 'Mapa de obras'] as $slug => $label)
        <a href="{{ route('original-map.transito.'.$slug) }}" @if(request()->routeIs('original-map.transito.'.$slug)) class="ccdae-nav-active" aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ route('original-occurrence.transito.index') }}" @if(request()->routeIs('traffic.infractions.*', 'original-occurrence.transito.*')) class="ccdae-nav-active" aria-current="page" @endif>Infrações de trânsito</a>
        @endif
    </aside>
    <main class="ccdae-content">
        @yield('content')
    </main>
</body>
</html>
