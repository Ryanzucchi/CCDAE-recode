<?php

namespace GeoVeil\Resources;

use GeoVeil\Forms\Form;
use InvalidArgumentException;

final class Resource
{
    private array $columns = [];
    private array $optionalColumns = [];
    private array $badgedColumns = [];
    private ?Form $form = null;
    private function __construct(public readonly string $id, public readonly string $title, public readonly string $table)
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) throw new InvalidArgumentException('ID de recurso inválido.');
        if (!preg_match('/^[a-z_][a-z0-9_.]*$/', $table)) throw new InvalidArgumentException('Tabela inválida.');
    }
    public static function make(string $id, string $title, string $table): self { return new self($id, $title, $table); }
    public function form(Form $form): self { $this->form = $form; return $this; }
    public function columns(array $columns): self
    {
        foreach ($columns as $field => $label) {
            if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]*$/', $field) || !is_string($label)) throw new InvalidArgumentException('Coluna inválida.');
        }
        $this->columns = $columns;
        return $this;
    }
    public function getForm(): Form { return $this->form ?? throw new InvalidArgumentException('Formulário ausente.'); }
    public function getColumns(): array { return $this->columns; }
    public function optionalColumns(array $columns): self
    {
        foreach ($columns as $field => $label) {
            if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]*$/', $field) || !is_string($label)) throw new InvalidArgumentException('Coluna opcional inválida.');
        }
        $this->optionalColumns = $columns;
        return $this;
    }
    public function getOptionalColumns(): array { return $this->optionalColumns; }
    public function badgedColumns(array $fields): self
    {
        foreach ($fields as $field) {
            if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]*$/', $field)) throw new InvalidArgumentException('Coluna de etiqueta inválida.');
        }
        $this->badgedColumns = $fields;
        return $this;
    }
    public function getBadgedColumns(): array { return $this->badgedColumns; }
}
