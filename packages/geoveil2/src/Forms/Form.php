<?php

namespace GeoVeil\Forms;

use InvalidArgumentException;

final class Form implements \JsonSerializable
{
    private array $sections = [];
    private function __construct(private string $id) {}
    public static function make(string $id): self
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) throw new InvalidArgumentException('ID de formulário inválido.');
        return new self($id);
    }
    public function section(string $label, array $fields): self
    {
        $names = [];
        foreach ($this->sections as $section) foreach ($section['fields'] as $field) $names[$field['name']] = true;
        $definitions = [];
        foreach ($fields as $field) {
            if (!$field instanceof Field) throw new InvalidArgumentException('Campo inválido.');
            $definition = $field->jsonSerialize();
            if (isset($names[$definition['name']])) throw new InvalidArgumentException('Campo duplicado.');
            $names[$definition['name']] = true;
            $definitions[] = $definition;
        }
        $this->sections[] = ['label' => $label, 'fields' => $definitions];
        return $this;
    }
    public function jsonSerialize(): array { return ['id' => $this->id, 'sections' => $this->sections]; }
}
