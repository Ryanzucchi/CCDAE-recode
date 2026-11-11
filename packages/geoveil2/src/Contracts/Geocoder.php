<?php

namespace GeoVeil\Contracts;

interface Geocoder
{
    /** @return array<int, array{label:string, longitude:float, latitude:float}> */
    public function search(string $query): array;
}
