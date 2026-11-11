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
    public function options(array $options): self { $this->options = $options; return $this; }
    public function endpoint(string $url): self { if ($url === '') throw new InvalidArgumentException('Endpoint de busca ausente.'); $this->endpoint = $url; return $this; }

    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'label' => $this->label, 'required' => $this->required, 'options' => $this->options, 'help' => $this->help, 'default' => $this->default, 'step' => $this->step, 'endpoint' => $this->endpoint];
    }
}
