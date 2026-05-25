<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPretty
{
    private int $offset = 0;
    private readonly int $length;

    private function __construct(
        private readonly string $json,
        private readonly string $indent,
    ) {
        $this->length = strlen($json);
    }

    public static function jsonPretty(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, ?string $indent = null): ?string
    {
        $canonical = SQLiteJsonCanonical::json($value);
        if ($canonical === null) {
            return null;
        }

        $formatter = new self($canonical, $indent ?? '    ');

        return $formatter->format();
    }

    public static function jsonPrettySqlFunction(string $function, string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $indent = null): ?string
    {
        if (strtolower($function) !== 'json_pretty') {
            throw new \InvalidArgumentException('SQLite JSON pretty function must be json_pretty');
        }

        return self::jsonPretty(self::sqlArgumentToJsonInput($value), self::sqlArgumentToText($indent));
    }

    /**
     * @param list<string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null> $arguments
     */
    public static function jsonPrettySqlFunctionArguments(string $function, array $arguments): ?string
    {
        $argumentCount = count($arguments);
        if ($argumentCount < 1 || $argumentCount > 2) {
            throw new \InvalidArgumentException('SQLite json_pretty() expects one or two arguments');
        }

        $indent = null;
        if ($argumentCount === 2) {
            $indent = self::sqlArgumentToText($arguments[1]);
        }

        return self::jsonPrettySqlFunction($function, self::sqlArgumentToJsonInput($arguments[0]), $indent);
    }

    private static function sqlArgumentToJsonInput(string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null
    {
        if ($value instanceof SQLiteBlobValue || $value instanceof SQLiteJsonSubtypeValue || $value === null) {
            return $value;
        }

        return self::sqlScalarToText($value);
    }

    private static function sqlArgumentToText(string|int|float|bool|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return $value->json;
        }

        return self::sqlScalarToText($value);
    }

    private static function sqlScalarToText(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (!is_string($encoded)) {
                throw new \InvalidArgumentException('SQLite JSON pretty SQL scalar could not be encoded');
            }

            return $encoded;
        }

        return (string) $value;
    }

    private function format(): string
    {
        $pretty = $this->formatValue(0);
        if (!$this->isEnd()) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        return $pretty;
    }

    private function formatValue(int $depth): string
    {
        if ($this->isEnd()) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        return match ($this->json[$this->offset]) {
            '{' => $this->formatObject($depth),
            '[' => $this->formatArray($depth),
            '"' => $this->readStringLiteral(),
            default => $this->readAtom(),
        };
    }

    private function formatObject(int $depth): string
    {
        $this->offset++;
        if ($this->consume('}')) {
            return '{}';
        }

        $members = [];
        while (true) {
            if ($this->isEnd() || $this->json[$this->offset] !== '"') {
                throw new \InvalidArgumentException('malformed JSON');
            }

            $key = $this->readStringLiteral();
            if (!$this->consume(':')) {
                throw new \InvalidArgumentException('malformed JSON');
            }

            $members[] = $key . ': ' . $this->formatValue($depth + 1);
            if ($this->consume('}')) {
                break;
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('malformed JSON');
            }
        }

        $memberIndent = $this->indent($depth + 1);

        return "{\n"
            . $memberIndent
            . implode(",\n" . $memberIndent, $members)
            . "\n"
            . $this->indent($depth)
            . '}';
    }

    private function formatArray(int $depth): string
    {
        $this->offset++;
        if ($this->consume(']')) {
            return '[]';
        }

        $items = [];
        while (true) {
            $items[] = $this->formatValue($depth + 1);
            if ($this->consume(']')) {
                break;
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('malformed JSON');
            }
        }

        $itemIndent = $this->indent($depth + 1);

        return "[\n"
            . $itemIndent
            . implode(",\n" . $itemIndent, $items)
            . "\n"
            . $this->indent($depth)
            . ']';
    }

    private function readStringLiteral(): string
    {
        $start = $this->offset;
        $this->offset++;

        while (!$this->isEnd()) {
            $char = $this->json[$this->offset];
            $this->offset++;
            if ($char === '"') {
                return substr($this->json, $start, $this->offset - $start);
            }
            if ($char === '\\') {
                if ($this->isEnd()) {
                    throw new \InvalidArgumentException('malformed JSON');
                }
                $this->offset++;
            }
        }

        throw new \InvalidArgumentException('malformed JSON');
    }

    private function readAtom(): string
    {
        $start = $this->offset;
        while (!$this->isEnd()) {
            $char = $this->json[$this->offset];
            if ($char === ',' || $char === ']' || $char === '}') {
                break;
            }
            $this->offset++;
        }

        if ($this->offset === $start) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        return substr($this->json, $start, $this->offset - $start);
    }

    private function consume(string $char): bool
    {
        if (!$this->isEnd() && $this->json[$this->offset] === $char) {
            $this->offset++;

            return true;
        }

        return false;
    }

    private function indent(int $depth): string
    {
        return str_repeat($this->indent, $depth);
    }

    private function isEnd(): bool
    {
        return $this->offset >= $this->length;
    }
}
