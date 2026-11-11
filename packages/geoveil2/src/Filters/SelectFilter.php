<?php

namespace GeoVeil\Filters;

final class SelectFilter implements \JsonSerializable
{
    private string $label;

    private ?string $layer = null;

    public function __construct(private string $field)
    {
        $this->label = $field;
    }

    public static function make(string $field): self
    {
        return new self($field);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function layer(string $layer): self
    {
        $this->layer = $layer;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return ['type' => 'select', 'field' => $this->field, 'label' => $this->label, 'layer' => $this->layer];
    }
}
