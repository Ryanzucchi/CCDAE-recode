<?php

namespace GeoVeil\Forms;

use InvalidArgumentException;

final class Field implements \JsonSerializable
{
    private string $label;
    private bool $required = false;
    private array $options = [];
    private ?string $help = null;
    private mixed $default = null;
    private ?string $step = null;
    private ?int $maxLength = null;
    private int|float|null $minimum = null;
    private int|float|null $maximum = null;
    private ?int $rows = null;
    private bool $searchable = false;
    private bool $disabled = false;
    private ?string $endpoint = null;

    private function __construct(private string $name, private string $type)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) throw new InvalidArgumentException('Nome de campo inválido.');
        $this->label = ucfirst(str_replace('_', ' ', $name));
    }

    public static function text(string $name): self { return new self($name, 'text'); }
    public static function number(string $name): self { return new self($name, 'number'); }
    public static function datetime(string $name): self { return new self($name, 'datetime-local'); }
    public static function date(string $name): self { return new self($name, 'date'); }
    public static function toggle(string $name): self { return new self($name, 'checkbox'); }
    public static function textarea(string $name): self { return new self($name, 'textarea'); }
    public static function select(string $name, array $options): self { return (new self($name, 'select'))->options($options); }
    public static function remoteSelect(string $name, string $endpoint, array $selected = []): self { return (new self($name, 'remote-select'))->endpoint($endpoint)->options($selected); }
    public static function point(string $name = 'location'): self { return new self($name, 'point'); }
    public static function geometry(string $name = 'geometry'): self { return new self($name, 'geometry'); }
    public static function line(string $name = 'route'): self { return new self($name, 'line'); }

    public function label(string $label): self { $this->label = $label; return $this; }
    public function required(): self { $this->required = true; return $this; }
    public function help(string $text): self { $this->help = $text; return $this; }
    public function default(mixed $value): self { $this->default = $value; return $this; }
    public function step(string $value): self { $this->step = $value; return $this; }
    public function maxLength(int $value): self { if ($value < 1) throw new InvalidArgumentException('Comprimento máximo inválido.'); $this->maxLength = $value; return $this; }
    public function min(int|float $value): self { $this->minimum = $value; return $this; }
    public function max(int|float $value): self { $this->maximum = $value; return $this; }
    public function rows(int $value): self { if ($value < 1) throw new InvalidArgumentException('Número de linhas inválido.'); $this->rows = $value; return $this; }
    public function searchable(): self { if ($this->type !== 'select') throw new InvalidArgumentException('Busca local exige um campo de seleção.'); $this->searchable = true; return $this; }
    public function disabled(): self { $this->disabled = true; return $this; }
    public function options(array $options): self { $this->options = $options; return $this; }
    public function endpoint(string $url): self { if ($url === '') throw new InvalidArgumentException('Endpoint de busca ausente.'); $this->endpoint = $url; return $this; }

    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'label' => $this->label, 'required' => $this->required, 'options' => $this->options, 'help' => $this->help, 'default' => $this->default, 'step' => $this->step, 'maxLength' => $this->maxLength, 'min' => $this->minimum, 'max' => $this->maximum, 'rows' => $this->rows, 'searchable' => $this->searchable, 'disabled' => $this->disabled, 'endpoint' => $this->endpoint];
    }
}
