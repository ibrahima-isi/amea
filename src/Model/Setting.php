<?php
namespace Amea\Model;

class Setting
{
    public function __construct(
        private string $key,
        private string $value
    ) {}

    public function getKey(): string   { return $this->key; }
    public function getValue(): string { return $this->value; }

    public static function fromRow(array $row): self
    {
        return new self($row['setting_key'], $row['setting_value'] ?? '');
    }
}
