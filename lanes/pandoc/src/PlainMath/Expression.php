<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

use InvalidArgumentException;

final class Expression
{
    /**
     * @param list<Expression> $children
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $value = null,
        public readonly array $children = [],
        public readonly array $attributes = [],
        public readonly ?int $startByte = null,
        public readonly ?int $endByte = null,
    ) {
        if ($kind === '') {
            throw new InvalidArgumentException('PlainMath expression kind cannot be empty.');
        }

        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new InvalidArgumentException('PlainMath expression children must be Expression instances.');
            }
        }

        if (($startByte === null) !== ($endByte === null)) {
            throw new InvalidArgumentException('PlainMath expression spans must include both start and end bytes.');
        }

        if ($startByte !== null && $endByte !== null && $endByte < $startByte) {
            throw new InvalidArgumentException('PlainMath expression end byte cannot precede start byte.');
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function number(string $value, array $attributes = []): self
    {
        return new self('number', $value, [], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function identifier(string $value, array $attributes = []): self
    {
        return new self('identifier', $value, [], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function operator(string $value, array $attributes = []): self
    {
        return new self('operator', $value, [], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function mathOperator(string $value, array $attributes = []): self
    {
        return new self('mathOperator', $value, [], ['mathvariant' => 'normal'] + $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function text(string $value, array $attributes = []): self
    {
        return new self('text', $value, [], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function space(array $attributes = []): self
    {
        return new self('space', null, [], $attributes);
    }

    /**
     * @param list<Expression> $children
     * @param array<string, mixed> $attributes
     */
    public static function row(array $children, array $attributes = []): self
    {
        return new self('row', null, $children, $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function super(Expression $base, Expression $superscript, array $attributes = []): self
    {
        return new self('super', null, [$base, $superscript], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function sub(Expression $base, Expression $subscript, array $attributes = []): self
    {
        return new self('sub', null, [$base, $subscript], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function subSup(Expression $base, Expression $subscript, Expression $superscript, array $attributes = []): self
    {
        return new self('subsup', null, [$base, $subscript, $superscript], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fraction(Expression $numerator, Expression $denominator, array $attributes = []): self
    {
        return new self('fraction', null, [$numerator, $denominator], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function sqrt(Expression $body, array $attributes = []): self
    {
        return new self('sqrt', null, [$body], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function root(Expression $body, Expression $index, array $attributes = []): self
    {
        return new self('root', null, [$body, $index], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function over(Expression $base, Expression $overscript, array $attributes = []): self
    {
        return new self('over', null, [$base, $overscript], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function under(Expression $base, Expression $underscript, array $attributes = []): self
    {
        return new self('under', null, [$base, $underscript], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function enclosed(Expression $body, string $notation, array $attributes = []): self
    {
        return new self('enclosed', null, [$body], ['notation' => $notation] + $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function style(Expression $body, array $attributes = []): self
    {
        return new self('style', null, [$body], $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function delimited(?string $left, Expression $body, ?string $right, array $attributes = []): self
    {
        return new self('delimited', null, [$body], ['left' => $left, 'right' => $right] + $attributes);
    }

    /**
     * @param list<list<Expression>> $rows
     * @param array<string, mixed> $attributes
     */
    public static function table(array $rows, array $attributes = []): self
    {
        $rowExpressions = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                if (!$cell instanceof self) {
                    throw new InvalidArgumentException('PlainMath table cells must be Expression instances.');
                }
                $cells[] = new self('tableCell', null, [$cell]);
            }
            $rowExpressions[] = new self('tableRow', null, $cells);
        }

        return new self('table', null, $rowExpressions, $attributes);
    }
}
