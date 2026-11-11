<?php

namespace GeoVeil\Widgets;

final class LineChart implements \JsonSerializable
{
    private array $definition;

    public static function make(string $label): self
    {
        $item = new self;
        $item->definition = ['type' => 'line', 'label' => $label];

        return $item;
    }

    public function source(string $layer, string $time, string $value): self
    {
        $this->definition += ['layer' => $layer, 'time' => $time, 'field' => $value];

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->definition;
    }
}
