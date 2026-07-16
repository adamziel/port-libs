<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonCanonical
{
    private int $offset = 0;
    private readonly int $length;

    private function __construct(private readonly string $json)
    {
        $this->length = strlen($json);
    }

    public static function json(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return self::canonicalizeText($value->json);
        }
        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                return self::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($value->bytes));
            }

            return self::canonicalizeText($value->bytes);
        }

        return self::canonicalizeText($value);
    }

    public static function jsonSqlFunction(string $function, string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): string|SQLiteBlobValue|null
    {
        $normalized = strtolower($function);
        $json = match ($normalized) {
            'json', 'jsonb' => self::json($value),
            default => throw new \InvalidArgumentException('SQLite JSON canonical function must be json or jsonb'),
        };
        if ($json === null || $normalized === 'json') {
            return $json;
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode($json)));
    }

    /**
     * @param list<string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null> $arguments
     */
    public static function jsonSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue|null
    {
        if (count($arguments) !== 1) {
            throw new \InvalidArgumentException('SQLite json() and jsonb() expect one argument');
        }

        return self::jsonSqlFunction($function, $arguments[0]);
    }

    public static function canonicalizeText(string $json): string
    {
        $parser = new self($json);
        $canonical = $parser->parseValue();
        $parser->skipWhitespaceAndComments();
        if (!$parser->isEnd()) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        return $canonical;
    }

    private function parseValue(): string
    {
        $this->skipWhitespaceAndComments();
        if ($this->isEnd()) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        return match ($this->json[$this->offset]) {
            '{' => $this->parseObject(),
            '[' => $this->parseArray(),
            '"', "'" => $this->parseStringLiteral(),
            default => $this->parseNumberOrLiteral(),
        };
    }

    private function parseObject(): string
    {
        $this->offset++;
        $members = [];
        $this->skipWhitespaceAndComments();
        if ($this->consume('}')) {
            return '{}';
        }

        while (true) {
            $key = $this->parseObjectKey();
            $this->skipWhitespaceAndComments();
            if (!$this->consume(':')) {
                throw new \InvalidArgumentException('malformed JSON');
            }
            $members[] = $key . ':' . $this->parseValue();
            $this->skipWhitespaceAndComments();
            if ($this->consume('}')) {
                return '{' . implode(',', $members) . '}';
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('malformed JSON');
            }

            $this->skipWhitespaceAndComments();
            if ($this->consume('}')) {
                return '{' . implode(',', $members) . '}';
            }
        }
    }

    private function parseArray(): string
    {
        $this->offset++;
        $items = [];
        $this->skipWhitespaceAndComments();
        if ($this->consume(']')) {
            return '[]';
        }

        while (true) {
            $items[] = $this->parseValue();
            $this->skipWhitespaceAndComments();
            if ($this->consume(']')) {
                return '[' . implode(',', $items) . ']';
            }
            if (!$this->consume(',')) {
                throw new \InvalidArgumentException('malformed JSON');
            }

            $this->skipWhitespaceAndComments();
            if ($this->consume(']')) {
                return '[' . implode(',', $items) . ']';
            }
        }
    }

    private function parseObjectKey(): string
    {
        $this->skipWhitespaceAndComments();
        if ($this->isEnd()) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        $char = $this->json[$this->offset];
        if ($char === '"' || $char === "'") {
            return $this->parseStringLiteral();
        }

        $remaining = substr($this->json, $this->offset);
        $matched = preg_match('/^[$_\p{L}][$_\p{L}\p{N}\x{200C}\x{200D}]*/u', $remaining, $matches);
        if ($matched !== 1 || $matches[0] === '') {
            throw new \InvalidArgumentException('malformed JSON');
        }

        $this->offset += strlen($matches[0]);

        return self::quoteString($matches[0]);
    }

    private function parseStringLiteral(): string
    {
        $quote = $this->json[$this->offset];
        $this->offset++;
        $canonical = '"';

        while (!$this->isEnd()) {
            $char = $this->json[$this->offset];
            $this->offset++;

            if ($char === $quote) {
                return $canonical . '"';
            }
            if ($char !== '\\') {
                $canonical .= self::escapeStringByte($char);
                continue;
            }
            if ($this->isEnd()) {
                throw new \InvalidArgumentException('malformed JSON');
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
                    $canonical .= "'";
                    break;
                case '"':
                    $canonical .= '\\"';
                    break;
                case '\\':
                    $canonical .= '\\\\';
                    break;
                case '/':
                    $canonical .= '\\/';
                    break;
                case 'b':
                    $canonical .= '\\b';
                    break;
                case 'f':
                    $canonical .= '\\f';
                    break;
                case 'n':
                    $canonical .= '\\n';
                    break;
                case 'r':
                    $canonical .= '\\r';
                    break;
                case 't':
                    $canonical .= '\\t';
                    break;
                case 'v':
                    $canonical .= '\\u000b';
                    break;
                case '0':
                    if (!$this->isEnd() && ctype_digit($this->json[$this->offset])) {
                        throw new \InvalidArgumentException('malformed JSON');
                    }
                    $canonical .= '\\u0000';
                    break;
                case 'x':
                    $canonical .= '\\u00' . $this->readHex(2);
                    break;
                case 'u':
                    $canonical .= '\\u' . $this->readHex(4);
                    break;
                default:
                    if ($this->matchAt("\xE2\x80\xA8", $escapeStart) || $this->matchAt("\xE2\x80\xA9", $escapeStart)) {
                        $this->offset = $escapeStart + 3;
                        break;
                    }
                    throw new \InvalidArgumentException('malformed JSON');
            }
        }

        throw new \InvalidArgumentException('malformed JSON');
    }

    private function parseNumberOrLiteral(): string
    {
        $remaining = substr($this->json, $this->offset);

        if (preg_match('/^[+-]?(?:Infinity|Inf)/i', $remaining, $matches) === 1 && $this->hasTokenBoundary(strlen($matches[0]))) {
            $this->offset += strlen($matches[0]);

            return str_starts_with($matches[0], '-') ? '-9e999' : '9e999';
        }
        if (preg_match('/^[+-]?(?:NaN|QNaN|SNaN)/i', $remaining, $matches) === 1 && $this->hasTokenBoundary(strlen($matches[0]))) {
            $this->offset += strlen($matches[0]);

            return 'null';
        }
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f]+/', $remaining, $matches) === 1) {
            $token = $matches[0];
            $this->offset += strlen($token);
            $negative = str_starts_with($token, '-');
            if ($token[0] === '-' || $token[0] === '+') {
                $token = substr($token, 1);
            }
            $digits = substr($token, 2);
            if (strlen(ltrim($digits, '0')) > 16) {
                return $negative ? '-9.0e999' : '9.0e999';
            }
            $number = (string) hexdec($digits);

            return $negative && $number !== '0' ? '-' . $number : $number;
        }

        $number = $this->parseJson5NumberToken();
        if ($number !== null) {
            return $number;
        }

        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*/', $remaining, $matches) === 1) {
            $token = $matches[0];
            if (!$this->hasTokenBoundary(strlen($token))) {
                throw new \InvalidArgumentException('malformed JSON');
            }
            $lower = strtolower($token);
            if ($lower === 'true' || $lower === 'false' || $lower === 'null') {
                $this->offset += strlen($token);

                return $lower;
            }
        }

        throw new \InvalidArgumentException('malformed JSON');
    }

    private function parseJson5NumberToken(): ?string
    {
        $start = $this->offset;
        $offset = $start;
        $negative = false;
        $positive = false;
        if ($offset < $this->length && ($this->json[$offset] === '+' || $this->json[$offset] === '-')) {
            $negative = $this->json[$offset] === '-';
            $positive = $this->json[$offset] === '+';
            $offset++;
        }

        $hasDigitsBeforeDot = false;
        $hasDigitsAfterDot = false;
        $hasFraction = false;
        if ($offset < $this->length && $this->json[$offset] === '.') {
            $hasFraction = true;
            $offset++;
            if ($offset >= $this->length || !ctype_digit($this->json[$offset])) {
                return null;
            }
            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
                $hasDigitsAfterDot = true;
            }
        } elseif ($offset < $this->length && ctype_digit($this->json[$offset])) {
            $hasDigitsBeforeDot = true;
            if ($this->json[$offset] === '0' && $offset + 1 < $this->length && ctype_digit($this->json[$offset + 1])) {
                $this->offset = $offset + 1;
                throw new \InvalidArgumentException('malformed JSON');
            }

            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
            }
            if ($offset < $this->length && $this->json[$offset] === '.') {
                $hasFraction = true;
                $offset++;
                while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                    $offset++;
                    $hasDigitsAfterDot = true;
                }
            }
        } else {
            return null;
        }

        $exponent = '';
        if ($offset < $this->length && ($this->json[$offset] === 'e' || $this->json[$offset] === 'E')) {
            $exponentOffset = $offset;
            $offset++;
            if ($offset < $this->length && ($this->json[$offset] === '+' || $this->json[$offset] === '-')) {
                $offset++;
            }
            if ($offset >= $this->length || !ctype_digit($this->json[$offset])) {
                $this->offset = $exponentOffset;
                throw new \InvalidArgumentException('malformed JSON');
            }
            while ($offset < $this->length && ctype_digit($this->json[$offset])) {
                $offset++;
            }
            $exponent = substr($this->json, $exponentOffset, $offset - $exponentOffset);
        }

        $token = substr($this->json, $start, $offset - $start);
        $this->offset = $offset;
        if (!$hasFraction && $exponent === '') {
            return $positive ? substr($token, 1) : $token;
        }

        $mantissaEnd = $exponent === '' ? strlen($token) : strlen($token) - strlen($exponent);
        $mantissa = substr($token, 0, $mantissaEnd);
        if ($positive) {
            $mantissa = substr($mantissa, 1);
        }
        if (!$hasDigitsBeforeDot && str_starts_with($mantissa, '.')) {
            $mantissa = '0' . $mantissa;
        } elseif (!$hasDigitsBeforeDot && str_starts_with($mantissa, '-.')) {
            $mantissa = '-0.' . substr($mantissa, 2);
        }
        if ($hasFraction && !$hasDigitsAfterDot) {
            $mantissa .= '0';
        }

        return $mantissa . $exponent;
    }

    private function readHex(int $digits): string
    {
        if ($this->offset + $digits > $this->length) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        $hex = substr($this->json, $this->offset, $digits);
        if (preg_match('/^[0-9A-Fa-f]+$/', $hex) !== 1) {
            throw new \InvalidArgumentException('malformed JSON');
        }

        $this->offset += $digits;

        return $hex;
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
                    throw new \InvalidArgumentException('malformed JSON');
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

        return !(ctype_alnum($char) || $char === '_' || $char === '$' || ord($char) >= 0x80);
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

    private static function escapeStringByte(string $byte): string
    {
        if ($byte === '"') {
            return '\\"';
        }
        if ($byte === '\\') {
            return '\\\\';
        }

        $ordinal = ord($byte);
        return match ($ordinal) {
            0x08 => '\\b',
            0x09 => '\\t',
            0x0a => '\\n',
            0x0c => '\\f',
            0x0d => '\\r',
            default => $ordinal <= 0x1f ? sprintf('\\u%04x', $ordinal) : $byte,
        };
    }

    private static function quoteString(string $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException('SQLite JSON string could not be encoded');
        }

        return $encoded;
    }

    public static function encodeDecodedJson(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'null';
            }
            if ($value === INF) {
                return '9e999';
            }
            if ($value === -INF) {
                return '-9e999';
            }
            $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (!is_string($encoded)) {
                throw new \InvalidArgumentException('SQLite JSON number could not be encoded');
            }

            return $encoded;
        }
        if (is_string($value)) {
            return self::quoteString($value);
        }
        if ($value instanceof \stdClass) {
            $members = [];
            foreach (get_object_vars($value) as $key => $memberValue) {
                $members[] = self::quoteString((string) $key) . ':' . self::encodeDecodedJson($memberValue);
            }

            return '{' . implode(',', $members) . '}';
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(',', array_map(self::encodeDecodedJson(...), $value)) . ']';
            }

            $members = [];
            foreach ($value as $key => $memberValue) {
                $members[] = self::quoteString((string) $key) . ':' . self::encodeDecodedJson($memberValue);
            }

            return '{' . implode(',', $members) . '}';
        }

        throw new \InvalidArgumentException('SQLite JSON value could not be encoded');
    }
}
