<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

final class TexParseResult
{
    /**
     * @param list<Expression> $expressions
     * @param list<array{code:string,message:string,offset?:int}> $diagnostics
     */
    public function __construct(
        public readonly string $source,
        public readonly array $expressions,
        public readonly array $diagnostics = [],
        public readonly int $consumedBytes = 0,
    ) {
    }

    public function ok(): bool
    {
        return $this->diagnostics === [];
    }

    public function expression(): ?Expression
    {
        return match (count($this->expressions)) {
            0 => null,
            1 => $this->expressions[0],
            default => Expression::row($this->expressions),
        };
    }
}
