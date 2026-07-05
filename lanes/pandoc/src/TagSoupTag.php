<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupTag
{
    public const OPEN = 'open';
    public const CLOSE = 'close';
    public const TEXT = 'text';
    public const COMMENT = 'comment';
    public const WARNING = 'warning';
    public const POSITION = 'position';

    /**
     * @param list<array{name:string,value:string}> $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly string $name = '',
        public readonly array $attributes = [],
        public readonly string $text = '',
        public readonly ?int $row = null,
        public readonly ?int $column = null,
    ) {
    }

    /**
     * @param list<array{name:string,value:string}> $attributes
     */
    public static function open(string $name, array $attributes = []): self
    {
        return new self(self::OPEN, $name, self::normalizeAttributes($attributes));
    }

    public static function close(string $name): self
    {
        return new self(self::CLOSE, $name);
    }

    public static function text(string $text): self
    {
        return new self(self::TEXT, text: $text);
    }

    public static function comment(string $text): self
    {
        return new self(self::COMMENT, text: $text);
    }

    public static function warning(string $text): self
    {
        return new self(self::WARNING, text: $text);
    }

    public static function position(int $row, int $column): self
    {
        return new self(self::POSITION, row: $row, column: $column);
    }

    public function isOpenName(string $name): bool
    {
        return $this->type === self::OPEN && $this->name === $name;
    }

    public function isCloseName(string $name): bool
    {
        return $this->type === self::CLOSE && $this->name === $name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'text' => $this->text,
            'row' => $this->row,
            'column' => $this->column,
        ];
    }

    /**
     * @param list<array{name:string,value:string}> $attributes
     * @return list<array{name:string,value:string}>
     */
    private static function normalizeAttributes(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $attribute) {
            $name = $attribute['name'] ?? null;
            $value = $attribute['value'] ?? null;
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            $normalized[] = ['name' => $name, 'value' => $value];
        }

        return $normalized;
    }
}
