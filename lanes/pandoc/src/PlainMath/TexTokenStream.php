<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

use InvalidArgumentException;

final class TexTokenStream
{
    private readonly int $length;

    public function __construct(
        private readonly string $source,
        private readonly int $offset = 0
    ) {
        $this->length = strlen($source);
        if ($offset < 0 || $offset > $this->length) {
            throw new InvalidArgumentException('TeX token stream offset is outside the source bounds.');
        }
    }

    public function source(): string
    {
        return $this->source;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function atEnd(): bool
    {
        return $this->offset >= $this->length;
    }

    public function peekByte(int $relativeOffset = 0): ?string
    {
        $offset = $this->offset + $relativeOffset;

        return $offset >= 0 && $offset < $this->length ? $this->source[$offset] : null;
    }

    public function withOffset(int $offset): self
    {
        return new self($this->source, $offset);
    }

    public function slice(int $start, int $end): string
    {
        return $this->sourceSpan($start, $end)['text'];
    }

    /**
     * @return array{start:int,end:int,text:string}
     */
    public function sourceSpan(int $start, int $end): array
    {
        if ($start < 0 || $end < $start || $end > $this->length) {
            throw new InvalidArgumentException('TeX source span is outside the source bounds.');
        }

        return [
            'start' => $start,
            'end' => $end,
            'text' => substr($this->source, $start, $end - $start),
        ];
    }

    public function skipWhitespace(): self
    {
        $cursor = $this->offset;
        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if (ctype_space($char)) {
                $cursor++;
                continue;
            }

            if ($char === '%' && !$this->isEscapedAt($cursor)) {
                $cursor++;
                while ($cursor < $this->length && $this->source[$cursor] !== "\n" && $this->source[$cursor] !== "\r") {
                    $cursor++;
                }
                continue;
            }

            break;
        }

        return $this->withOffset($cursor);
    }

    /**
     * @return array{command:string,span:array{start:int,end:int,text:string},stream:self}|null
     */
    public function readCommand(): ?array
    {
        if (($this->source[$this->offset] ?? '') !== '\\') {
            return null;
        }

        $start = $this->offset;
        $cursor = $start + 1;
        $nameStart = $cursor;
        while ($cursor < $this->length && self::isAsciiAlpha($this->source[$cursor])) {
            $cursor++;
        }

        if ($cursor > $nameStart) {
            $command = substr($this->source, $nameStart, $cursor - $nameStart);
        } else {
            [$command, $cursor] = $this->readUtf8CharAt($cursor);
        }

        return [
            'command' => $command,
            'span' => $this->sourceSpan($start, $cursor),
            'stream' => $this->withOffset($cursor),
        ];
    }

    /**
     * @return array{value:string,span:array{start:int,end:int,text:string},inner_span:array{start:int,end:int,text:string},stream:self}|null
     */
    public function readRawGroup(): ?array
    {
        if (($this->source[$this->offset] ?? '') !== '{') {
            return null;
        }

        $start = $this->offset;
        $innerStart = $start + 1;
        $cursor = $innerStart;
        $depth = 1;

        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if ($char === '\\') {
                $cursor = $this->skipEscapedCharacter($cursor);
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $cursor + 1;

                    return [
                        'value' => substr($this->source, $innerStart, $cursor - $innerStart),
                        'span' => $this->sourceSpan($start, $end),
                        'inner_span' => $this->sourceSpan($innerStart, $cursor),
                        'stream' => $this->withOffset($end),
                    ];
                }
            }

            $cursor++;
        }

        return null;
    }

    /**
     * @return array{value:string,span:array{start:int,end:int,text:string},inner_span:array{start:int,end:int,text:string},stream:self}|null
     */
    public function readOptionalBracket(): ?array
    {
        if (($this->source[$this->offset] ?? '') !== '[') {
            return null;
        }

        $start = $this->offset;
        $innerStart = $start + 1;
        $cursor = $innerStart;
        $depth = 1;

        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if ($char === '\\') {
                $cursor = $this->skipEscapedCharacter($cursor);
                continue;
            }

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $cursor + 1;

                    return [
                        'value' => substr($this->source, $innerStart, $cursor - $innerStart),
                        'span' => $this->sourceSpan($start, $end),
                        'inner_span' => $this->sourceSpan($innerStart, $cursor),
                        'stream' => $this->withOffset($end),
                    ];
                }
            }

            $cursor++;
        }

        return null;
    }

    /**
     * @return array{char:string,span:array{start:int,end:int,text:string},stream:self}|null
     */
    public function readUtf8Char(): ?array
    {
        if ($this->atEnd()) {
            return null;
        }

        [$char, $end] = $this->readUtf8CharAt($this->offset);

        return [
            'char' => $char,
            'span' => $this->sourceSpan($this->offset, $end),
            'stream' => $this->withOffset($end),
        ];
    }

    private function isEscapedAt(int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $this->source[$cursor] === '\\'; $cursor--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    private function skipEscapedCharacter(int $offset): int
    {
        [, $cursor] = $this->readUtf8CharAt($offset + 1);

        return $cursor;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function readUtf8CharAt(int $offset): array
    {
        if ($offset >= $this->length) {
            return ['', $offset];
        }

        $tail = substr($this->source, $offset);
        if (preg_match('/\A./us', $tail, $match) === 1) {
            return [$match[0], $offset + strlen($match[0])];
        }

        return [$this->source[$offset], $offset + 1];
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z');
    }
}
