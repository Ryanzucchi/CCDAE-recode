import * as maplibregl from 'maplibre-gl';
import { sampleFeatures } from './surface-data.js';

function heightScale(value) {
    const normalized = Math.max(0, Math.min(1, value));
    const levels = 15;
    const position = normalized * levels;
    const level = Math.min(levels - 1, Math.floor(position));
    const progress = position - level;
    const maximum = (2 ** levels) - 1;
    const lower = level === 0 ? 0 : ((2 ** level) - 1) / maximum;
    const upper = ((2 ** (level + 1)) - 1) / maximum;
    const smoothProgress = progress * progress * (3 - 2 * progress);
    return lower + (upper - lower) * smoothProgress;
}

function buildSpatialIndex(points, influence) {
    const cellSize = influence * 1.5;
    const cells = new Map();
    for (const point of points) {
        const key = `${Math.floor(point.longitude / cellSize)}:${Math.floor(point.latitude / cellSize)}`;
        if (!cells.has(key)) cells.set(key, []);
        cells.get(key).push(point);
    }
    return { cells, cellSize };
}

function interpolate(index, longitude, latitude, influence) {
    let accumulatedPower = 0;
    const centerX = Math.floor(longitude / index.cellSize);
    const centerY = Math.floor(latitude / index.cellSize);
    for (let offsetX = -3; offsetX <= 3; offsetX++) {
        for (let offsetY = -3; offsetY <= 3; offsetY++) {
            const nearby = index.cells.get(`${centerX + offsetX}:${centerY + offsetY}`) ?? [];
            for (const point of nearby) {
                const dx = (longitude - point.longitude) * Math.cos(latitude * Math.PI / 180);
                const dy = latitude - point.latitude;
                const distanceSquared = dx * dx + dy * dy;
                const spatialWeight = Math.exp(-distanceSquared / (2 * influence * influence));
                const contribution = point.weight * spatialWeight;
                accumulatedPower += contribution ** 4;
            }
        }
    }
    return Math.min(1, accumulatedPower ** 0.25);
}

function buildWaveSurface(data, definition) {
    const points = data.features.map((feature) => ({
        longitude: Number(feature.geometry?.coordinates?.[0]),
        latitude: Number(feature.geometry?.coordinates?.[1]),
        weight: Number(feature.properties?.weight) || 0,
    })).filter((point) => Number.isFinite(point.longitude) && Number.isFinite(point.latitude));

    const padding = Math.max(0.018, definition.influence * 4.5);
    const west = Math.min(...points.map((point) => point.longitude)) - padding;
    const east = Math.max(...points.map((point) => point.longitude)) + padding;
    const south = Math.min(...points.map((point) => point.latitude)) - padding;
    const north = Math.max(...points.map((point) => point.latitude)) + padding;
    const divisions = 170;
    const longitudeStep = (east - west) / divisions;
    const latitudeStep = (north - south) / divisions;
    const positions = [];
    const normals = [];
    const weights = [];
    const indices = [];
    const spatialIndex = buildSpatialIndex(points, definition.influence);

    for (let row = 0; row <= divisions; row++) {
        for (let column = 0; column <= divisions; column++) {
            const longitude = west + column * longitudeStep;
            const latitude = south + row * latitudeStep;
            const value = interpolate(spatialIndex, longitude, latitude, definition.influence);
            const relativeHeight = heightScale(value);
            const coordinate = maplibregl.MercatorCoordinate.fromLngLat([longitude, latitude], 5 + relativeHeight * definition.altitude);
            positions.push(coordinate.x, coordinate.y, coordinate.z);
            weights.push(value);
        }
    }
    for (let row = 0; row < divisions; row++) {
        for (let column = 0; column < divisions; column++) {
            const a = row * (divisions + 1) + column;
            const b = a + 1;
            const c = a + divisions + 1;
            const d = c + 1;
            indices.push(a, c, b, b, c, d);
        }
    }
    const vertexAt = (row, column) => {
        const index = (row * (divisions + 1) + column) * 3;
        return [positions[index], positions[index + 1], positions[index + 2]];
    };
    for (let row = 0; row <= divisions; row++) {
        for (let column = 0; column <= divisions; column++) {
            const left = vertexAt(row, Math.max(0, column - 1));
            const right = vertexAt(row, Math.min(divisions, column + 1));
            const down = vertexAt(Math.max(0, row - 1), column);
            const up = vertexAt(Math.min(divisions, row + 1), column);
            const tangentX = [right[0] - left[0], right[1] - left[1], right[2] - left[2]];
            const tangentY = [up[0] - down[0], up[1] - down[1], up[2] - down[2]];
            const normal = [
                tangentX[1] * tangentY[2] - tangentX[2] * tangentY[1],
                tangentX[2] * tangentY[0] - tangentX[0] * tangentY[2],
                tangentX[0] * tangentY[1] - tangentX[1] * tangentY[0],
            ];
            const length = Math.hypot(...normal) || 1;
            normals.push(normal[0] / length, normal[1] / length, normal[2] / length);
        }
    }
    return { positions, normals, weights, indices, spatialIndex, definition };
}

