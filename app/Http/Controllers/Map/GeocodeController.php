<?php

namespace App\Http\Controllers\Map;

use GeoVeil\Geocoding\Nominatim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class GeocodeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:3|max:200']);
        if (! config('geoveil.geocoder_endpoint')) {
            return response()->json(['message' => 'Configure um provedor de geocodificação para habilitar a busca.'], 503);
        }
        try {
            $geocoder = new Nominatim(config('geoveil.geocoder_user_agent'), config('geoveil.geocoder_endpoint'));

            return response()->json(['results' => $geocoder->search($data['q'])]);
        } catch (\Exception $exception) {
            Log::warning('GeoVeil: serviço de localização indisponível.', ['exception' => get_class($exception)]);

            return response()->json(['message' => 'Busca indisponível no momento. Aguarde alguns segundos e tente novamente.'], 503);
        }
    }
}
