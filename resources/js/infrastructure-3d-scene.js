/**
 * Infrastructure 3D WebGL Scene Engine (Three.js)
 * CCDAE Smart City Infrastructure Viewer
 */

export class Infrastructure3DScene {
    constructor(containerId = 'infra-3d-container', tooltipId = 'three-tooltip') {
        this.container = document.getElementById(containerId);
        this.tooltip = document.getElementById(tooltipId);
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.raycaster = null;
        this.animationFrameId = null;
        this.terrainSampler = null;
        this.terrainRequestKey = null;
    }

    init() {
        if (!this.container || this.renderer) return;

        const width = this.container.clientWidth;
        const height = this.container.clientHeight || 750;

        this.scene = new THREE.Scene();
        const isDark = document.documentElement.dataset.theme === 'dark';
        this.scene.background = new THREE.Color(isDark ? 0x07111f : 0xdbeafe);
        this.scene.fog = new THREE.Fog(isDark ? 0x07111f : 0xdbeafe, 700, 4200);

        this.camera = new THREE.PerspectiveCamera(45, width / height, 1, 10000);
        this.camera.position.set(0, 200, 450);

        this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputEncoding = THREE.sRGBEncoding;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = isDark ? 0.72 : 0.95;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        try {
            const ControlsClass = THREE.OrbitControls || window.OrbitControls || OrbitControls;
            if (ControlsClass) {
                this.controls = new ControlsClass(this.camera, this.renderer.domElement);
                this.controls.enableDamping = true;
                this.controls.dampingFactor = 0.05;
                this.controls.maxPolarAngle = Math.PI / 2 - 0.01;
                this.controls.minDistance = 1;
                this.controls.maxDistance = 4000;
                this.controls.addEventListener('change', () => {
                    if (this.tooltip) this.tooltip.style.display = 'none';
                });
            }
        } catch (err) {
            console.error('Error loading OrbitControls:', err);
        }

        this.raycaster = new THREE.Raycaster();
        this.raycaster.params.Line.threshold = 0.6;

        let startX = 0, startY = 0;
        this.container.addEventListener('pointerdown', (e) => {
            startX = e.clientX;
            startY = e.clientY;
        });

        this.container.addEventListener('pointerup', (e) => {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (Math.sqrt(dx * dx + dy * dy) < 4) {
                this.handleClick(e);
            }
        });

        const ground = new THREE.Mesh(
            new THREE.PlaneGeometry(6000, 6000),
            new THREE.MeshStandardMaterial({ color: isDark ? 0x0b1728 : 0xcbd5e1, roughness: 1 })
        );
        ground.rotation.x = -Math.PI / 2;
        ground.position.y = -0.04;
        ground.receiveShadow = true;
        ground.userData.persistent = true;
        this.scene.add(ground);

        const gridHelper = new THREE.GridHelper(6000, 120, isDark ? 0x334155 : 0x64748b, isDark ? 0x172033 : 0x94a3b8);
        gridHelper.userData.persistent = true;
        gridHelper.userData.themeRole = 'grid';
        this.scene.add(gridHelper);

        const ambient = new THREE.HemisphereLight(isDark ? 0x93c5fd : 0xffffff, isDark ? 0x0f172a : 0x64748b, 1.15);
        this.scene.add(ambient);

        const dir1 = new THREE.DirectionalLight(0xffffff, 1.3);
        dir1.position.set(400, 800, 400);
        dir1.castShadow = true;
        dir1.shadow.mapSize.set(1024, 1024);
        this.scene.add(dir1);

        const dir2 = new THREE.DirectionalLight(0x38bdf8, 0.6);
        dir2.position.set(-400, 300, -400);
        this.scene.add(dir2);

        const applyTheme = () => {
            const dark = document.documentElement.dataset.theme === 'dark';
            if (!this.scene || !this.renderer) return;
            const color = dark ? 0x07111f : 0xdbeafe;
            this.scene.background.setHex(color);
            this.scene.fog.color.setHex(color);
            ground.material.color.setHex(dark ? 0x0b1728 : 0xcbd5e1);
            ambient.color.setHex(dark ? 0x7895b5 : 0xffffff);
            ambient.groundColor.setHex(dark ? 0x07111f : 0x64748b);
            ambient.intensity = dark ? 0.58 : 1.15;
            dir1.color.setHex(dark ? 0xb8c9dc : 0xffffff);
            dir1.intensity = dark ? 0.78 : 1.3;
            dir2.color.setHex(dark ? 0x1d4f6f : 0x38bdf8);
            dir2.intensity = dark ? 0.28 : 0.6;
            this.renderer.toneMappingExposure = dark ? 0.72 : 0.95;

            this.scene.traverse((object) => {
                const material = object.material;
                if (!material) return;
                const materials = Array.isArray(material) ? material : [material];
                const role = object.userData.themeRole;
                materials.forEach((item) => {
                    if (!item?.color) return;
                    if (object.userData.terrain) item.color.setHex(dark ? 0x53615b : 0xffffff);
                    if (role === 'tree-trunk') item.color.setHex(dark ? 0x3b2b22 : 0x6b4423);
                    if (role === 'tree-canopy') item.color.setHex(dark ? 0x153c2b : 0x245c32);
                    if (role === 'road') item.color.setHex(dark ? 0x151a20 : 0x343a40);
                    if (role === 'road-edge') item.color.setHex(dark ? 0x64748b : 0xcbd5e1);
                });
            });
        };
        new MutationObserver(applyTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        const animate = () => {
            this.animationFrameId = requestAnimationFrame(animate);
            if (this.controls) this.controls.update();
            this.renderer.render(this.scene, this.camera);
        };
        animate();

        window.addEventListener('resize', () => {
            if (!this.camera || !this.renderer) return;
            const w = this.container.clientWidth;
            const h = this.container.clientHeight || 750;
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(w, h);
        });
    }

    handleClick(e) {
        if (!this.renderer || !this.camera || !this.scene) return;
        const escapeFields = record => Object.fromEntries(Object.entries(record).map(([key, value]) => [key,
            typeof value === 'string' ? value.replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]) : value]));

