<?php

namespace GeoVeil\Widgets;

final class BarChart implements \JsonSerializable
{
    private array $definition;

    public static function make(string $label): self
    {
        $item = new self;
        $item->definition = ['type' => 'bar', 'label' => $label];

        return $item;
    }

    public function source(string $layer, string $label, string $value): self
    {
        $this->definition += ['layer' => $layer, 'labelField' => $label, 'field' => $value];

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->definition;
    }
}