function createShader(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(shader));
    return shader;
}

function createWaveLayer(id, surface, maxWeight = 1.0) {
    return {
        id, type: 'custom', renderingMode: '3d', minWeight: 0.004, maxWeight,
        setMinWeight(val) {
            this.minWeight = Math.max(0.001, Math.min(0.999, val));
            this.map?.triggerRepaint();
        },
        onAdd(map, gl) {
            this.map = map;
            const vertexShader = createShader(gl, gl.VERTEX_SHADER, `#version 300 es
                precision highp float;
                uniform mat4 u_matrix;
                in vec3 a_position;
                in vec3 a_normal;
                in float a_weight;
                out vec3 v_normal;
                out float v_weight;
                void main() {
                    v_normal = a_normal;
                    v_weight = a_weight;
                    gl_Position = u_matrix * vec4(a_position, 1.0);
                }`);
            const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, `#version 300 es
                precision highp float;
                uniform float u_min_weight;
                uniform float u_max_weight;
                in vec3 v_normal;
                in float v_weight;
                out vec4 outColor;

                const vec3 u_palette[10] = vec3[10](
                    vec3(0.090, 0.145, 0.329), // #172554 Azul escuro
                    vec3(0.114, 0.306, 0.847), // #1d4ed8 Azul vivo
                    vec3(0.024, 0.714, 0.831), // #06b6d4 Ciano
                    vec3(0.063, 0.725, 0.506), // #10b981 Verde Esmeralda
                    vec3(0.518, 0.800, 0.086), // #84cc16 Lima
                    vec3(0.980, 0.800, 0.082), // #facc15 Amarelo
                    vec3(0.976, 0.451, 0.086), // #f97316 Laranja
                    vec3(0.937, 0.267, 0.267), // #ef4444 Vermelho
                    vec3(0.635, 0.110, 0.686), // #a21caf Magenta
                    vec3(0.224, 1.000, 0.078)  // #39ff14 Verde Neon Extremo
                );

                void main() {
                    if (v_weight < u_min_weight) discard;
                    vec3 normal = normalize(v_normal);
                    float light = 0.68 + 0.32 * abs(dot(normal, normalize(vec3(-0.45, 0.35, 0.82))));
                    
                    float fadeWidth = (u_min_weight <= 0.005) ? 0.08 : 0.010;
                    float alpha = smoothstep(u_min_weight, u_min_weight + fadeWidth, v_weight) * 0.85;
                    if (alpha <= 0.001) discard;

                    float range = max(0.005, u_max_weight - u_min_weight);
                    float t = clamp((v_weight - u_min_weight) / range, 0.0, 0.999);
                    int band = clamp(int(floor(t * 10.0)), 0, 9);
                    vec3 baseColor = u_palette[band];
                    vec3 shadedColor = baseColor * light;
                    outColor = vec4(shadedColor * alpha, alpha);
                }`);
            this.program = gl.createProgram();
            gl.attachShader(this.program, vertexShader);
            gl.attachShader(this.program, fragmentShader);
            gl.linkProgram(this.program);
            if (!gl.getProgramParameter(this.program, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(this.program));
            gl.deleteShader(vertexShader);
            gl.deleteShader(fragmentShader);
            this.matrixLocation = gl.getUniformLocation(this.program, 'u_matrix');
            this.minWeightLocation = gl.getUniformLocation(this.program, 'u_min_weight');
            this.maxWeightLocation = gl.getUniformLocation(this.program, 'u_max_weight');
            this.vao = gl.createVertexArray();
            gl.bindVertexArray(this.vao);
            const positionBuffer = gl.createBuffer();
            this.buffers = [positionBuffer];
            gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.positions), gl.STATIC_DRAW);
            const positionLocation = gl.getAttribLocation(this.program, 'a_position');
            gl.enableVertexAttribArray(positionLocation);
            gl.vertexAttribPointer(positionLocation, 3, gl.FLOAT, false, 0, 0);
            const normalBuffer = gl.createBuffer();
            this.buffers.push(normalBuffer);
            gl.bindBuffer(gl.ARRAY_BUFFER, normalBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.normals), gl.STATIC_DRAW);
            const normalLocation = gl.getAttribLocation(this.program, 'a_normal');
            gl.enableVertexAttribArray(normalLocation);
            gl.vertexAttribPointer(normalLocation, 3, gl.FLOAT, false, 0, 0);
            const weightBuffer = gl.createBuffer();
            this.buffers.push(weightBuffer);
            gl.bindBuffer(gl.ARRAY_BUFFER, weightBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.weights), gl.STATIC_DRAW);
            const weightLocation = gl.getAttribLocation(this.program, 'a_weight');
            gl.enableVertexAttribArray(weightLocation);
            gl.vertexAttribPointer(weightLocation, 1, gl.FLOAT, false, 0, 0);
            this.indexBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, this.indexBuffer);
            gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint32Array(surface.indices), gl.STATIC_DRAW);
            this.indexCount = surface.indices.length;
            gl.bindVertexArray(null);
        },
        render(gl, args) {
            this.projectionMatrix = args.defaultProjectionData.mainMatrix;
            gl.useProgram(this.program);
            gl.uniformMatrix4fv(this.matrixLocation, false, args.defaultProjectionData.mainMatrix);
            gl.uniform1f(this.minWeightLocation, this.minWeight ?? 0.004);
            gl.uniform1f(this.maxWeightLocation, this.maxWeight ?? 1.0);
            gl.bindVertexArray(this.vao);
            gl.drawElements(gl.TRIANGLES, this.indexCount, gl.UNSIGNED_INT, 0);
            gl.bindVertexArray(null);
        },
        project(coordinate, width, height) {
            const matrix = this.projectionMatrix;
            if (!matrix) return null;
            const [x, y, z] = coordinate;
            const clipX = matrix[0] * x + matrix[4] * y + matrix[8] * z + matrix[12];
            const clipY = matrix[1] * x + matrix[5] * y + matrix[9] * z + matrix[13];
            const clipW = matrix[3] * x + matrix[7] * y + matrix[11] * z + matrix[15];
            if (clipW <= 0) return null;
            return { x: (clipX / clipW + 1) * width / 2, y: (1 - clipY / clipW) * height / 2 };
        },
        onRemove(map, gl) {
            this.buffers.forEach(buffer => gl.deleteBuffer(buffer));
            gl.deleteVertexArray(this.vao);
            gl.deleteBuffer(this.indexBuffer);
            gl.deleteProgram(this.program);
        },
    };
}


export function surfaceLayer(id, features, options) {
    const data = sampleFeatures(features, options.field);
    if (!data.features.length) return null;
    const mesh = buildWaveSurface(data, options);
    const maximum = mesh.weights.reduce((max, value) => Math.max(max, value), 0.05);
    const layer = createWaveLayer(id, mesh, maximum);
    layer.setCutoff = percentage => layer.setMinWeight(percentage <= 1 ? 0.004 : percentage >= 100 ? Math.max(0.005, maximum - 0.015) : 0.004 + (percentage - 1) / 99 * (maximum - 0.025));
    layer.candidates = data.features.map(feature => {
        const [longitude, latitude] = feature.geometry.coordinates;
        const weight = interpolate(mesh.spatialIndex, longitude, latitude, options.influence);
        const coordinate = maplibregl.MercatorCoordinate.fromLngLat([longitude, latitude], 5 + heightScale(weight) * options.altitude);
        return { id: feature.id, weight, coordinate: [coordinate.x, coordinate.y, coordinate.z] };
    });
    return layer;
}