        const rect = this.renderer.domElement.getBoundingClientRect();
        const mouse = new THREE.Vector2(
            ((e.clientX - rect.left) / rect.width) * 2 - 1,
            -((e.clientY - rect.top) / rect.height) * 2 + 1
        );

        this.raycaster.setFromCamera(mouse, this.camera);
        const intersects = this.raycaster.intersectObjects(this.scene.children, true);

        let found = false;

        for (let i = 0; i < intersects.length; i++) {
            let obj = intersects[i].object;
            while (obj && !obj.userData.type) {
                obj = obj.parent;
            }

            if (obj && obj.userData.type) {
                const item = obj.userData;
                let html = '';
                let title = '';
                let editUrl = '';

                if (item.type === 'poste') {
                    title = 'Poste de distribuição';
                    const d = escapeFields(item.data);
                    html = `<b>Código Patrimônio:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                            <b>Altura:</b> ${d.altura_metros ? `${Number(d.altura_metros).toFixed(2)} m` : 'estimada em 10 m'}<br>
                            <b>Material:</b> ${d.material || 'não informado'}<br>
                            <b>Resistência:</b> ${d.resistencia_kg ? `${d.resistencia_kg} kg` : 'não informada'}<br>
                            <b>Iluminação:</b> ${d.possui_iluminacao ? 'sim' : 'não'}<br>
                            <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                    editUrl = `/admin/postes/${d.id}/edit`;
                } else if (item.type === 'cabo') {
                    title = 'Cabo / fiação';
                    const d = escapeFields(item.data);
                    html = `<b>Identificador:</b> ${d.nome || d.tipo_cabo}<br>
                            <b>Tipo Fiação:</b> ${d.tipo_cabo}<br>
                            <b>Estado de Conservação:</b> ${d.estado_conservacao || 'bom'}`;
                    editUrl = `/admin/cabeamentos/${d.id}/edit`;
                } else if (item.type === 'equipamento') {
                    title = 'Equipamento de rede';
                    const d = escapeFields(item.data);
                    html = `<b>Nome:</b> ${d.nome}<br>
                            <b>Tipo Equipamento:</b> ${d.tipo}<br>
                            <b>Modelo:</b> ${d.modelo || 'não informado'}<br>
                            <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                    editUrl = `/admin/equipamento-infraestruturas/${d.id}/edit`;
                } else if (item.type === 'central') {
                    title = 'Central / estação';
                    const d = escapeFields(item.data);
                    html = `<b>Nome:</b> ${d.nome}<br>
                            <b>Tipo Central:</b> ${d.tipo}<br>
                            <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                    editUrl = `/admin/central-distribuicaos/${d.id}/edit`;
                } else if (item.type === 'antena') {
                    title = 'Antena de telecomunicações';
                    const d = escapeFields(item.data);
                    html = `<b>Código Antena:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                            <b>Tipo de Sinal:</b> ${d.tipo_sinal}<br>
                            <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                    editUrl = `/admin/antenas/${d.id}/edit`;
                } else if (item.type === 'via') {
                    title = 'Via urbana';
                    const d = escapeFields(item.data);
                    html = `<b>Nome:</b> ${d.nome || 'Via sem nome'}<br>
                            <b>Fluxo:</b> ${d.nivel_congestionamento || 'não informado'}<br>
                            <b>Velocidade média:</b> ${d.velocidade_media ? `${d.velocidade_media} km/h` : 'não informada'}`;
                    editUrl = `/admin/via-transitos/${d.id}/edit`;
                } else if (item.type === 'building') {
                    title = 'Construção OpenStreetMap';
                    const d = escapeFields(item.data);
                    const sourceLabel = d.height_source === 'osm'
                        ? 'informada no OSM'
                        : (d.height_source === 'levels' ? 'calculada pelos níveis' : 'altura visual padrão');
                    html = `<b>Tipo:</b> ${d.building || 'construção'}<br>
                            <b>OSM ID:</b> ${d.osm_type || 'objeto'} ${d.osm_id || ''}<br>
                            <b>Altura cadastrada:</b> ${d.height ? `${Number(d.height).toFixed(2)} m` : 'não informada'}<br>
                            <b>Altura renderizada:</b> ${Number(d.render_height || 0).toFixed(2)} m<br>
                            <b>Níveis:</b> ${d.building_levels ?? 'não informado'}<br>
                            <b>Origem da altura:</b> ${sourceLabel}`;
                }

                if (html && this.tooltip) {
                    this.tooltip.innerHTML = `
                        <div style="font-weight: bold; border-bottom: 1px solid var(--tooltip-border); padding-bottom: 6px; margin-bottom: 8px; color: var(--tooltip-title); font-size: 13px;">
                            ${title}
                        </div>
                        <div style="line-height: 1.5; color: var(--tooltip-text); font-size: 11px;">
                            ${html}
                        </div>
                        ${editUrl ? `
                        <div style="margin-top: 12px; text-align: right;">
                            <a href="${editUrl}" target="_blank" style="display: inline-block; background: #2563eb; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 11px; transition: background 0.2s;">
                                Editar registro
                            </a>
                        </div>` : ''}
                    `;
                    this.tooltip.style.left = (e.clientX - rect.left + 15) + 'px';
                    this.tooltip.style.top = (e.clientY - rect.top + 15) + 'px';
                    this.tooltip.style.display = 'block';
                    found = true;
                    break;
                }
            }
        }

