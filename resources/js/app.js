import './bootstrap';
import { boot } from '@geoveil/core';

let theme = document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
const toggle = document.querySelector('[data-theme-toggle]');
function applyTheme(next, persist = true) {
    theme = next;
    document.documentElement.dataset.theme = theme;
    if (toggle) {
        toggle.textContent = theme === 'dark' ? '☾ Tema escuro' : '☀ Tema claro';
        toggle.setAttribute('aria-checked', String(theme === 'dark'));
    }
    document.querySelectorAll('[data-geoveil]').forEach(root => root.geoveil?.setTheme(theme));
    if (persist) { try { localStorage.setItem('ccdae-theme', theme); } catch (_) {} }
}
applyTheme(theme, false);
const mapsReady = boot(document, {theme});
toggle?.addEventListener('click', () => applyTheme(theme === 'dark' ? 'light' : 'dark'));
window.addEventListener('storage', event => {
    if (event.key === 'ccdae-theme') applyTheme(event.newValue === 'light' ? 'light' : 'dark', false);
});

const navigation = document.querySelector('#main-navigation');
const navigationToggle = document.querySelector('[data-navigation-toggle]');
const backdrop = document.querySelector('[data-navigation-close]');
const mobileNavigation = window.matchMedia('(max-width: 760px)');
function setNavigation(open) {
    document.body.classList.toggle('navigation-closed', !open);
    navigationToggle?.setAttribute('aria-expanded', String(open));
    if (navigation) navigation.inert = !open;
    if (backdrop) backdrop.hidden = !(open && mobileNavigation.matches);
    window.dispatchEvent(new Event('resize'));
}
setNavigation(!mobileNavigation.matches);
navigationToggle?.addEventListener('click', () => setNavigation(navigationToggle.getAttribute('aria-expanded') !== 'true'));
backdrop?.addEventListener('click', () => { setNavigation(false); navigationToggle?.focus(); });
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && navigationToggle?.getAttribute('aria-expanded') === 'true') {
        setNavigation(false);
        navigationToggle.focus();
    }
});
mobileNavigation.addEventListener('change', () => setNavigation(!mobileNavigation.matches));

if (document.querySelector('[data-boundary-editor]')) {
    import('./boundary-editor').then(module => module.mountBoundaryEditors());
}
if (document.querySelector('[data-geoveil-point]')) import('@geoveil/core/point-editor').then(module => module.mountPointEditors());

const geometryReady = document.querySelector('[data-geoveil-geometry]')
    ? import('@geoveil/core/geometry-editor').then(module => module.mountGeometryEditors()) : Promise.resolve();
if (document.querySelector('[data-geoveil-form="cabeamentos"]')) geometryReady.then(() => import('./cable-route')).then(module => module.mountCableRoute());

if (document.querySelector('[data-geoveil-remote-select]')) import('@geoveil/core/remote-select').then(module => module.mountRemoteSelects());
if (document.querySelector('[data-geoveil-select-filter]')) import('@geoveil/core/local-select').then(module => module.mountLocalSelects());
if (document.querySelector('[data-traffic-period]')) import('./traffic-period').then(module => module.mountTrafficPeriod());
if (document.querySelector('[data-column-picker]')) import('@geoveil/core/optional-columns').then(module => module.mountOptionalColumns());
if (document.querySelector('[data-traffic-timeline]')) mapsReady.then(() => import('./traffic-timeline')).then(module => module.mountTrafficTimeline());
if (document.querySelector('[data-climate-timeline]')) mapsReady.then(() => import('./climate-timeline')).then(module => module.mountClimateTimeline());
if (document.querySelector('[data-infrastructure-switch]')) mapsReady.then(() => import('./infrastructure-viewer')).then(module => module.mountInfrastructureViewer());
const climateCount = document.querySelector('[data-climate-count]');
if (climateCount) fetch(climateCount.dataset.url, { headers: { Accept: 'application/json' } })
    .then(response => { if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); })
    .then(data => { climateCount.textContent = new Intl.NumberFormat('pt-BR').format(data.count); })
    .catch(() => { climateCount.textContent = 'Indisponível'; });
