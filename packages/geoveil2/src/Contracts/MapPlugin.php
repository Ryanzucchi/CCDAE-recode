<?php

namespace GeoVeil\Contracts;

interface MapPlugin extends \JsonSerializable
{
    public function supports(string $capability): bool;
}
