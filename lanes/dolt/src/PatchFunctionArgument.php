<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PatchFunctionArgument
{
    private function __construct(
        private mixed $value,
        private bool $literal,
        private string $expression,
    ) {
    }

    public static function literal(mixed $value, ?string $expression = null): self
    {
        return new self($value, true, $expression ?? self::defaultExpression($value));
    }

    public static function expression(string $expression): self
    {
        if ($expression === '') {
            throw new \InvalidArgumentException('Patch function expression must be non-empty.');
        }

        return new self(null, false, $expression);
    }

    public function isLiteral(): bool
    {
        return $this->literal;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function expressionString(): string
    {
        return $this->expression;
    }

    private static function defaultExpression(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "\\'", $value) . "'";
        }
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return (string) $value;
    }
}