        if (!found && this.tooltip) {
            this.tooltip.style.display = 'none';
        }
    }

    async loadTerrain(bounds) {
        if (!this.container || this.container.dataset.terrainEnabled !== 'true') return null;

        const zoom = Number(this.container.dataset.terrainZoom || 12);
        const template = this.container.dataset.terrainTileUrl;
        const scale = 2 ** zoom;
        const project = (lat, lon) => {
            const sin = Math.sin(Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI / 180);
            return {
                x: ((lon + 180) / 360) * scale,
                y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
            };
        };

        const northWest = project(bounds.maxLat, bounds.minLon);
        const southEast = project(bounds.minLat, bounds.maxLon);
        const minTileX = Math.floor(northWest.x);
        const maxTileX = Math.floor(southEast.x);
        const minTileY = Math.floor(northWest.y);
        const maxTileY = Math.floor(southEast.y);
        const requestKey = `${zoom}:${minTileX}:${maxTileX}:${minTileY}:${maxTileY}`;
        if (this.terrainRequestKey === requestKey && this.terrainSampler) return this.terrainSampler;

        const tiles = new Map();
        const requests = [];
        for (let x = minTileX; x <= maxTileX; x++) {
            for (let y = minTileY; y <= maxTileY; y++) {
                requests.push(new Promise((resolve) => {
                    const image = new Image();
                    image.crossOrigin = 'anonymous';
                    image.onload = () => {
                        try {
                            const canvas = document.createElement('canvas');
                            canvas.width = image.naturalWidth;
                            canvas.height = image.naturalHeight;
                            const context = canvas.getContext('2d', { willReadFrequently: true });
                            context.drawImage(image, 0, 0);
                            tiles.set(`${x}:${y}`, context.getImageData(0, 0, canvas.width, canvas.height));
                        } catch (error) {
                            console.warn('Tile de relevo não pôde ser lido.', error);
                        }
                        resolve();
                    };
                    image.onerror = resolve;
                    image.src = template.replace('{z}', zoom).replace('{x}', x).replace('{y}', y);
                }));
            }
        }
        await Promise.all(requests);

        const sampler = (lat, lon) => {
            const point = project(lat, lon);
            const tileX = Math.floor(point.x);
            const tileY = Math.floor(point.y);
            const tile = tiles.get(`${tileX}:${tileY}`);
            if (!tile) return 0;
            const px = Math.max(0, Math.min(tile.width - 1, Math.floor((point.x - tileX) * tile.width)));
            const py = Math.max(0, Math.min(tile.height - 1, Math.floor((point.y - tileY) * tile.height)));
            const offset = (py * tile.width + px) * 4;
            return (tile.data[offset] * 256 + tile.data[offset + 1] + tile.data[offset + 2] / 256) - 32768;
        };
        this.terrainRequestKey = requestKey;
        this.terrainSampler = sampler;
        return sampler;
    }

    async render(data, buildingsEnabled = true) {
        if (!this.scene) return;

        const toRemove = [];
        this.scene.traverse((child) => {
            if ((child.isMesh || child.isLine || child.isGroup) && !child.userData.persistent && child.parent === this.scene) {
                toRemove.push(child);
            }
        });
        toRemove.forEach((child) => this.scene.remove(child));

        let centerLat = -16.070830;
        let centerLon = -57.687500;

        if (data.postes && data.postes.length > 0) {
            let sumLat = 0, sumLon = 0, count = 0;
            data.postes.forEach((p) => {
                if (p.latitude && p.longitude) {
                    sumLat += parseFloat(p.latitude);
                    sumLon += parseFloat(p.longitude);
                    count++;
                }
            });
            if (count > 0) {
                centerLat = sumLat / count;
                centerLon = sumLon / count;
            }
        }

        const getLocalCoords = (lat, lon) => {
            const latRad = centerLat * Math.PI / 180;
            const metersPerDegreeLon = 111320 * Math.cos(latRad);
            const metersPerDegreeLat = 110540;
            const x = (parseFloat(lon) - centerLon) * metersPerDegreeLon;
            const z = -(parseFloat(lat) - centerLat) * metersPerDegreeLat;
            return { x, z };
        };
        const getGeoCoords = (x, z) => {
            const latRad = centerLat * Math.PI / 180;
            const metersPerDegreeLon = 111320 * Math.cos(latRad);
            const metersPerDegreeLat = 110540;
            return {
                lon: centerLon + Number(x) / metersPerDegreeLon,
                lat: centerLat - Number(z) / metersPerDegreeLat,
            };
        };

        const validPoles = (data.postes || []).filter((p) => p.latitude && p.longitude);
        const latitudes = validPoles.map((p) => Number(p.latitude));
        const longitudes = validPoles.map((p) => Number(p.longitude));
        const terrainBounds = validPoles.length ? {
            minLat: Math.min(...latitudes) - 0.001,
            maxLat: Math.max(...latitudes) + 0.001,
            minLon: Math.min(...longitudes) - 0.001,
            maxLon: Math.max(...longitudes) + 0.001,
        } : null;

        const terrainSampler = terrainBounds ? await this.loadTerrain(terrainBounds) : null;
        const terrainExaggeration = Number(this.container?.dataset.terrainExaggeration || 1.35);
        let terrainReference = 0;
        let groundHeightResolver = () => 0;
        const roadSegments = [];

        (data.vias || []).forEach((via) => {
            try {
                const geometry = typeof via.geojson === 'string' ? JSON.parse(via.geojson) : via.geojson;
                const lines = geometry?.type === 'MultiLineString' ? geometry.coordinates : [geometry?.coordinates];
                lines.forEach((coordinates) => {
                    for (let index = 1; index < (coordinates?.length || 0); index++) {
                        roadSegments.push([
                            getLocalCoords(coordinates[index - 1][1], coordinates[index - 1][0]),
                            getLocalCoords(coordinates[index][1], coordinates[index][0]),
                        ]);
                    }
                });
            } catch (error) {}
        });

        if (terrainSampler && terrainBounds) {
            const samples = validPoles.map((p) => terrainSampler(Number(p.latitude), Number(p.longitude))).filter(Number.isFinite);
            terrainReference = samples.length ? Math.min(...samples) : 0;
            const columns = 48;
            const rows = 48;
            const vertices = [];
            const colors = [];
            const indices = [];
            const elevationGrid = [];

            for (let row = 0; row < rows; row++) {
                const lat = terrainBounds.maxLat - (row / (rows - 1)) * (terrainBounds.maxLat - terrainBounds.minLat);
                for (let column = 0; column < columns; column++) {
                    const lon = terrainBounds.minLon + (column / (columns - 1)) * (terrainBounds.maxLon - terrainBounds.minLon);
                    const local = getLocalCoords(lat, lon);
                    const elevation = Math.max(0, (terrainSampler(lat, lon) - terrainReference) * terrainExaggeration);
                    elevationGrid.push(elevation);
                    vertices.push(local.x, elevation, local.z);
                }
            }

            groundHeightResolver = (lat, lon) => {
                const columnPosition = ((Number(lon) - terrainBounds.minLon) / (terrainBounds.maxLon - terrainBounds.minLon)) * (columns - 1);
                const rowPosition = ((terrainBounds.maxLat - Number(lat)) / (terrainBounds.maxLat - terrainBounds.minLat)) * (rows - 1);
                const column0 = Math.max(0, Math.min(columns - 1, Math.floor(columnPosition)));
                const row0 = Math.max(0, Math.min(rows - 1, Math.floor(rowPosition)));
                const column1 = Math.min(columns - 1, column0 + 1);
                const row1 = Math.min(rows - 1, row0 + 1);
                const tx = Math.max(0, Math.min(1, columnPosition - column0));
                const ty = Math.max(0, Math.min(1, rowPosition - row0));
                const a = elevationGrid[row0 * columns + column0];
                const b = elevationGrid[row0 * columns + column1];
                const c = elevationGrid[row1 * columns + column0];
                const d = elevationGrid[row1 * columns + column1];
                return tx + ty <= 1
                    ? a + (b - a) * tx + (c - a) * ty
                    : d + (c - d) * (1 - tx) + (b - d) * (1 - ty);
            };

            const minElevation = Math.min(...elevationGrid);
            const maxElevation = Math.max(...elevationGrid);
            const elevationRange = Math.max(1, maxElevation - minElevation);
            const lowColor = new THREE.Color(0x3f6f45);
            const middleColor = new THREE.Color(0x789653);
            const highColor = new THREE.Color(0x9b815d);

            elevationGrid.forEach((elevation, index) => {
                const row = Math.floor(index / columns);
                const column = index % columns;
                const right = elevationGrid[row * columns + Math.min(columns - 1, column + 1)] ?? elevation;
                const down = elevationGrid[Math.min(rows - 1, row + 1) * columns + column] ?? elevation;
                const slope = Math.min(1, (Math.abs(right - elevation) + Math.abs(down - elevation)) / 12);
                const normalized = (elevation - minElevation) / elevationRange;
                const color = normalized < 0.55
                    ? lowColor.clone().lerp(middleColor, normalized / 0.55)
                    : middleColor.clone().lerp(highColor, (normalized - 0.55) / 0.45);
                color.multiplyScalar(1 - slope * 0.2);
                colors.push(color.r, color.g, color.b);
            });

            for (let row = 0; row < rows - 1; row++) {
                for (let column = 0; column < columns - 1; column++) {
                    const a = row * columns + column;
                    const b = a + 1;
                    const c = a + columns;
                    const d = c + 1;
                    indices.push(a, c, b, b, c, d);
                }
            }

            const terrainGeometry = new THREE.BufferGeometry();
            terrainGeometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
            terrainGeometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
            terrainGeometry.setIndex(indices);
            terrainGeometry.computeVertexNormals();
            const terrain = new THREE.Mesh(
                terrainGeometry,
                new THREE.MeshStandardMaterial({
                    color: document.documentElement.dataset.theme === 'dark' ? 0x53615b : 0xffffff,
                    vertexColors: true,
                    roughness: 0.96,
                    metalness: 0,
                    side: THREE.DoubleSide,
                })
            );
            terrain.receiveShadow = true;
            terrain.userData = { terrain: true };
            this.scene.add(terrain);

            // Trees
            const treeCount = Math.min(180, Math.max(50, Math.round(validPoles.length * 0.35)));
            const trunk = new THREE.InstancedMesh(
                new THREE.CylinderGeometry(0.11, 0.16, 1, 6),
                new THREE.MeshStandardMaterial({
                    color: document.documentElement.dataset.theme === 'dark' ? 0x3b2b22 : 0x6b4423,
                    roughness: 1,
                }),
                treeCount
            );
            const canopy = new THREE.InstancedMesh(
                new THREE.ConeGeometry(1, 2.4, 7),
                new THREE.MeshStandardMaterial({
                    color: document.documentElement.dataset.theme === 'dark' ? 0x153c2b : 0x245c32,
                    roughness: 0.92,
                }),
                treeCount
            );
            const matrix = new THREE.Matrix4();
            let planted = 0;
            for (let attempt = 0; attempt < treeCount * 5 && planted < treeCount; attempt++) {
                const randomA = Math.abs(Math.sin((attempt + 1) * 12.9898) * 43758.5453) % 1;
                const randomB = Math.abs(Math.sin((attempt + 1) * 78.233) * 12345.6789) % 1;
                const lat = terrainBounds.minLat + randomA * (terrainBounds.maxLat - terrainBounds.minLat);
                const lon = terrainBounds.minLon + randomB * (terrainBounds.maxLon - terrainBounds.minLon);
                const local = getLocalCoords(lat, lon);
                const tooClose = validPoles.some((p) => {
                    const poleLocal = getLocalCoords(p.latitude, p.longitude);
                    return Math.hypot(local.x - poleLocal.x, local.z - poleLocal.z) < 7;
                });
                const onRoad = roadSegments.some(([start, end]) => {
                    const dx = end.x - start.x;
                    const dz = end.z - start.z;
                    const lengthSquared = dx * dx + dz * dz;
                    const projection = lengthSquared
                        ? Math.max(0, Math.min(1, ((local.x - start.x) * dx + (local.z - start.z) * dz) / lengthSquared))
                        : 0;
                    return Math.hypot(local.x - (start.x + projection * dx), local.z - (start.z + projection * dz)) < 5;
                });
                if (tooClose || onRoad) continue;

                const groundY = groundHeightResolver(lat, lon);
                const treeHeight = 3.2 + ((attempt * 37) % 28) / 10;
                matrix.compose(
                    new THREE.Vector3(local.x, groundY + treeHeight * 0.22, local.z),
                    new THREE.Quaternion(),
                    new THREE.Vector3(1, treeHeight * 0.44, 1)
                );
                trunk.setMatrixAt(planted, matrix);
                matrix.compose(
                    new THREE.Vector3(local.x, groundY + treeHeight * 0.72, local.z),
                    new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), randomA * Math.PI),
                    new THREE.Vector3(treeHeight * 0.42, treeHeight * 0.58, treeHeight * 0.42)
                );
                canopy.setMatrixAt(planted, matrix);
                planted++;
            }
            trunk.count = planted;
            canopy.count = planted;
            trunk.userData.themeRole = 'tree-trunk';
            canopy.userData.themeRole = 'tree-canopy';
            trunk.instanceMatrix.needsUpdate = true;
            canopy.instanceMatrix.needsUpdate = true;
            trunk.castShadow = true;
            canopy.castShadow = true;
            this.scene.add(trunk, canopy);
        }

        const getGroundHeight = (lat, lon) => groundHeightResolver(Number(lat), Number(lon));
        const cylinderGeo = new THREE.CylinderGeometry(0.12, 0.18, 1, 8);

        const poleMaterials = {
            concreto: new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: 0.82 }),
            madeira: new THREE.MeshStandardMaterial({ color: 0x8b5a2b, roughness: 0.92 }),
            ferro: new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.72, roughness: 0.34 }),
            fibra: new THREE.MeshStandardMaterial({ color: 0x0891b2, roughness: 0.5 }),
            default: new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.72 }),
        };

        const poleDetails = {
            crossarm: new THREE.BoxGeometry(2.2, 0.12, 0.12),
            insulator: new THREE.CylinderGeometry(0.07, 0.09, 0.25, 8),
            lampArm: new THREE.CylinderGeometry(0.035, 0.035, 1.6, 8),
            lamp: new THREE.SphereGeometry(0.16, 12, 8),
        };
        const detailMaterials = {
            metal: new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.75, roughness: 0.3 }),
            ceramic: new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.28 }),
            lamp: new THREE.MeshStandardMaterial({ color: 0xfef3c7, emissive: 0xfbbf24, emissiveIntensity: 1.25 }),
        };

        const eqGeometries = {
            transformador: new THREE.BoxGeometry(0.65, 0.65, 0.65),
            cto: new THREE.BoxGeometry(0.35, 0.35, 0.22),
            caixaEmenda: new THREE.CylinderGeometry(0.12, 0.12, 0.4, 8),
            medidor: new THREE.BoxGeometry(0.16, 0.16, 0.1),
            central: new THREE.BoxGeometry(6, 4, 6),
            antenaBase: new THREE.CylinderGeometry(0.05, 0.45, 18, 4, 1, true),
            antenaSignal: new THREE.SphereGeometry(0.28, 8, 8),
        };

        const eqMaterials = {
            transformador: new THREE.MeshStandardMaterial({ color: 0x4f46e5, metalness: 0.5, roughness: 0.2 }),
            cto: new THREE.MeshStandardMaterial({ color: 0xec4899 }),
            caixaEmenda: new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.8 }),
            medidor: new THREE.MeshStandardMaterial({ color: 0x10b981 }),
            central: new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.1, transparent: true, opacity: 0.8 }),
            antenaSignal: new THREE.MeshBasicMaterial({ color: 0xec4899 }),
            wireframeTower: new THREE.MeshBasicMaterial({ color: 0x475569, wireframe: true }),
        };

        const cableMaterials = {
            fibra_optica: new THREE.LineBasicMaterial({ color: 0x06b6d4, linewidth: 2 }),
            fibra_backbone: new THREE.LineBasicMaterial({ color: 0xfacc15, linewidth: 2 }),
            eletrico_alta_tensao: new THREE.LineBasicMaterial({ color: 0xef4444, linewidth: 2 }),
            eletrico_baixa_tensao: new THREE.LineBasicMaterial({ color: 0x3b82f6, linewidth: 2 }),
            telefonia: new THREE.LineBasicMaterial({ color: 0xa855f7, linewidth: 2 }),
            default: new THREE.LineBasicMaterial({ color: 0x64748b, linewidth: 2 }),
        };

        const roadMaterials = {
            asphalt: new THREE.MeshStandardMaterial({
                color: document.documentElement.dataset.theme === 'dark' ? 0x151a20 : 0x343a40,
                roughness: 0.94,
                metalness: 0,
            }),
            edge: new THREE.LineBasicMaterial({
                color: document.documentElement.dataset.theme === 'dark' ? 0x64748b : 0xcbd5e1,
                transparent: true,
                opacity: 0.7,
            }),
        };

        // 3D Buildings
        if (terrainBounds && buildingsEnabled) {
            try {
                const res = await fetch(`${this.container.dataset.buildingsUrl}?west=${terrainBounds.minLon}&south=${terrainBounds.minLat}&east=${terrainBounds.maxLon}&north=${terrainBounds.maxLat}`, {
                    headers: { Accept: 'application/json' },
                });
                if (res.ok) {
                    const collection = await res.json();
                    const buildings = Array.isArray(collection.features) ? collection.features : [];
                    const buildingMaterial = new THREE.MeshStandardMaterial({
                        color: document.documentElement.dataset.theme === 'dark' ? 0x64748b : 0xa8a29e,
                        roughness: 0.88,
                        metalness: 0.02,
                    });

                    buildings.forEach((feature) => {
                        const polygons = feature.geometry?.type === 'MultiPolygon'
                            ? feature.geometry.coordinates
                            : (feature.geometry?.type === 'Polygon' ? [feature.geometry.coordinates] : []);
                        polygons.forEach((rings) => {
                            const outer = rings?.[0];
                            if (!outer || outer.length < 4) return;
                            const shape = new THREE.Shape();
                            outer.forEach((coordinate, index) => {
                                const local = getLocalCoords(coordinate[1], coordinate[0]);
                                if (index === 0) shape.moveTo(local.x, -local.z);
                                else shape.lineTo(local.x, -local.z);
                            });
                            (rings.slice(1) || []).forEach((inner) => {
                                if (!inner || inner.length < 4) return;
                                const hole = new THREE.Path();
                                inner.forEach((coordinate, index) => {
                                    const local = getLocalCoords(coordinate[1], coordinate[0]);
                                    if (index === 0) hole.moveTo(local.x, -local.z);
                                    else hole.lineTo(local.x, -local.z);
                                });
                                shape.holes.push(hole);
                            });

                            const height = Math.max(1, Number(feature.properties?.render_height) || 6);
                            const geometry = new THREE.ExtrudeGeometry(shape, {
                                depth: height,
                                bevelEnabled: false,
                                curveSegments: 1,
                            });
                            geometry.rotateX(-Math.PI / 2);
                            const sampledGround = outer
                                .filter((_, index) => index % Math.max(1, Math.floor(outer.length / 12)) === 0)
                                .map((coordinate) => getGroundHeight(coordinate[1], coordinate[0]));
                            const building = new THREE.Mesh(geometry, buildingMaterial);
                            building.position.y = sampledGround.length ? Math.max(...sampledGround) + 0.04 : 0.04;
                            building.castShadow = true;
                            building.receiveShadow = true;
                            building.userData = { type: 'building', data: feature.properties || {} };
                            this.scene.add(building);
                        });
                    });
                }
            } catch (error) {
                console.error('Erro ao adicionar construções à cena 3D.', error);
            }
        }

        // 1. Roads
        if (data.vias) {
            data.vias.forEach((v) => {
                if (!v.geojson) return;
                try {
                    const geo = typeof v.geojson === 'string' ? JSON.parse(v.geojson) : v.geojson;
                    const lineSets = geo.type === 'MultiLineString' ? geo.coordinates : [geo.coordinates];

                    let colorHex = 0x10b981;
                    if (v.nivel_congestionamento === 'moderado') colorHex = 0xfacc15;
                    if (v.nivel_congestionamento === 'intenso') colorHex = 0xf97316;
                    if (v.nivel_congestionamento === 'parado') colorHex = 0xef4444;

                    lineSets.forEach((coords) => {
                        if (!coords || coords.length < 2) return;
                        const roadWidth = 6;
                        const denseCoords = [coords[0]];
                        const roadSampleSpacing = 6;
                        for (let segmentIndex = 1; segmentIndex < coords.length; segmentIndex++) {
                            const startCoordinate = coords[segmentIndex - 1];
                            const endCoordinate = coords[segmentIndex];
                            const startLocal = getLocalCoords(startCoordinate[1], startCoordinate[0]);
                            const endLocal = getLocalCoords(endCoordinate[1], endCoordinate[0]);
                            const segmentLength = Math.hypot(endLocal.x - startLocal.x, endLocal.z - startLocal.z);
                            const divisions = Math.max(1, Math.ceil(segmentLength / roadSampleSpacing));
                            for (let division = 1; division <= divisions; division++) {
                                const ratio = division / divisions;
                                denseCoords.push([
                                    startCoordinate[0] + (endCoordinate[0] - startCoordinate[0]) * ratio,
                                    startCoordinate[1] + (endCoordinate[1] - startCoordinate[1]) * ratio,
                                ]);
                            }
                        }
                        const ribbonVertices = [];
                        const ribbonIndices = [];
                        const centerPoints = [];
                        const leftEdge = [];
                        const rightEdge = [];

                        denseCoords.forEach((coordinate, index) => {
                            const previous = denseCoords[Math.max(0, index - 1)];
                            const next = denseCoords[Math.min(denseCoords.length - 1, index + 1)];
                            const before = getLocalCoords(previous[1], previous[0]);
                            const after = getLocalCoords(next[1], next[0]);
                            const length = Math.hypot(after.x - before.x, after.z - before.z) || 1;
                            const normalX = -(after.z - before.z) / length;
                            const normalZ = (after.x - before.x) / length;
                            const local = getLocalCoords(coordinate[1], coordinate[0]);
                            const leftX = local.x + normalX * roadWidth / 2;
                            const leftZ = local.z + normalZ * roadWidth / 2;
                            const rightX = local.x - normalX * roadWidth / 2;
                            const rightZ = local.z - normalZ * roadWidth / 2;
                            const leftGeo = getGeoCoords(leftX, leftZ);
                            const rightGeo = getGeoCoords(rightX, rightZ);
                            const roadClearance = 0.22;
                            const leftY = getGroundHeight(leftGeo.lat, leftGeo.lon) + roadClearance;
                            const rightY = getGroundHeight(rightGeo.lat, rightGeo.lon) + roadClearance;
                            const left = new THREE.Vector3(leftX, leftY, leftZ);
                            const right = new THREE.Vector3(rightX, rightY, rightZ);
                            ribbonVertices.push(left.x, left.y, left.z, right.x, right.y, right.z);
                            leftEdge.push(left);
                            rightEdge.push(right);
                            centerPoints.push(new THREE.Vector3(
                                local.x,
                                getGroundHeight(coordinate[1], coordinate[0]) + roadClearance + 0.045,
                                local.z
                            ));
                            if (index < denseCoords.length - 1) {
                                const base = index * 2;
                                ribbonIndices.push(base, base + 2, base + 1, base + 1, base + 2, base + 3);
                            }
                        });

                        const roadGeometry = new THREE.BufferGeometry();
                        roadGeometry.setAttribute('position', new THREE.Float32BufferAttribute(ribbonVertices, 3));
                        roadGeometry.setIndex(ribbonIndices);
                        roadGeometry.computeVertexNormals();
                        const road = new THREE.Mesh(roadGeometry, roadMaterials.asphalt);
                        road.receiveShadow = true;
                        road.userData = { type: 'via', data: v, themeRole: 'road' };
                        this.scene.add(road);

                        const leftRoadEdge = new THREE.Line(new THREE.BufferGeometry().setFromPoints(leftEdge), roadMaterials.edge);
                        const rightRoadEdge = new THREE.Line(new THREE.BufferGeometry().setFromPoints(rightEdge), roadMaterials.edge);
                        leftRoadEdge.userData.themeRole = 'road-edge';
                        rightRoadEdge.userData.themeRole = 'road-edge';
                        this.scene.add(leftRoadEdge, rightRoadEdge);
                        this.scene.add(new THREE.Line(
                            new THREE.BufferGeometry().setFromPoints(centerPoints),
                            new THREE.LineBasicMaterial({ color: colorHex, transparent: true, opacity: 0.9 })
                        ));
                    });
                } catch (e) {}
            });
        }

        // 2. Centrais
        if (data.centrais) {
            data.centrais.forEach((c) => {
                let x = 0, z = 0;
                if (c.latitude && c.longitude) {
                    const pos = getLocalCoords(c.latitude, c.longitude);
                    x = pos.x; z = pos.z;
                } else if (c.geojson) {
                    try {
                        const geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                        if (geo.coordinates && geo.coordinates[0]) {
                            const pos = getLocalCoords(geo.coordinates[0][1], geo.coordinates[0][0]);
                            x = pos.x; z = pos.z;
                        }
                    } catch (e) {}
                }

                const box = new THREE.Mesh(eqGeometries.central, eqMaterials.central);
                const centralGround = c.latitude && c.longitude ? getGroundHeight(c.latitude, c.longitude) : 0;
                box.position.set(x, centralGround + 2, z);
                box.castShadow = true;
                box.receiveShadow = true;
                box.userData = { type: 'central', data: c };
                this.scene.add(box);
            });
        }

        // 3. Postes
        const polesMap = {};
        if (data.postes) {
            data.postes.forEach((p) => {
                if (!p.latitude || !p.longitude) return;
                const pos = getLocalCoords(p.latitude, p.longitude);
                const height = parseFloat(p.altura_metros) || 10;
                const groundHeight = getGroundHeight(p.latitude, p.longitude);

                const material = poleMaterials[p.material] || poleMaterials.default;
                const cylinder = new THREE.Mesh(cylinderGeo, material);
                const poleGroup = new THREE.Group();

                cylinder.scale.set(1, height, 1);
                cylinder.position.set(0, height / 2, 0);
                cylinder.castShadow = true;
                poleGroup.add(cylinder);

                const crossarm = new THREE.Mesh(poleDetails.crossarm, detailMaterials.metal);
                crossarm.position.y = height - 0.55;
                crossarm.castShadow = true;
                poleGroup.add(crossarm);

                [-0.82, 0, 0.82].forEach((offset) => {
                    const insulator = new THREE.Mesh(poleDetails.insulator, detailMaterials.ceramic);
                    insulator.position.set(offset, height - 0.36, 0);
                    poleGroup.add(insulator);
                });

                if (p.possui_iluminacao) {
                    const lampArm = new THREE.Mesh(poleDetails.lampArm, detailMaterials.metal);
                    lampArm.rotation.z = Math.PI / 2;
                    lampArm.position.set(0.72, height - 1.15, 0);
                    poleGroup.add(lampArm);

                    const lamp = new THREE.Mesh(poleDetails.lamp, detailMaterials.lamp);
                    lamp.scale.set(1.5, 0.55, 0.9);
                    lamp.position.set(1.5, height - 1.15, 0);
                    poleGroup.add(lamp);
                }

                if (['ruim', 'critico'].includes(p.estado_conservacao)) {
                    const warning = new THREE.Mesh(
                        new THREE.TorusGeometry(0.36, 0.055, 8, 20),
                        new THREE.MeshBasicMaterial({ color: p.estado_conservacao === 'critico' ? 0xef4444 : 0xf97316 })
                    );
                    warning.rotation.x = Math.PI / 2;
                    warning.position.y = 0.12;
                    poleGroup.add(warning);
                }

                poleGroup.position.set(pos.x, groundHeight, pos.z);
                poleGroup.userData = { type: 'poste', data: p };
                this.scene.add(poleGroup);

                polesMap[p.id] = {
                    id: p.id,
                    x: pos.x,
                    z: pos.z,
                    height: height,
                    groundHeight: groundHeight,
                };
            });
        }

        // 4. Equipamentos
        if (data.equipamentos) {
            data.equipamentos.forEach((eq) => {
                if (eq.poste_id && polesMap[eq.poste_id]) {
                    const pole = polesMap[eq.poste_id];
                    const x = pole.x;
                    const z = pole.z;
                    let y = 0.5;
                    let mesh = null;

                    if (eq.tipo === 'Transformador') {
                        y = pole.height - 2.2;
                        mesh = new THREE.Mesh(eqGeometries.transformador, eqMaterials.transformador);
                        mesh.position.set(x + 0.28, y, z);
                    } else if (eq.tipo === 'CTO') {
                        y = pole.height - 0.8;
                        mesh = new THREE.Mesh(eqGeometries.cto, eqMaterials.cto);
                        mesh.position.set(x - 0.22, y, z);
                    } else if (eq.tipo === 'Caixa_Emenda') {
                        y = pole.height - 1.4;
                        mesh = new THREE.Mesh(eqGeometries.caixaEmenda, eqMaterials.caixaEmenda);
                        mesh.rotation.z = Math.PI / 2;
                        mesh.position.set(x, y, z + 0.22);
                    } else if (eq.tipo === 'Medidor') {
                        y = 1.6;
                        mesh = new THREE.Mesh(eqGeometries.medidor, eqMaterials.medidor);
                        mesh.position.set(x, y, z - 0.2);
                    }

                    if (mesh) {
                        mesh.userData = { type: 'equipamento', data: eq };
                        this.scene.add(mesh);
                    }
                }
            });
        }

        // 5. Antenas
        if (data.antenas) {
            data.antenas.forEach((a) => {
                if (!a.latitude || !a.longitude) return;
                const pos = getLocalCoords(a.latitude, a.longitude);
                const height = 18;

                const tower = new THREE.Mesh(eqGeometries.antenaBase, eqMaterials.wireframeTower);
                const antennaGround = getGroundHeight(a.latitude, a.longitude);
                tower.position.set(pos.x, antennaGround + height / 2, pos.z);
                tower.userData = { type: 'antena', data: a };
                this.scene.add(tower);

                const signal = new THREE.Mesh(eqGeometries.antenaSignal, eqMaterials.antenaSignal);
                signal.position.set(pos.x, antennaGround + height, pos.z);
                signal.userData = { type: 'antena', data: a };
                this.scene.add(signal);
            });
        }

        // 6. Cabeamentos
        if (data.cabeamentos) {
            const cabosPorTrecho = {};
            data.cabeamentos.forEach((c) => {
                if (!c.geojson) return;

                const endpoints = [];
                if (c.poste_origem_id) endpoints.push('p_orig_' + c.poste_origem_id);
                if (c.poste_destino_id) endpoints.push('p_dest_' + c.poste_destino_id);
                if (c.central_origem_id) endpoints.push('c_orig_' + c.central_origem_id);
                if (c.central_destino_id) endpoints.push('c_dest_' + c.central_destino_id);

                let key = '';
                if (endpoints.length >= 2) {
                    key = endpoints.sort().join('_');
                } else {
                    try {
                        const geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                        if (geo.coordinates && geo.coordinates.length >= 2) {
                            const p1 = geo.coordinates[0];
                            const p2 = geo.coordinates[geo.coordinates.length - 1];
                            const c1 = p1[0].toFixed(5) + ',' + p1[1].toFixed(5);
                            const c2 = p2[0].toFixed(5) + ',' + p2[1].toFixed(5);
                            key = [c1, c2].sort().join('_');
                        }
                    } catch (err) {}
                }

                if (!key) key = 'cabo_single_' + c.id;
                if (!cabosPorTrecho[key]) cabosPorTrecho[key] = [];
                cabosPorTrecho[key].push(c);
            });

            Object.keys(cabosPorTrecho).forEach((key) => {
                const group = cabosPorTrecho[key];

                group.forEach((c, index) => {
                    try {
                        let startPos = null;
                        let endPos = null;

                        if (c.poste_origem_id && polesMap[c.poste_origem_id]) {
                            const p = polesMap[c.poste_origem_id];
                            startPos = new THREE.Vector3(p.x, p.groundHeight + p.height - (index * 0.45) - 0.3, p.z);
                        } else if (c.central_origem_id) {
                            const geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                            const pt = geo.coordinates[0];
                            const local = getLocalCoords(pt[1], pt[0]);
                            startPos = new THREE.Vector3(local.x, getGroundHeight(pt[1], pt[0]) + 2.8, local.z);
                        }

                        if (c.poste_destino_id && polesMap[c.poste_destino_id]) {
                            const p = polesMap[c.poste_destino_id];
                            endPos = new THREE.Vector3(p.x, p.groundHeight + p.height - (index * 0.45) - 0.3, p.z);
                        } else if (c.central_destino_id) {
                            const geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                            const pt = geo.coordinates[geo.coordinates.length - 1];
                            const local = getLocalCoords(pt[1], pt[0]);
                            endPos = new THREE.Vector3(local.x, getGroundHeight(pt[1], pt[0]) + 2.8, local.z);
                        }

                        if (!startPos || !endPos) {
                            const geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                            const coords = geo.coordinates;
                            if (coords && coords.length >= 2) {
                                const c1 = coords[0];
                                const c2 = coords[coords.length - 1];
                                const local1 = getLocalCoords(c1[1], c1[0]);
                                const local2 = getLocalCoords(c2[1], c2[0]);
                                startPos = new THREE.Vector3(local1.x, getGroundHeight(c1[1], c1[0]) + 9.5 - (index * 0.4), local1.z);
                                endPos = new THREE.Vector3(local2.x, getGroundHeight(c2[1], c2[0]) + 9.5 - (index * 0.4), local2.z);
                            }
                        }

                        if (startPos && endPos) {
                            const dist = startPos.distanceTo(endPos);
                            const sag = Math.max(0.08, dist * 0.035);

                            const midPoint = new THREE.Vector3(
                                (startPos.x + endPos.x) / 2,
                                ((startPos.y + endPos.y) / 2) - sag,
                                (startPos.z + endPos.z) / 2
                            );

                            const curve = new THREE.QuadraticBezierCurve3(startPos, midPoint, endPos);
                            const curvePoints = curve.getPoints(24);

                            const material = cableMaterials[c.tipo_cabo] || cableMaterials.default;
                            const isElectric = String(c.tipo_cabo || '').startsWith('eletrico_');
                            const cableObject = isElectric
                                ? new THREE.Mesh(
                                    new THREE.TubeGeometry(curve, 10, c.tipo_cabo === 'eletrico_alta_tensao' ? 0.075 : 0.05, 4, false),
                                    new THREE.MeshStandardMaterial({
                                        color: c.tipo_cabo === 'eletrico_alta_tensao' ? 0xef4444 : 0xfbbf24,
                                        emissive: c.tipo_cabo === 'eletrico_alta_tensao' ? 0x7f1d1d : 0x713f12,
                                        emissiveIntensity: 0.28,
                                        roughness: 0.48,
                                    })
                                )
                                : new THREE.Line(new THREE.BufferGeometry().setFromPoints(curvePoints), material);

                            cableObject.userData = { type: 'cabo', data: c };
                            this.scene.add(cableObject);
                        }
                    } catch (e) {}
                });
            });
        }

        // Camera Positioning
        if (data.postes && data.postes.length > 0) {
            let minX = Infinity, maxX = -Infinity, minZ = Infinity, maxZ = -Infinity;
            Object.keys(polesMap).forEach((id) => {
                const p = polesMap[id];
                if (p.x < minX) minX = p.x;
                if (p.x > maxX) maxX = p.x;
                if (p.z < minZ) minZ = p.z;
                if (p.z > maxZ) maxZ = p.z;
            });

            const centerX = (minX + maxX) / 2;
            const centerZ = (minZ + maxZ) / 2;

            if (this.controls) {
                this.controls.target.set(centerX, 0, centerZ);
                this.controls.update();
            }
            this.camera.position.set(centerX, Math.max(100, (maxZ - minZ) * 0.4), centerZ + Math.max(200, (maxZ - minZ) * 0.7));
        }
    }
}

if (typeof window !== 'undefined') {
    window.Infrastructure3DScene = Infrastructure3DScene;
}
