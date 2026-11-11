<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class VectorTileController extends Controller
{
    public function tilejson(string $source)
    {
        abort_unless(in_array($source, config('geoveil.vector_sources'), true), 404);
        $upstream = Http::timeout(5)->get(rtrim(config('geoveil.martin_url'), '/').'/'.$source);
        abort_unless($upstream->successful(), 502);
        $json = $upstream->json();
        abort_unless(is_array($json) && isset($json['vector_layers']), 502);
        $json['tiles'] = [url('/tiles/'.$source).'/{z}/{x}/{y}'];
        return response()->json($json)->header('Cache-Control', 'public, max-age=60');
    }

    public function tile(string $source, int $z, int $x, int $y)
    {
        abort_unless(in_array($source, config('geoveil.vector_sources'), true), 404);
        abort_unless($z >= 0 && $z <= 22 && $x >= 0 && $y >= 0 && $x < 2 ** $z && $y < 2 ** $z, 404);
        $upstream = Http::timeout(10)->get(rtrim(config('geoveil.martin_url'), '/')."/$source/$z/$x/$y");
        abort_unless($upstream->successful(), $upstream->status() === 404 ? 404 : 502);
        return response($upstream->body(), 200, [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
