<?php

namespace GeoVeil\Layers;

use InvalidArgumentException;

final class SurfaceLayer extends Layer
{
    protected string $type = 'surface';

    private array $surface = ['field' => 'value', 'altitude' => 1850, 'influence' => 0.005];

    public function value(string $field): static
    {
        $this->surface['field'] = $field;
        $this->withProperties([$field]);

        return $this;
    }

    public function altitude(float $meters): static
    {
        if (! is_finite($meters) || $meters <= 0 || $meters > 10000) {
            throw new InvalidArgumentException('Altitude deve estar entre 0 e 10.000 metros.');
        } $this->surface['altitude'] = $meters;

        return $this;
    }

    public function influence(float $degrees): static
    {
        if (! is_finite($degrees) || $degrees <= 0 || $degrees > 1) {
            throw new InvalidArgumentException('Influência inválida.');
        } $this->surface['influence'] = $degrees;

        return $this;
    }

    public function jsonSerialize(): array
    {
        $this->withProperties([$this->surface['field']]);

        return parent::jsonSerialize() + ['surface' => $this->surface];
    }
}
