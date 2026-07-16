<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJson5Parser
{
    private int $offset = 0;
    private readonly int $length;

    private function __construct(private readonly string $json)
    {
        $this->length = strlen($json);
    }

    public static function decode(string $json): mixed
    {
        $parser = new self($json);
        $value = $parser->parseValue();
        $parser->skipWhitespaceAndComments();
        if (!$parser->isEnd()) {
            throw new \InvalidArgumentException('SQLite JSON5 input has trailing content');
        }

        return $value;
    }

    public static function errorPosition(string $json): int
    {
        $parser = new self($json);
        try {
            $parser->parseValue();
            $parser->skipWhitespaceAndComments();
            if (!$parser->isEnd()) {
                return 1;
            }

            return 0;
        } catch (\InvalidArgumentException) {
            return self::byteOffsetToCharacterPosition($json, min($parser->offset, $parser->length));
        }
    }

    private function parseValue(): mixed
    {
        $this->skipWhitespaceAndComments();
        if ($this->isEnd()) {
            throw new \InvalidArgumentException('SQLite JSON5 input ended before a value');
        }

        $char = $this->json[$this->offset];
        if ($char === '{') {
            return $this->parseObject();
        }
        if ($char === '[') {
            return $this->parseArray();
        }
        if ($char === '"' || $char === "'") {
            return $this->parseString();
        }

        return $this->parseNumberOrLiteral();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseObject(): array
    {
        $this->offset++;
        $object = [];
        $this->skipWhitespaceAndComments();
        if ($this->consume('}')) {
            return $object;
        }

        while (true) {
            $key = $this->parseObjectKey();
            $this->skipWhitespaceAndComments();
            if (!$this->consume(':')) {
                throw new \InvalidArgumentException('SQLite JSON5 object key is missing a colon');
            }

            $object[$key] = $this->parseValue();
            $this->skipWhitespaceAndComments();
            if ($this->consume('}')) {
                return $object;
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('SQLite JSON5 object member is missing a comma');
            }

            $this->skipWhitespaceAndComments();
            if ($this->consume('}')) {
                return $object;
            }
        }
    }

    /**
     * @return list<mixed>
     */
    private function parseArray(): array
    {
        $this->offset++;
        $array = [];
        $this->skipWhitespaceAndComments();
        if ($this->consume(']')) {
            return $array;
        }

        while (true) {
            $array[] = $this->parseValue();
            $this->skipWhitespaceAndComments();
            if ($this->consume(']')) {
                return $array;
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('SQLite JSON5 array value is missing a comma');
            }

            $this->skipWhitespaceAndComments();
            if ($this->consume(']')) {
                return $array;
            }
        }
    }

    private function parseObjectKey(): string
    {
        $this->skipWhitespaceAndComments();
        if ($this->isEnd()) {
            throw new \InvalidArgumentException('SQLite JSON5 object ended before a key');
        }

        $char = $this->json[$this->offset];
        if ($char === '"' || $char === "'") {
            return $this->parseString();
        }

        $remaining = substr($this->json, $this->offset);
        $matched = preg_match('/^[$_\p{L}][$_\p{L}\p{N}\x{200C}\x{200D}]*/u', $remaining, $matches);
        if ($matched !== 1 || $matches[0] === '') {
            throw new \InvalidArgumentException('SQLite JSON5 object key is outside the supported IdentifierName subset');
        }

        $this->offset += strlen($matches[0]);

        return $matches[0];
    }

    private function parseString(): string
    {
        $quote = $this->json[$this->offset];
        $this->offset++;
        $value = '';

        while (!$this->isEnd()) {
            $char = $this->json[$this->offset];
            $this->offset++;

            if ($char === $quote) {
                return $value;
            }
            if ($char !== '\\') {
                $value .= $char;
                continue;
            }
            if ($this->isEnd()) {
                throw new \InvalidArgumentException('SQLite JSON5 string escape is truncated');
            }

            $escapeStart = $this->offset;
            $escape = $this->json[$this->offset];
            $this->offset++;
            switch ($escape) {
                case "\n":
                    break;
                case "\r":
                    if (!$this->isEnd() && $this->json[$this->offset] === "\n") {
                        $this->offset++;
                    }
                    break;
                case "'":
                case '"':
                case '\\':
                case '/':
                    $value .= $escape;
                    break;
                case 'b':
                    $value .= "\x08";
                    break;
                case 'f':
                    $value .= "\x0c";
                    break;
                case 'n':
                    $value .= "\n";
                    break;
                case 'r':
                    $value .= "\r";
                    break;
                case 't':
                    $value .= "\t";
                    break;
                case 'v':
                    $value .= "\x0b";
                    break;
                case '0':
                    if (!$this->isEnd() && ctype_digit($this->json[$this->offset])) {
                        throw new \InvalidArgumentException('SQLite JSON5 nul escape cannot be followed by a digit');
                    }
                    $value .= "\0";
                    break;
                case 'x':
                    $value .= chr($this->readHexByte());
                    break;
                case 'u':
                    $value .= $this->readUnicodeEscape();
                    break;
                default:
                    if ($this->matchAt("\xE2\x80\xA8", $escapeStart) || $this->matchAt("\xE2\x80\xA9", $escapeStart)) {
                        $this->offset = $escapeStart + 3;
                        break;
                    }
                    $value .= $escape;
                    break;
            }
        }

        throw new \InvalidArgumentException('SQLite JSON5 string is unterminated');
    }

    private function parseNumberOrLiteral(): mixed
    {
        $remaining = substr($this->json, $this->offset);

        if (preg_match('/^[+-]?(?:Infinity|Inf)/i', $remaining, $matches) === 1 && $this->hasTokenBoundary(strlen($matches[0]))) {
            $this->offset += strlen($matches[0]);

            return str_starts_with($matches[0], '-') ? -INF : INF;
        }
        if (preg_match('/^[+-]?(?:NaN|QNaN|SNaN)/i', $remaining, $matches) === 1 && $this->hasTokenBoundary(strlen($matches[0]))) {
            $this->offset += strlen($matches[0]);

            return null;
        }
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f]+/', $remaining, $matches) === 1) {
            $token = $matches[0];
            $this->offset += strlen($token);
            $negative = str_starts_with($token, '-');
            if ($token[0] === '-' || $token[0] === '+') {
                $token = substr($token, 1);
            }
            $number = hexdec(substr($token, 2));

            return $negative ? -$number : $number;
        }
        $number = $this->parseJson5NumberToken();
        if ($number !== null) {
            return $number;
        }
        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*/', $remaining, $matches) === 1) {
            $tokenOffset = $this->offset;
            $token = $matches[0];
            if (!$this->hasTokenBoundary(strlen($token))) {
                throw new \InvalidArgumentException('SQLite JSON5 literal is followed by identifier characters');
            }
            $this->offset += strlen($token);
            $lower = strtolower($token);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
            if ($lower === 'null') {
                return null;
            }
            if ($lower === 'infinity' || $lower === 'inf') {
                return INF;
            }
            if ($lower === 'nan' || $lower === 'qnan' || $lower === 'snan') {
                return null;
            }

            $this->offset = $tokenOffset;
            throw new \InvalidArgumentException("SQLite JSON5 value identifier is unsupported: {$token}");
        }

        throw new \InvalidArgumentException('SQLite JSON5 value is malformed');
    }

    private function parseJson5NumberToken(): int|float|null
    {
        $start = $this->offset;
        $offset = $start;
        if ($offset < $this->length && ($this->json[$offset] === '+' || $this->json[$offset] === '-')) {
            $offset++;
        }

        $hasFraction = false;
        $hasExponent = false;
        if ($offset < $this->length && $this->json[$offset] === '.') {
            $hasFraction = true;
            $offset++;
            if ($offset >= $this->length || !ctype_digit($this->json[$offset])) {
                return null;
            }
            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
            }
        } elseif ($offset < $this->length && ctype_digit($this->json[$offset])) {
            if ($this->json[$offset] === '0' && $offset + 1 < $this->length && ctype_digit($this->json[$offset + 1])) {
                $this->offset = $offset + 1;
                throw new \InvalidArgumentException('SQLite JSON5 decimal number has a leading zero');
            }

            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
            }
            if ($offset < $this->length && $this->json[$offset] === '.') {
                $hasFraction = true;
                $offset++;
                while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                    $offset++;
                }
            }
        } else {
            return null;
        }

        if ($offset < $this->length && ($this->json[$offset] === 'e' || $this->json[$offset] === 'E')) {
            $hasExponent = true;
            $exponentOffset = $offset;
            $offset++;
            if ($offset < $this->length && ($this->json[$offset] === '+' || $this->json[$offset] === '-')) {
                $offset++;
            }
            if ($offset >= $this->length || !ctype_digit($this->json[$offset])) {
                $this->offset = $exponentOffset;
                throw new \InvalidArgumentException('SQLite JSON5 exponent is missing digits');
            }
            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
            }
        }

        $token = substr($this->json, $start, $offset - $start);
        $this->offset = $offset;

        return $hasFraction || $hasExponent ? (float) $token : (int) $token;
    }

    private function readHexByte(): int
    {
        if ($this->offset + 2 > $this->length) {
            throw new \InvalidArgumentException('SQLite JSON5 hex escape is truncated');
        }

        $hex = substr($this->json, $this->offset, 2);
        if (preg_match('/^[0-9A-Fa-f]{2}$/', $hex) !== 1) {
            throw new \InvalidArgumentException('SQLite JSON5 hex escape is malformed');
        }

        $this->offset += 2;

        return hexdec($hex);
    }

    private function readUnicodeEscape(): string
    {
        $codepoint = $this->readHexCodepoint();
        if (
            $codepoint >= 0xd800
            && $codepoint <= 0xdbff
            && $this->offset + 6 <= $this->length
            && substr($this->json, $this->offset, 2) === '\\u'
            && preg_match('/^[0-9A-Fa-f]{4}$/', substr($this->json, $this->offset + 2, 4)) === 1
        ) {
            $low = hexdec(substr($this->json, $this->offset + 2, 4));
            if ($low >= 0xdc00 && $low <= 0xdfff) {
                $this->offset += 6;
                $codepoint = 0x10000 + (($codepoint - 0xd800) << 10) + ($low - 0xdc00);
            }
        }

        return self::codepointToUtf8($codepoint);
    }

    private function readHexCodepoint(): int
    {
        if ($this->offset + 4 > $this->length) {
            throw new \InvalidArgumentException('SQLite JSON5 unicode escape is truncated');
        }

        $hex = substr($this->json, $this->offset, 4);
        if (preg_match('/^[0-9A-Fa-f]{4}$/', $hex) !== 1) {
            throw new \InvalidArgumentException('SQLite JSON5 unicode escape is malformed');
        }

        $this->offset += 4;

        return hexdec($hex);
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || ($codepoint >= 0xd800 && $codepoint <= 0xdfff) || $codepoint > 0x10ffff) {
            throw new \InvalidArgumentException('SQLite JSON5 unicode codepoint is invalid');
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    private function skipWhitespaceAndComments(): void
    {
        while (!$this->isEnd()) {
            $char = $this->json[$this->offset];
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === "\v" || $char === "\f") {
                $this->offset++;
                continue;
            }

            $matchedWhitespace = false;
            foreach (self::json5WhitespaceSequences() as $sequence) {
                if ($this->matchAt($sequence, $this->offset)) {
                    $this->offset += strlen($sequence);
                    $matchedWhitespace = true;
                    break;
                }
            }
            if ($matchedWhitespace) {
                continue;
            }

            if ($this->matchAt('//', $this->offset)) {
                $this->offset += 2;
                while (!$this->isEnd() && $this->json[$this->offset] !== "\n" && $this->json[$this->offset] !== "\r") {
                    $this->offset++;
                }
                continue;
            }
            if ($this->matchAt('/*', $this->offset)) {
                $end = strpos($this->json, '*/', $this->offset + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite JSON5 block comment is unterminated');
                }
                $this->offset = $end + 2;
                continue;
            }

            return;
        }
    }

    /**
     * @return list<string>
     */
    private static function json5WhitespaceSequences(): array
    {
        return [
            "\xC2\xA0",
            "\xE1\x9A\x80",
            "\xE2\x80\x80",
            "\xE2\x80\x81",
            "\xE2\x80\x82",
            "\xE2\x80\x83",
            "\xE2\x80\x84",
            "\xE2\x80\x85",
            "\xE2\x80\x86",
            "\xE2\x80\x87",
            "\xE2\x80\x88",
            "\xE2\x80\x89",
            "\xE2\x80\x8A",
            "\xE2\x80\xA8",
            "\xE2\x80\xA9",
            "\xE3\x80\x80",
            "\xEF\xBB\xBF",
        ];
    }

    private function hasTokenBoundary(int $tokenLength): bool
    {
        $offset = $this->offset + $tokenLength;
        if ($offset >= $this->length) {
            return true;
        }

        $char = $this->json[$offset];
        if (ctype_alnum($char) || $char === '_' || $char === '$' || ord($char) >= 0x80) {
            return false;
        }

        return true;
    }

    private function consume(string $char): bool
    {
        if (!$this->isEnd() && $this->json[$this->offset] === $char) {
            $this->offset++;

            return true;
        }

        return false;
    }

    private function matchAt(string $needle, int $offset): bool
    {
        return substr_compare($this->json, $needle, $offset, strlen($needle)) === 0;
    }

    private function isEnd(): bool
    {
        return $this->offset >= $this->length;
    }

    private static function byteOffsetToCharacterPosition(string $json, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }

        $offset = min($offset, strlen($json));
        $position = 1;
        for ($i = 0; $i < $offset; $i++) {
            if ((ord($json[$i]) & 0xc0) !== 0x80) {
                $position++;
            }
        }

        return $position;
    }
}
