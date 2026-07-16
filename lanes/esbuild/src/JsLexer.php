<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class JsLexer
{
    /**
     * @return list<Token>
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($offset === 0 && substr($source, 0, 2) === '#!') {
                $hashbangLength = strcspn($source, "\r\n", 2) + 2;
                $tokens[] = new Token('hashbang', substr($source, 0, $hashbangLength), 0);
                $offset = $hashbangLength;
                continue;
            }
            if (preg_match('/\G\s+/A', $source, $m, 0, $offset)) {
                $offset += strlen($m[0]);
                continue;
            }
            if (substr($source, $offset, 2) === '//') {
                $offset += strcspn($source, "\r\n", $offset);
                continue;
            }
            if (substr($source, $offset, 2) === '/*') {
                $end = strpos($source, '*/', $offset + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('Unterminated JavaScript block comment at offset ' . $offset);
                }
                $offset = $end + 2;
                continue;
            }
            if (preg_match('/\G[$_\pL][$_\pL\pN]*/Au', $source, $m, 0, $offset)) {
                $tokens[] = new Token('identifier', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G#[$_\pL][$_\pL\pN]*/Au', $source, $m, 0, $offset)) {
                $tokens[] = new Token('private_identifier', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            $number = $this->readNumberLiteral($source, $offset);
            if ($number !== null) {
                [$text, $value, $newOffset] = $number;
                $tokens[] = new Token('number', $text, $offset, $value);
                $offset = $newOffset;
                continue;
            }
            if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/As', $source, $m, 0, $offset)) {
                $tokens[] = new Token('string', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G=>|===|!==|==|!=|<=|>=|\+\+|--|\+=|-=|\*=|\/=|%=|&&=|\|\|=|\?\?=|&&|\|\||[{}()[\].,;:?+\-*\/%<>=!@]/A', $source, $m, 0, $offset)) {
                $tokens[] = new Token('punctuator', $m[0], $offset);
                $offset += strlen($m[0]);
                continue;
            }

            throw new \InvalidArgumentException('Unexpected JavaScript byte at offset ' . $offset);
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: float, 2: int}|null
     */
    private function readNumberLiteral(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $first = $source[$offset];
        $second = $offset + 1 < $length ? $source[$offset + 1] : '';

        if ($first === '0' && ($second === 'b' || $second === 'B' || $second === 'o' || $second === 'O' || $second === 'x' || $second === 'X')) {
            $base = match (strtolower($second)) {
                'b' => 2,
                'o' => 8,
                default => 16,
            };
            [$cursor, $digits] = $this->consumeDigitSequence($source, $offset + 2, $base);
            if ($digits === '') {
                throw new \InvalidArgumentException('Malformed JavaScript numeric literal at offset ' . $offset);
            }
            if ($cursor < $length && $this->isIdentifierContinueByte($source[$cursor])) {
                throw new \InvalidArgumentException('Malformed JavaScript numeric literal at offset ' . $cursor);
            }

            $text = substr($source, $offset, $cursor - $offset);
            $normalized = str_replace('_', '', $digits);
            $value = match ($base) {
                2 => (float) bindec($normalized),
                8 => (float) octdec($normalized),
                default => (float) hexdec($normalized),
            };

            return [$text, $value, $cursor];
        }

        $cursor = $offset;
        $digits = '';
        if ($this->isDecimalDigit($first)) {
            [$cursor, $digits] = $this->consumeDigitSequence($source, $cursor, 10);
        }

        $fractionDigits = '';
        if ($cursor < $length && $source[$cursor] === '.') {
            $cursor++;
            [$cursor, $fractionDigits] = $this->consumeDigitSequence($source, $cursor, 10);
        }

        if ($digits === '' && $fractionDigits === '') {
            return null;
        }

        if ($cursor < $length && ($source[$cursor] === 'e' || $source[$cursor] === 'E')) {
            $exponentOffset = $cursor;
            $cursor++;
            if ($cursor < $length && ($source[$cursor] === '+' || $source[$cursor] === '-')) {
                $cursor++;
            }
            [$cursor, $exponentDigits] = $this->consumeDigitSequence($source, $cursor, 10);
            if ($exponentDigits === '') {
                throw new \InvalidArgumentException('Malformed JavaScript numeric exponent at offset ' . $exponentOffset);
            }
        }

        $text = substr($source, $offset, $cursor - $offset);
        $value = (float) str_replace('_', '', $text);

        return [$text, $value, $cursor];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function consumeDigitSequence(string $source, int $offset, int $base): array
    {
        $cursor = $offset;
        $digits = '';
        $lastWasSeparator = false;
        $length = strlen($source);

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '_') {
                if ($digits === '' || $lastWasSeparator) {
                    throw new \InvalidArgumentException('Malformed JavaScript numeric separator at offset ' . $cursor);
                }
                $lastWasSeparator = true;
                $digits .= $char;
                $cursor++;
                continue;
            }
            if (!$this->isDigitForBase($char, $base)) {
                break;
            }
            $lastWasSeparator = false;
            $digits .= $char;
            $cursor++;
        }

        if ($lastWasSeparator) {
            throw new \InvalidArgumentException('Malformed JavaScript numeric separator at offset ' . ($cursor - 1));
        }

        return [$cursor, $digits];
    }

    private function isDigitForBase(string $char, int $base): bool
    {
        if ($base === 2) {
            return $char === '0' || $char === '1';
        }
        if ($base === 8) {
            return $char >= '0' && $char <= '7';
        }
        if ($base === 10) {
            return $this->isDecimalDigit($char);
        }

        return ctype_xdigit($char);
    }

    private function isDecimalDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isIdentifierContinueByte(string $char): bool
    {
        return ($char >= '0' && $char <= '9')
            || ($char >= 'A' && $char <= 'Z')
            || ($char >= 'a' && $char <= 'z')
            || $char === '$'
            || $char === '_';
    }
}
