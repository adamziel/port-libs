<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCoreScalarFunction
{
    /**
     * @param list<mixed> $arguments
     */
    public static function sqlFunctionArguments(string $functionName, array $arguments): mixed
    {
        $normalized = strtolower($functionName);

        return match ($normalized) {
            'abs' => self::abs($arguments),
            'round' => self::round($arguments),
            'sign' => self::sign($arguments),
            'typeof' => self::typeof($arguments),
            'quote' => self::quote($arguments),
            'coalesce' => self::coalesce($arguments),
            'ifnull' => self::ifnull($arguments),
            'nullif' => self::nullif($arguments),
            'min' => self::minmax('min', $arguments),
            'max' => self::minmax('max', $arguments),
            'lower' => self::caseMap('lower', $arguments),
            'upper' => self::caseMap('upper', $arguments),
            'length' => self::length($arguments),
            'substr', 'substring' => self::substring($normalized, $arguments),
            'trim', 'ltrim', 'rtrim' => self::trim($normalized, $arguments),
            'replace' => self::replace($arguments),
            'instr' => self::instr($arguments),
            'concat' => self::concat($arguments),
            'concat_ws' => self::concatWithSeparator($arguments),
            'printf', 'format' => self::formatSql($normalized, $arguments),
            'hex' => self::hex($arguments),
            'unhex' => self::unhex($arguments),
            'char' => self::char($arguments),
            'unicode' => self::unicode($arguments),
            'octet_length' => self::octetLength($arguments),
            'zeroblob' => self::zeroblob($arguments),
            default => throw new \InvalidArgumentException("Unsupported SQLite core scalar function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function abs(array $arguments): int|float|null
    {
        self::assertArity('abs', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $number = self::coerceNumeric($arguments[0]);
        if (is_int($number)) {
            if ($number === PHP_INT_MIN) {
                throw new \OverflowException('SQLite abs() integer overflow');
            }

            return abs($number);
        }

        return abs($number);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function round(array $arguments): ?float
    {
        self::assertArity('round', $arguments, 1, 2);
        if ($arguments[0] === null || (array_key_exists(1, $arguments) && $arguments[1] === null)) {
            return null;
        }

        $precision = array_key_exists(1, $arguments) ? self::coerceInteger($arguments[1]) : 0;
        if ($precision < 0) {
            $precision = 0;
        }

        return round((float) self::coerceNumeric($arguments[0]), $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sign(array $arguments): ?int
    {
        self::assertArity('sign', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $number = self::coerceLosslessNumeric($arguments[0]);
        if ($number === null) {
            return null;
        }

        return $number <=> 0;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function typeof(array $arguments): string
    {
        self::assertArity('typeof', $arguments, 1, 1);

        return match (true) {
            $arguments[0] === null => 'null',
            $arguments[0] instanceof SQLiteBlobValue => 'blob',
            is_int($arguments[0]) || is_bool($arguments[0]) => 'integer',
            is_float($arguments[0]) => 'real',
            is_string($arguments[0]) => 'text',
            default => throw new \InvalidArgumentException('SQLite typeof() argument must be scalar, BLOB, or NULL'),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function quote(array $arguments): string
    {
        self::assertArity('quote', $arguments, 1, 1);
        $value = $arguments[0];
        if ($value === null) {
            return 'NULL';
        }
        if ($value instanceof SQLiteBlobValue) {
            return "X'" . strtoupper(bin2hex($value->bytes)) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NULL';
            }
            if (is_infinite($value)) {
                return $value > 0 ? 'Inf' : '-Inf';
            }

            return self::formatFloat($value);
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        throw new \InvalidArgumentException('SQLite quote() argument must be scalar, BLOB, or NULL');
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function coalesce(array $arguments): mixed
    {
        self::assertArity('coalesce', $arguments, 2, null);
        foreach ($arguments as $argument) {
            if ($argument !== null) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function ifnull(array $arguments): mixed
    {
        self::assertArity('ifnull', $arguments, 2, 2);

        return $arguments[0] ?? $arguments[1];
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function nullif(array $arguments): mixed
    {
        self::assertArity('nullif', $arguments, 2, 2);

        return self::sqliteValuesEqual($arguments[0], $arguments[1]) ? null : $arguments[0];
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function minmax(string $functionName, array $arguments): mixed
    {
        self::assertArity($functionName, $arguments, 2, null);
        foreach ($arguments as $argument) {
            if ($argument === null) {
                return null;
            }
            self::assertComparable($functionName, $argument);
        }

        $selected = $arguments[0];
        foreach (array_slice($arguments, 1) as $argument) {
            $comparison = self::compareSqlValues($argument, $selected);
            if (($functionName === 'min' && $comparison < 0) || ($functionName === 'max' && $comparison > 0)) {
                $selected = $argument;
            }
        }

        return $selected;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function caseMap(string $functionName, array $arguments): ?string
    {
        self::assertArity($functionName, $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }
        if ($arguments[0] instanceof SQLiteBlobValue) {
            $value = $arguments[0]->bytes;
        } elseif (is_scalar($arguments[0])) {
            $value = (string) $arguments[0];
        } else {
            throw new \InvalidArgumentException("SQLite {$functionName}() argument must be scalar, BLOB, or NULL");
        }

        return $functionName === 'lower'
            ? strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')
            : strtr($value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function length(array $arguments): ?int
    {
        self::assertArity('length', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }
        if ($arguments[0] instanceof SQLiteBlobValue) {
            return strlen($arguments[0]->bytes);
        }
        if (is_scalar($arguments[0])) {
            return count(self::splitTextUnits((string) $arguments[0]));
        }

        throw new \InvalidArgumentException('SQLite length() argument must be scalar, BLOB, or NULL');
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function substring(string $functionName, array $arguments): string|SQLiteBlobValue|null
    {
        self::assertArity($functionName, $arguments, 2, 3);
        if ($arguments[0] === null || $arguments[1] === null || (array_key_exists(2, $arguments) && $arguments[2] === null)) {
            return null;
        }

        $start = self::coerceInteger($arguments[1]);
        $length = array_key_exists(2, $arguments) ? self::coerceInteger($arguments[2]) : null;
        if ($arguments[0] instanceof SQLiteBlobValue) {
            [$offset, $byteLength] = self::substringWindow($start, $length, strlen($arguments[0]->bytes));
            $bytes = $byteLength === null
                ? substr($arguments[0]->bytes, $offset)
                : substr($arguments[0]->bytes, $offset, $byteLength);

            return new SQLiteBlobValue($bytes);
        }
        if (!is_scalar($arguments[0])) {
            throw new \InvalidArgumentException("SQLite {$functionName}() first argument must be scalar, BLOB, or NULL");
        }

        $units = self::splitTextUnits((string) $arguments[0]);
        [$offset, $byteLength] = self::substringWindow($start, $length, count($units));

        return implode('', $byteLength === null
            ? array_slice($units, $offset)
            : array_slice($units, $offset, $byteLength));
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function trim(string $functionName, array $arguments): string|null
    {
        self::assertArity($functionName, $arguments, 1, 2);
        if ($arguments[0] === null || (array_key_exists(1, $arguments) && $arguments[1] === null)) {
            return null;
        }

        $value = self::coerceText($functionName, $arguments[0], 'first');
        $characters = array_key_exists(1, $arguments)
            ? self::coerceText($functionName, $arguments[1], 'second')
            : ' ';
        if ($characters === '') {
            return $value;
        }

        $units = self::splitTextUnits($value);
        $trimSet = array_fill_keys(self::splitTextUnits($characters), true);
        $start = 0;
        $end = count($units) - 1;
        if ($functionName !== 'rtrim') {
            while ($start <= $end && isset($trimSet[$units[$start]])) {
                $start++;
            }
        }
        if ($functionName !== 'ltrim') {
            while ($end >= $start && isset($trimSet[$units[$end]])) {
                $end--;
            }
        }

        return implode('', array_slice($units, $start, $end - $start + 1));
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function replace(array $arguments): ?string
    {
        self::assertArity('replace', $arguments, 3, 3);
        if ($arguments[0] === null || $arguments[1] === null || $arguments[2] === null) {
            return null;
        }

        $value = self::coerceText('replace', $arguments[0], 'first');
        $from = self::coerceText('replace', $arguments[1], 'second');
        if ($from === '') {
            return $value;
        }

        return str_replace($from, self::coerceText('replace', $arguments[2], 'third'), $value);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function instr(array $arguments): ?int
    {
        self::assertArity('instr', $arguments, 2, 2);
        if ($arguments[0] === null || $arguments[1] === null) {
            return null;
        }

        if ($arguments[0] instanceof SQLiteBlobValue && $arguments[1] instanceof SQLiteBlobValue) {
            $position = strpos($arguments[0]->bytes, $arguments[1]->bytes);

            return $position === false ? 0 : $position + 1;
        }

        $haystack = self::coerceText('instr', $arguments[0], 'first');
        $needle = self::coerceText('instr', $arguments[1], 'second');
        if ($needle === '') {
            return 1;
        }

        $position = strpos($haystack, $needle);
        if ($position === false) {
            return 0;
        }
        if (self::isUtf8Text($haystack) && self::isUtf8Text($needle)) {
            return count(self::splitTextUnits(substr($haystack, 0, $position))) + 1;
        }

        return $position + 1;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function concat(array $arguments): string
    {
        self::assertArity('concat', $arguments, 1, null);
        $text = '';
        foreach ($arguments as $argument) {
            if ($argument !== null) {
                $text .= self::coerceText('concat', $argument, 'argument');
            }
        }

        return $text;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function concatWithSeparator(array $arguments): ?string
    {
        self::assertArity('concat_ws', $arguments, 2, null);
        if ($arguments[0] === null) {
            return null;
        }

        $separator = self::coerceText('concat_ws', $arguments[0], 'separator');
        $parts = [];
        foreach (array_slice($arguments, 1) as $argument) {
            if ($argument !== null) {
                $parts[] = self::coerceText('concat_ws', $argument, 'argument');
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function formatSql(string $functionName, array $arguments): ?string
    {
        self::assertArity($functionName, $arguments, 1, null);
        if ($arguments[0] === null) {
            return null;
        }

        $format = self::coerceText($functionName, $arguments[0], 'format');
        $argumentIndex = 1;
        $result = '';
        $length = strlen($format);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $format[$offset];
            if ($byte !== '%') {
                $result .= $byte;
                continue;
            }
            if ($offset + 1 >= $length) {
                $result .= '%';
                continue;
            }
            if ($format[$offset + 1] === '%') {
                $result .= '%';
                $offset++;
                continue;
            }

            $start = $offset;
            $offset++;
            while ($offset < $length && str_contains('-+ #0,', $format[$offset])) {
                $offset++;
            }
            while ($offset < $length && ctype_digit($format[$offset])) {
                $offset++;
            }
            if ($offset < $length && $format[$offset] === '.') {
                $offset++;
                while ($offset < $length && ctype_digit($format[$offset])) {
                    $offset++;
                }
            }
            if ($offset >= $length) {
                $result .= substr($format, $start);
                break;
            }

            $type = $format[$offset];
            $specifier = substr($format, $start, $offset - $start + 1);
            $argument = $argumentIndex < count($arguments) ? $arguments[$argumentIndex] : null;
            if ($type !== '%') {
                $argumentIndex++;
            }

            $result .= match ($type) {
                's', 'z' => self::sprintfText($specifier, $argument),
                'q' => self::sqliteQuoteEscaped($argument, false, $specifier),
                'Q' => $argument === null ? 'NULL' : "'" . self::sqliteQuoteEscaped($argument, false, $specifier) . "'",
                'w' => self::sqliteQuoteEscaped($argument, true, $specifier),
                'd', 'i', 'u', 'x', 'X', 'o' => self::sprintfInteger($specifier, $argument),
                'f', 'F', 'e', 'E', 'g', 'G' => self::sprintfFloat($specifier, $argument),
                'c' => self::sprintfCharacter($specifier, $argument),
                default => $specifier,
            };
        }

        return $result;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function hex(array $arguments): ?string
    {
        self::assertArity('hex', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $bytes = $arguments[0] instanceof SQLiteBlobValue
            ? $arguments[0]->bytes
            : self::coerceText('hex', $arguments[0], 'first');

        return strtoupper(bin2hex($bytes));
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function unhex(array $arguments): ?SQLiteBlobValue
    {
        self::assertArity('unhex', $arguments, 1, 2);
        if ($arguments[0] === null || (array_key_exists(1, $arguments) && $arguments[1] === null)) {
            return null;
        }

        $hex = self::coerceText('unhex', $arguments[0], 'first');
        if (array_key_exists(1, $arguments)) {
            $ignored = array_fill_keys(str_split(self::coerceText('unhex', $arguments[1], 'second')), true);
            $filtered = '';
            foreach (str_split($hex) as $byte) {
                if (!isset($ignored[$byte])) {
                    $filtered .= $byte;
                }
            }
            $hex = $filtered;
        }

        if (strlen($hex) % 2 !== 0 || preg_match('/\A[0-9A-Fa-f]*\z/', $hex) !== 1) {
            return null;
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return null;
        }

        return new SQLiteBlobValue($bytes);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function char(array $arguments): string
    {
        self::assertArity('char', $arguments, 0, null);
        $text = '';
        foreach ($arguments as $argument) {
            $codepoint = $argument === null ? 0 : self::coerceInteger($argument);
            $text .= self::utf8Codepoint($codepoint);
        }

        return $text;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function unicode(array $arguments): ?int
    {
        self::assertArity('unicode', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $value = self::coerceText('unicode', $arguments[0], 'first');
        if ($value === '') {
            return null;
        }

        return self::firstUtf8Codepoint($value);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function octetLength(array $arguments): ?int
    {
        self::assertArity('octet_length', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }
        if ($arguments[0] instanceof SQLiteBlobValue) {
            return strlen($arguments[0]->bytes);
        }

        return strlen(self::coerceText('octet_length', $arguments[0], 'first'));
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function zeroblob(array $arguments): SQLiteBlobValue
    {
        self::assertArity('zeroblob', $arguments, 1, 1);
        $length = max(0, self::coerceInteger($arguments[0]));

        return new SQLiteBlobValue(str_repeat("\0", $length));
    }

    private static function assertArity(string $functionName, array $arguments, int $minimum, ?int $maximum): void
    {
        $count = count($arguments);
        if ($count < $minimum || ($maximum !== null && $count > $maximum)) {
            $range = $maximum === null ? "{$minimum}+" : ($minimum === $maximum ? (string) $minimum : "{$minimum}-{$maximum}");
            throw new \InvalidArgumentException("SQLite {$functionName}() expects {$range} argument(s), {$count} given");
        }
    }

    private static function coerceNumeric(mixed $value): int|float
    {
        if ($value instanceof SQLiteBlobValue) {
            $value = $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $text = ltrim($value);
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $text, $matches) === 1) {
                return str_contains($matches[0], '.') || stripos($matches[0], 'e') !== false
                    ? (float) $matches[0]
                    : (int) $matches[0];
            }

            return 0;
        }

        throw new \InvalidArgumentException('SQLite numeric scalar argument must be scalar, BLOB, or NULL');
    }

    private static function coerceInteger(mixed $value): int
    {
        $number = self::coerceNumeric($value);

        return (int) $number;
    }

    private static function coerceLosslessNumeric(mixed $value): int|float|null
    {
        if ($value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $text = trim($value);
            if (preg_match('/\A[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?\z/', $text) !== 1) {
                return null;
            }

            return str_contains($text, '.') || stripos($text, 'e') !== false
                ? (float) $text
                : (int) $text;
        }

        throw new \InvalidArgumentException('SQLite numeric scalar argument must be scalar, BLOB, or NULL');
    }

    private static function sqliteValuesEqual(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        if ($left instanceof SQLiteBlobValue || $right instanceof SQLiteBlobValue) {
            return $left instanceof SQLiteBlobValue
                && $right instanceof SQLiteBlobValue
                && $left->bytes === $right->bytes;
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return (float) $left === (float) $right;
        }
        if (is_string($left) && is_string($right)) {
            return $left === $right;
        }

        return false;
    }

    private static function assertComparable(string $functionName, mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || is_scalar($value)) {
            return;
        }

        throw new \InvalidArgumentException("SQLite {$functionName}() arguments must be scalar, BLOB, or NULL");
    }

    private static function compareSqlValues(mixed $left, mixed $right): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        if ($leftRank === 1) {
            return (float) $left <=> (float) $right;
        }

        $leftText = $left instanceof SQLiteBlobValue ? $left->bytes : (string) $left;
        $rightText = $right instanceof SQLiteBlobValue ? $right->bytes : (string) $right;

        return $leftText <=> $rightText;
    }

    private static function sortRank(mixed $value): int
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        throw new \InvalidArgumentException('SQLite scalar comparison argument must be scalar or BLOB');
    }

    private static function coerceText(string $functionName, mixed $value, string $position): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException("SQLite {$functionName}() {$position} argument must be scalar, BLOB, or NULL");
    }

    private static function sprintfText(string $specifier, mixed $value): string
    {
        return sprintf(self::phpSprintfSpecifier($specifier, 's'), $value === null ? '' : self::coerceText('format', $value, 'argument'));
    }

    private static function sprintfInteger(string $specifier, mixed $value): string
    {
        return sprintf(self::phpSprintfSpecifier($specifier, substr($specifier, -1)), $value === null ? 0 : self::coerceInteger($value));
    }

    private static function sprintfFloat(string $specifier, mixed $value): string
    {
        return sprintf(self::phpSprintfSpecifier($specifier, substr($specifier, -1)), $value === null ? 0.0 : (float) self::coerceNumeric($value));
    }

    private static function sprintfCharacter(string $specifier, mixed $value): string
    {
        $character = $value === null ? "\0" : self::utf8Codepoint(self::coerceInteger($value));

        return sprintf(self::phpSprintfSpecifier($specifier, 's'), $character);
    }

    private static function sqliteQuoteEscaped(mixed $value, bool $identifier, string $specifier): string
    {
        $text = $value === null ? '' : self::coerceText('format', $value, 'argument');
        $escaped = str_replace($identifier ? '"' : "'", $identifier ? '""' : "''", $text);

        return sprintf(self::phpSprintfSpecifier($specifier, 's'), $escaped);
    }

    private static function phpSprintfSpecifier(string $specifier, string $type): string
    {
        $body = substr($specifier, 1, -1);
        $body = str_replace(',', '', $body);

        return '%' . $body . $type;
    }

    /**
     * @return list<string>
     */
    private static function splitTextUnits(string $value): array
    {
        if (self::isUtf8Text($value)) {
            /** @var list<string>|false $units */
            $units = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
            if ($units !== false) {
                return $units;
            }
        }

        return str_split($value);
    }

    private static function isUtf8Text(string $value): bool
    {
        return $value === '' || preg_match('//u', $value) === 1;
    }

    private static function formatFloat(float $value): string
    {
        $formatted = sprintf('%.15G', $value);

        return str_contains($formatted, 'E') ? str_replace('E', 'e', $formatted) : $formatted;
    }

    private static function utf8Codepoint(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            $codepoint = 0xfffd;
        }
        if (function_exists('mb_chr')) {
            $character = mb_chr($codepoint, 'UTF-8');
            if ($character !== false) {
                return $character;
            }
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3f)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function firstUtf8Codepoint(string $value): int
    {
        if (function_exists('mb_ord') && function_exists('mb_substr') && function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            $codepoint = mb_ord(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
            if ($codepoint !== false) {
                return $codepoint;
            }
        }

        $byte = ord($value[0]);
        if ($byte < 0x80) {
            return $byte;
        }
        $length = $byte >= 0xf0 ? 4 : ($byte >= 0xe0 ? 3 : ($byte >= 0xc0 ? 2 : 1));
        $codepoint = $byte & ((1 << (8 - $length - 1)) - 1);
        for ($i = 1; $i < $length && isset($value[$i]); $i++) {
            $codepoint = ($codepoint << 6) | (ord($value[$i]) & 0x3f);
        }

        return $codepoint;
    }

    /**
     * @return array{0:int,1:int|null}
     */
    private static function substringWindow(int $start, ?int $length, int $unitCount): array
    {
        $leftPadding = 0;
        if ($start > 0) {
            $offset = $start - 1;
        } elseif ($start < 0) {
            $offset = $unitCount + $start;
            if ($offset < 0) {
                $leftPadding = -$offset;
                $offset = 0;
            }
        } else {
            $offset = 0;
        }

        if ($length === null) {
            return [min($offset, $unitCount), null];
        }

        if ($start === 0 && $length > 0) {
            $length--;
        } elseif ($leftPadding > 0 && $length > 0) {
            $length = max(0, $length - $leftPadding);
        }

        if ($length < 0) {
            $end = min(max($offset, 0), $unitCount);
            $offset = max(0, $end + $length);

            return [$offset, $end - $offset];
        }

        return [min($offset, $unitCount), max(0, $length)];
    }
}
