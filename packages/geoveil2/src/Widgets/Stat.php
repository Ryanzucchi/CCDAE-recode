<?php

namespace GeoVeil\Widgets;

final class Stat implements \JsonSerializable
{
    private array $definition;

    public static function make(string $label): self
    {
        $item = new self;
        $item->definition = ['type' => 'stat', 'label' => $label];

        return $item;
    }

    public function sum(string $layer, string $field): self
    {
        $this->definition += ['layer' => $layer, 'field' => $field, 'operation' => 'sum'];

        return $this;
    }

    public function count(string $layer): self
    {
        $this->definition += ['layer' => $layer, 'operation' => 'count'];

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->definition;
    }
}
