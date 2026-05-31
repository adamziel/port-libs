<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCoreScalarFunction
{
    public const TARGET_SQLITE_VERSION = '3.50.0';
    public const TARGET_SQLITE_SOURCE_ID = '2026-05-26 8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';

    /**
     * @var list<string>
     */
    private const COMPILE_OPTIONS = [
        'ATOMIC_INTRINSICS=1',
        'DEFAULT_AUTOVACUUM',
        'DEFAULT_CACHE_SIZE=-2000',
        'DEFAULT_FILE_FORMAT=4',
        'DEFAULT_PAGE_SIZE=4096',
        'DEFAULT_RECURSIVE_TRIGGERS',
        'DEFAULT_SECTOR_SIZE=4096',
        'DEFAULT_SYNCHRONOUS=2',
        'DEFAULT_WAL_AUTOCHECKPOINT=1000',
        'DEFAULT_WAL_SYNCHRONOUS=2',
        'ENABLE_COLUMN_METADATA',
        'ENABLE_DBSTAT_VTAB',
        'ENABLE_FTS3',
        'ENABLE_FTS4',
        'ENABLE_FTS5',
        'ENABLE_GEOPOLY',
        'ENABLE_MATH_FUNCTIONS',
        'ENABLE_RTREE',
        'ENABLE_SESSION',
        'ENABLE_STAT4',
        'ENABLE_UNLOCK_NOTIFY',
        'MAX_ATTACHED=10',
        'MAX_COLUMN=2000',
        'MAX_COMPOUND_SELECT=500',
        'MAX_EXPR_DEPTH=1000',
        'MAX_FUNCTION_ARG=1000',
        'MAX_LENGTH=1000000000',
        'MAX_LIKE_PATTERN_LENGTH=50000',
        'MAX_PAGE_COUNT=4294967294',
        'MAX_PAGE_SIZE=65536',
        'MAX_SQL_LENGTH=1000000000',
        'MAX_TRIGGER_DEPTH=1000',
        'MAX_VARIABLE_NUMBER=32766',
        'THREADSAFE=1',
    ];

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
            'ceil', 'ceiling', 'floor', 'trunc' => self::roundingMath($normalized, $arguments),
            'sqrt', 'exp', 'ln', 'log', 'log10', 'log2', 'pow', 'power', 'mod' => self::numericMath($normalized, $arguments),
            'acos', 'asin', 'atan', 'atan2', 'cos', 'sin', 'tan', 'pi' => self::trigonometricMath($normalized, $arguments),
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
            'like' => self::like($arguments),
            'glob' => self::glob($arguments),
            'likely', 'unlikely', 'likelihood' => self::plannerLikelihood($normalized, $arguments),
            'iif', 'if' => self::conditionalValue($normalized, $arguments),
            'hex' => self::hex($arguments),
            'unhex' => self::unhex($arguments),
            'char' => self::char($arguments),
            'unicode' => self::unicode($arguments),
            'unistr' => self::unistr($arguments),
            'unistr_quote' => self::unistrQuote($arguments),
            'octet_length' => self::octetLength($arguments),
            'zeroblob' => self::zeroblob($arguments),
            'random' => self::random($arguments),
            'randomblob' => self::randomblob($arguments),
            'current_date', 'current_time', 'current_timestamp' => self::currentDateTime($normalized, $arguments),
            'date', 'time', 'datetime', 'julianday', 'unixepoch', 'strftime', 'timediff' => self::dateTime($normalized, $arguments),
            'sqlite_version', 'sqlite_source_id', 'sqlite_compileoption_get', 'sqlite_compileoption_used' => self::introspection($normalized, $arguments),
            default => throw new \InvalidArgumentException("Unsupported SQLite core scalar function: {$functionName}"),
        };
    }

    /**
     * @param list<array{function:string, arguments?:list<mixed>}> $calls
     * @return list<mixed>
     */
    public static function statementDateTimeResults(array $calls, ?\DateTimeImmutable $stepNow = null): array
    {
        $stepNow ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $stepNow = $stepNow->setTimezone(new \DateTimeZone('UTC'));
        $results = [];

        foreach ($calls as $call) {
            if (!isset($call['function']) || !is_string($call['function'])) {
                throw new \InvalidArgumentException('SQLite statement date/time calls require a function name');
            }
            $arguments = $call['arguments'] ?? [];
            if (!is_array($arguments) || array_is_list($arguments) === false) {
                throw new \InvalidArgumentException('SQLite statement date/time call arguments must be a list');
            }

            $results[] = self::sqlFunctionArgumentsWithStepNow($call['function'], $arguments, $stepNow);
        }

        return $results;
    }

    /**
     * @param list<mixed> $arguments
     * @param list<array{utcStart:string, offsetMinutes:int, failAtUtc?:string}> $localtimeRules
     */
    public static function sqlFunctionArgumentsWithLocaltimeRules(string $functionName, array $arguments, array $localtimeRules): mixed
    {
        $normalized = strtolower($functionName);
        if (!in_array($normalized, ['date', 'time', 'datetime', 'julianday', 'unixepoch', 'strftime'], true)) {
            return self::sqlFunctionArguments($functionName, $arguments);
        }

        return self::dateTime($normalized, $arguments, self::normalizeLocaltimeRules($localtimeRules));
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function isDeterministicSqlFunctionCall(string $functionName, array $arguments): bool
    {
        $normalized = strtolower($functionName);
        if (!in_array($normalized, ['date', 'time', 'datetime', 'julianday', 'unixepoch', 'strftime'], true)) {
            return true;
        }

        $minimum = $normalized === 'strftime' ? 2 : 0;
        self::assertArity($normalized, $arguments, $minimum, null);
        $timeValueIndex = $normalized === 'strftime' ? 1 : 0;
        $timeValue = array_key_exists($timeValueIndex, $arguments) ? $arguments[$timeValueIndex] : 'now';
        if (self::isNonDeterministicDateTimeValue($timeValue)) {
            return false;
        }

        foreach (array_slice($arguments, $timeValueIndex + 1) as $modifier) {
            if (self::isNonDeterministicDateTimeModifier($modifier)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function assertDeterministicSqlFunctionCall(string $functionName, array $arguments, string $schemaContext): void
    {
        if (!self::isDeterministicSqlFunctionCall($functionName, $arguments)) {
            throw new \InvalidArgumentException(
                'non-deterministic use of ' . strtolower($functionName) . '() in ' . $schemaContext
            );
        }
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function introspection(string $functionName, array $arguments): string|int|null
    {
        return match ($functionName) {
            'sqlite_version' => self::sqliteVersion($arguments),
            'sqlite_source_id' => self::sqliteSourceId($arguments),
            'sqlite_compileoption_get' => self::sqliteCompileOptionGet($arguments),
            'sqlite_compileoption_used' => self::sqliteCompileOptionUsed($arguments),
            default => throw new \InvalidArgumentException("Unsupported SQLite introspection function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sqliteVersion(array $arguments): string
    {
        self::assertArity('sqlite_version', $arguments, 0, 0);

        return self::TARGET_SQLITE_VERSION;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sqliteSourceId(array $arguments): string
    {
        self::assertArity('sqlite_source_id', $arguments, 0, 0);

        return self::TARGET_SQLITE_SOURCE_ID;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sqliteCompileOptionGet(array $arguments): ?string
    {
        self::assertArity('sqlite_compileoption_get', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $index = self::coerceInteger($arguments[0]);

        return self::COMPILE_OPTIONS[$index] ?? null;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sqliteCompileOptionUsed(array $arguments): ?int
    {
        self::assertArity('sqlite_compileoption_used', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $requested = self::normalizeCompileOptionName(self::coerceText('sqlite_compileoption_used', $arguments[0], 'first'));
        if ($requested === '') {
            return 0;
        }

        foreach (self::COMPILE_OPTIONS as $option) {
            if (self::compileOptionMatches($requested, $option)) {
                return 1;
            }
        }

        return 0;
    }

    private static function compileOptionMatches(string $requested, string $compiled): bool
    {
        $compiled = self::normalizeCompileOptionName($compiled);
        if ($requested === $compiled) {
            return true;
        }

        $compiledName = strtok($compiled, '=');
        $requestedName = strtok($requested, '=');

        return is_string($compiledName) && is_string($requestedName) && $requestedName === $compiledName;
    }

    private static function normalizeCompileOptionName(string $option): string
    {
        $option = strtoupper(trim($option));

        return str_starts_with($option, 'SQLITE_') ? substr($option, 7) : $option;
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
    private static function roundingMath(string $functionName, array $arguments): ?float
    {
        self::assertArity($functionName, $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $number = self::coerceLosslessNumeric($arguments[0]);
        if ($number === null) {
            return null;
        }

        return match ($functionName) {
            'ceil', 'ceiling' => ceil((float) $number),
            'floor' => floor((float) $number),
            'trunc' => (float) ((int) $number),
            default => throw new \InvalidArgumentException("Unsupported SQLite math function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function numericMath(string $functionName, array $arguments): ?float
    {
        $arity = match ($functionName) {
            'pow', 'power', 'mod' => 2,
            'log' => null,
            default => 1,
        };
        self::assertArity($functionName, $arguments, $arity ?? 1, $arity ?? 2);
        foreach ($arguments as $argument) {
            if ($argument === null) {
                return null;
            }
        }

        $left = self::coerceLosslessNumeric($arguments[0]);
        $right = array_key_exists(1, $arguments) ? self::coerceLosslessNumeric($arguments[1]) : null;
        if ($left === null || (array_key_exists(1, $arguments) && $right === null)) {
            return null;
        }

        return match ($functionName) {
            'sqrt' => $left < 0 ? null : sqrt((float) $left),
            'exp' => exp((float) $left),
            'ln' => $left <= 0 ? null : log((float) $left),
            'log' => array_key_exists(1, $arguments)
                ? (((float) $left) <= 0.0 || ((float) $left) == 1.0 || ((float) $right) <= 0.0 ? null : log((float) $right, (float) $left))
                : ($left <= 0 ? null : log10((float) $left)),
            'log10' => $left <= 0 ? null : log10((float) $left),
            'log2' => $left <= 0 ? null : log((float) $left, 2.0),
            'pow', 'power' => self::powSql((float) $left, (float) $right),
            'mod' => ((float) $right) == 0.0 ? null : fmod((float) $left, (float) $right),
            default => throw new \InvalidArgumentException("Unsupported SQLite math function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function trigonometricMath(string $functionName, array $arguments): ?float
    {
        $arity = $functionName === 'pi' ? 0 : ($functionName === 'atan2' ? 2 : 1);
        self::assertArity($functionName, $arguments, $arity, $arity);
        foreach ($arguments as $argument) {
            if ($argument === null) {
                return null;
            }
        }

        if ($functionName === 'pi') {
            return M_PI;
        }

        $left = self::coerceLosslessNumeric($arguments[0]);
        $right = array_key_exists(1, $arguments) ? self::coerceLosslessNumeric($arguments[1]) : null;
        if ($left === null || (array_key_exists(1, $arguments) && $right === null)) {
            return null;
        }

        return match ($functionName) {
            'acos' => ($left < -1 || $left > 1) ? null : acos((float) $left),
            'asin' => ($left < -1 || $left > 1) ? null : asin((float) $left),
            'atan' => atan((float) $left),
            'atan2' => atan2((float) $left, (float) $right),
            'cos' => cos((float) $left),
            'sin' => sin((float) $left),
            'tan' => tan((float) $left),
            default => throw new \InvalidArgumentException("Unsupported SQLite math function: {$functionName}"),
        };
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
            if (($functionName === 'min' && $comparison <= 0) || ($functionName === 'max' && $comparison > 0)) {
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
    private static function plannerLikelihood(string $functionName, array $arguments): mixed
    {
        if ($functionName === 'likelihood') {
            self::assertArity($functionName, $arguments, 2, 2);
            $probability = self::coerceLosslessNumeric($arguments[1]);
            if ($probability === null || $probability < 0.0 || $probability > 1.0) {
                throw new \InvalidArgumentException('SQLite likelihood() probability must be between 0.0 and 1.0');
            }

            return $arguments[0];
        }

        self::assertArity($functionName, $arguments, 1, 1);

        return $arguments[0];
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function like(array $arguments): ?int
    {
        self::assertArity('like', $arguments, 2, 3);
        if ($arguments[0] === null || $arguments[1] === null || (array_key_exists(2, $arguments) && $arguments[2] === null)) {
            return null;
        }

        $escape = array_key_exists(2, $arguments)
            ? self::coerceText('like', $arguments[2], 'escape')
            : null;

        return SQLiteDatabase::likeMatches(
            self::coerceText('like', $arguments[1], 'value'),
            self::coerceText('like', $arguments[0], 'pattern'),
            $escape
        ) ? 1 : 0;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function glob(array $arguments): ?int
    {
        self::assertArity('glob', $arguments, 2, 2);
        if ($arguments[0] === null || $arguments[1] === null) {
            return null;
        }

        return SQLiteDatabase::globMatches(
            self::coerceText('glob', $arguments[1], 'value'),
            self::coerceText('glob', $arguments[0], 'pattern')
        ) ? 1 : 0;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function conditionalValue(string $functionName, array $arguments): mixed
    {
        self::assertArity($functionName, $arguments, 2, null);

        $lastIndex = count($arguments) - 1;
        for ($index = 0; $index + 1 <= $lastIndex; $index += 2) {
            if (self::sqliteTruthValue($arguments[$index])) {
                return $arguments[$index + 1];
            }
        }

        if (count($arguments) % 2 === 1) {
            return $arguments[$lastIndex];
        }

        return null;
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
    private static function unistr(array $arguments): ?string
    {
        self::assertArity('unistr', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $value = self::coerceText('unistr', $arguments[0], 'first');
        $result = '';
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($value[$offset] !== '\\') {
                $result .= $value[$offset];
                continue;
            }
            if ($offset + 1 >= $length) {
                $result .= '\\';
                continue;
            }

            $next = $value[$offset + 1];
            if ($next === '\\') {
                $result .= '\\';
                $offset++;
                continue;
            }

            $hexLength = match ($next) {
                'u' => 4,
                'U' => 8,
                '+' => 6,
                default => ctype_xdigit($next) ? 4 : 0,
            };
            $hexStart = $next === 'u' || $next === 'U' || $next === '+' ? $offset + 2 : $offset + 1;
            if ($hexLength > 0 && $hexStart + $hexLength <= $length) {
                $hex = substr($value, $hexStart, $hexLength);
                if (preg_match('/\A[0-9A-Fa-f]{' . $hexLength . '}\z/', $hex) === 1) {
                    $result .= self::utf8Codepoint((int) hexdec($hex));
                    $offset = $hexStart + $hexLength - 1;
                    continue;
                }
            }

            throw new \InvalidArgumentException('SQLite unistr() invalid Unicode escape');
        }

        return $result;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function unistrQuote(array $arguments): string
    {
        self::assertArity('unistr_quote', $arguments, 1, 1);
        if ($arguments[0] === null || $arguments[0] instanceof SQLiteBlobValue || !is_string($arguments[0])) {
            return self::quote($arguments);
        }

        $value = $arguments[0];
        if (!self::containsControlCharacterOrBackslash($value)) {
            return self::quote([$value]);
        }

        $escaped = '';
        foreach (self::splitTextUnits($value) as $unit) {
            $escaped .= self::unistrQuotedUnit($unit);
        }

        return "unistr('{$escaped}')";
    }

    private static function containsControlCharacterOrBackslash(string $value): bool
    {
        return str_contains($value, '\\') || preg_match('/[\x01-\x1f]/', $value) === 1;
    }

    private static function unistrQuotedUnit(string $unit): string
    {
        return match ($unit) {
            "'" => "''",
            '\\' => '\\\\',
            "\x07" => '\\u0007',
            "\b" => '\\u0008',
            "\t" => '\\u0009',
            "\n" => '\\u000a',
            "\v" => '\\u000b',
            "\f" => '\\u000c',
            "\r" => '\\u000d',
            default => self::unistrQuotedDefault($unit),
        };
    }

    private static function unistrQuotedDefault(string $unit): string
    {
        if (strlen($unit) === 1) {
            $codepoint = ord($unit);
            if ($codepoint >= 1 && $codepoint <= 0x1f) {
                return sprintf('\\u%04x', $codepoint);
            }
        }

        return $unit;
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

    /**
     * @param list<mixed> $arguments
     */
    private static function random(array $arguments): int
    {
        self::assertArity('random', $arguments, 0, 0);

        return random_int(PHP_INT_MIN + 1, PHP_INT_MAX);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function randomblob(array $arguments): SQLiteBlobValue
    {
        self::assertArity('randomblob', $arguments, 1, 1);
        $length = max(1, self::coerceInteger($arguments[0]));

        return new SQLiteBlobValue(random_bytes($length));
    }

    /**
     * @param list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}>|null $localtimeRules
     */
    private static function dateTime(string $functionName, array $arguments, ?array $localtimeRules = null): int|float|string|null
    {
        $minimum = match ($functionName) {
            'strftime' => 2,
            'timediff' => 2,
            default => 0,
        };
        self::assertArity($functionName, $arguments, $minimum, null);
        if ($functionName === 'timediff') {
            self::assertArity($functionName, $arguments, 2, 2);
            if ($arguments[0] === null || $arguments[1] === null) {
                return null;
            }

            return self::timeDifference($arguments[0], $arguments[1]);
        }
        if ($functionName === 'strftime' && $arguments[0] === null) {
            return null;
        }

        $timeValueIndex = $functionName === 'strftime' ? 1 : 0;
        $timeValue = array_key_exists($timeValueIndex, $arguments) ? $arguments[$timeValueIndex] : 'now';
        if ($timeValue === null) {
            return null;
        }

        $modifiers = array_slice($arguments, $timeValueIndex + 1);
        foreach ($modifiers as $modifier) {
            if ($modifier === null) {
                return null;
            }
        }

        $subsecond = false;
        foreach ($modifiers as $modifier) {
            $modifierText = strtolower(trim(self::coerceText($functionName, $modifier, 'modifier')));
            if ($modifierText === 'subsec' || $modifierText === 'subsecond') {
                $subsecond = true;
            }
        }

        $state = self::parseDateTimeState($timeValue, $modifiers);
        if ($state === null) {
            return null;
        }
        $instant = $state['instant'];
        $floorCandidate = $state['floor'];
        $utcTimeline = self::dateTimeValueHasExplicitUtcTimeline($timeValue);
        $localtimeApplied = false;
        foreach ($modifiers as $modifier) {
            $modifierText = strtolower(trim(self::coerceText($functionName, $modifier, 'modifier')));
            if ($modifierText === 'unixepoch' || $modifierText === 'julianday' || $modifierText === 'auto' || $modifierText === 'subsec' || $modifierText === 'subsecond') {
                continue;
            }
            if ($modifierText === 'utc') {
                if ($localtimeRules !== null && !$utcTimeline) {
                    $instant = self::localtimeToUtc($instant, $localtimeRules);
                }
                $utcTimeline = true;
                $localtimeApplied = false;
                $floorCandidate = null;
                continue;
            }
            if ($modifierText === 'localtime') {
                if ($localtimeRules !== null && !$localtimeApplied) {
                    $instant = self::utcToLocaltime($instant, $localtimeRules);
                }
                $utcTimeline = false;
                $localtimeApplied = true;
                $floorCandidate = null;
                continue;
            }
            if ($modifierText === 'floor') {
                if ($floorCandidate !== null) {
                    $instant = $floorCandidate;
                }
                $floorCandidate = null;
                continue;
            }
            if ($modifierText === 'ceiling') {
                $floorCandidate = null;
                continue;
            }
            if (preg_match('/\Astart of (day|month|year)\z/', $modifierText, $matches) === 1) {
                $instant = match ($matches[1]) {
                    'day' => $instant->setTime(0, 0, 0),
                    'month' => $instant->setDate((int) $instant->format('Y'), (int) $instant->format('m'), 1)->setTime(0, 0, 0),
                    'year' => $instant->setDate((int) $instant->format('Y'), 1, 1)->setTime(0, 0, 0),
                };
                $floorCandidate = null;
                continue;
            }
            if (preg_match('/\Aweekday\s+([0-6])\z/', $modifierText, $matches) === 1) {
                $target = (int) $matches[1];
                $current = (int) $instant->format('w');
                $days = ($target - $current + 7) % 7;
                if ($days > 0) {
                    $instant = $instant->modify("+{$days} days");
                }
                $floorCandidate = null;
                continue;
            }
            if (preg_match('/\A([+-]?)(\d{1,2}):([0-5]\d)(?::([0-5]\d))?\z/', $modifierText, $matches) === 1) {
                $seconds = ((int) $matches[2] * 3600) + ((int) $matches[3] * 60) + (isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0);
                if ($matches[1] === '-') {
                    $seconds *= -1;
                }
                $instant = self::modifyBySeconds($instant, (float) $seconds);
                $floorCandidate = null;
                continue;
            }
            if (preg_match('/\A([+-])(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d+))?)?)?\z/', $modifierText, $matches) === 1) {
                if ((int) $matches[3] > 11 || (int) $matches[4] > 30) {
                    return null;
                }
                [$instant, $floorCandidate] = self::applyDateTimeYearMonthDayModifier(
                    $instant,
                    $matches[1] === '-' ? -1 : 1,
                    (int) $matches[2],
                    (int) $matches[3],
                    (int) $matches[4]
                );
                if (isset($matches[5]) && $matches[5] !== '') {
                    $seconds = ((int) $matches[5] * 3600) + ((int) $matches[6] * 60) + (isset($matches[7]) && $matches[7] !== '' ? (int) $matches[7] : 0);
                    if ($matches[1] === '-') {
                        $seconds *= -1;
                    }
                    $instant = self::modifyBySeconds($instant, (float) $seconds);
                    $floorCandidate = null;
                }
                continue;
            }
            if (preg_match('/\A([+-]?(?:\d+(?:\.\d*)?|\.\d+))\s+(second|seconds|minute|minutes|hour|hours|day|days|month|months|year|years)\z/', $modifierText, $matches) === 1) {
                [$instant, $floorCandidate] = self::applyDateTimeAmountModifier($instant, (float) $matches[1], $matches[2]);
                continue;
            }

            return null;
        }

        if (!self::isDateTimeInSQLiteRange($instant)) {
            return null;
        }

        return match ($functionName) {
            'date' => $instant->format('Y-m-d'),
            'time' => self::formatTime($instant, $subsecond),
            'datetime' => self::formatDateTime($instant, $subsecond),
            'unixepoch' => $subsecond ? self::unixTimestampFloat($instant) : (int) $instant->format('U'),
            'julianday' => self::unixTimestampFloat($instant) / 86400.0 + 2440587.5,
            'strftime' => self::strftimeSql(self::coerceText('strftime', $arguments[0], 'format'), $instant),
            default => throw new \InvalidArgumentException("Unsupported SQLite date/time function: {$functionName}"),
        };
    }

    private static function dateTimeValueHasExplicitUtcTimeline(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if ($value instanceof SQLiteBlobValue) {
            $value = $value->bytes;
        }
        if (!is_string($value)) {
            return false;
        }
        $text = trim($value);
        if (strcasecmp($text, 'now') === 0) {
            return true;
        }
        if (preg_match('/\A[+-]?(?:\d+(?:\.\d*)?|\.\d+)\z/', $text) === 1) {
            return true;
        }

        return preg_match('/(?:Z|[+-]\d{2}:\d{2})\z/i', $text) === 1;
    }

    /**
     * @param list<array{utcStart:string, offsetMinutes:int, failAtUtc?:string}> $rules
     * @return list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}>
     */
    private static function normalizeLocaltimeRules(array $rules): array
    {
        if ($rules === []) {
            throw new \InvalidArgumentException('SQLite localtime rules require at least one transition');
        }
        $timezone = new \DateTimeZone('UTC');
        $normalized = [];
        foreach ($rules as $rule) {
            if (!isset($rule['utcStart']) || !is_string($rule['utcStart']) || !isset($rule['offsetMinutes']) || !is_int($rule['offsetMinutes'])) {
                throw new \InvalidArgumentException('SQLite localtime rules require utcStart and offsetMinutes');
            }
            $row = [
                'utcStart' => new \DateTimeImmutable($rule['utcStart'], $timezone),
                'offsetMinutes' => $rule['offsetMinutes'],
            ];
            if (isset($rule['failAtUtc'])) {
                if (!is_string($rule['failAtUtc'])) {
                    throw new \InvalidArgumentException('SQLite localtime rules failAtUtc must be a string');
                }
                $row['failAtUtc'] = new \DateTimeImmutable($rule['failAtUtc'], $timezone);
            }
            $normalized[] = $row;
        }
        usort(
            $normalized,
            static fn (array $left, array $right): int => $left['utcStart'] <=> $right['utcStart']
        );

        return $normalized;
    }

    /**
     * @param list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}> $rules
     */
    private static function utcToLocaltime(\DateTimeImmutable $instant, array $rules): \DateTimeImmutable
    {
        foreach ($rules as $rule) {
            if (isset($rule['failAtUtc']) && self::formatDateTime($rule['failAtUtc'], true) === self::formatDateTime($instant, true)) {
                throw new \RuntimeException('local time unavailable');
            }
        }

        return self::modifyBySeconds($instant, (float) self::localtimeOffsetForUtc($instant, $rules) * 60.0);
    }

    /**
     * @param list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}> $rules
     */
    private static function localtimeToUtc(\DateTimeImmutable $local, array $rules): \DateTimeImmutable
    {
        $candidates = [];
        foreach ($rules as $rule) {
            $offset = $rule['offsetMinutes'];
            $candidate = self::modifyBySeconds($local, (float) -$offset * 60.0);
            if (self::formatDateTime(self::utcToLocaltime($candidate, $rules), true) === self::formatDateTime($local, true)) {
                $candidates[] = $candidate;
            }
        }
        if ($candidates !== []) {
            usort($candidates, static fn (\DateTimeImmutable $left, \DateTimeImmutable $right): int => $left <=> $right);

            return $candidates[array_key_last($candidates)];
        }

        return self::modifyBySeconds($local, (float) -self::localtimeOffsetForLocal($local, $rules) * 60.0);
    }

    /**
     * @param list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}> $rules
     */
    private static function localtimeOffsetForUtc(\DateTimeImmutable $instant, array $rules): int
    {
        $offset = $rules[0]['offsetMinutes'];
        foreach ($rules as $rule) {
            if ($instant < $rule['utcStart']) {
                break;
            }
            $offset = $rule['offsetMinutes'];
        }

        return $offset;
    }

    /**
     * @param list<array{utcStart:\DateTimeImmutable, offsetMinutes:int, failAtUtc?:\DateTimeImmutable}> $rules
     */
    private static function localtimeOffsetForLocal(\DateTimeImmutable $local, array $rules): int
    {
        $offset = $rules[0]['offsetMinutes'];
        foreach ($rules as $rule) {
            $transitionLocal = self::modifyBySeconds($rule['utcStart'], (float) $rule['offsetMinutes'] * 60.0);
            if ($local < $transitionLocal) {
                break;
            }
            $offset = $rule['offsetMinutes'];
        }

        return $offset;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function currentDateTime(string $functionName, array $arguments): string
    {
        self::assertArity($functionName, $arguments, 0, 0);

        return match ($functionName) {
            'current_date' => self::dateTime('date', ['now']),
            'current_time' => self::dateTime('time', ['now']),
            'current_timestamp' => self::dateTime('datetime', ['now']),
            default => throw new \InvalidArgumentException("Unsupported SQLite current date/time function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function sqlFunctionArgumentsWithStepNow(string $functionName, array $arguments, \DateTimeImmutable $stepNow): mixed
    {
        $normalized = strtolower($functionName);
        $instant = self::formatDateTime($stepNow->setTimezone(new \DateTimeZone('UTC')), true);

        return match ($normalized) {
            'current_date' => self::dateTime('date', [$instant]),
            'current_time' => self::dateTime('time', [$instant]),
            'current_timestamp' => self::dateTime('datetime', [$instant]),
            'date', 'time', 'datetime', 'julianday', 'unixepoch' => self::dateTime($normalized, self::replaceStatementNowArgument($arguments, 0, $instant, $normalized === 'unixepoch')),
            'strftime' => self::dateTime('strftime', self::replaceStatementNowArgument($arguments, 1, $instant, false)),
            default => self::sqlFunctionArguments($functionName, $arguments),
        };
    }

    /**
     * @param list<mixed> $arguments
     * @return list<mixed>
     */
    private static function replaceStatementNowArgument(array $arguments, int $timeValueIndex, string $instant, bool $preserveSubsecond): array
    {
        $replaced = [];
        foreach ($arguments as $index => $argument) {
            if ($index === $timeValueIndex && is_string($argument) && strcasecmp(trim($argument), 'now') === 0) {
                $replaced[] = $preserveSubsecond ? $instant : substr($instant, 0, 19);
                continue;
            }

            $replaced[] = $argument;
        }

        return $replaced;
    }

    private static function isDateTimeInSQLiteRange(\DateTimeImmutable $instant): bool
    {
        $julianDay = self::unixTimestampFloat($instant) / 86400.0 + 2440587.5;
        $year = (int) $instant->format('Y');
        if ($year > 9999 || $year < -4713) {
            return false;
        }

        return self::isSQLiteJulianDayInRange($julianDay);
    }

    private static function isSQLiteJulianDayInRange(float $julianDay): bool
    {
        return $julianDay >= -0.00000001 && $julianDay <= 5373484.49999999;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private static function applyDateTimeAmountModifier(\DateTimeImmutable $instant, float $amount, string $unit): array
    {
        $normalized = rtrim($unit, 's');
        $whole = (int) ($amount < 0 ? ceil($amount) : floor($amount));
        $fraction = $amount - (float) $whole;
        $floorCandidate = null;

        if ($whole !== 0) {
            if ($normalized === 'month' || $normalized === 'year') {
                [$instant, $floorCandidate] = self::applyDateTimeMonthShift(
                    $instant,
                    $normalized === 'year' ? $whole * 12 : $whole
                );
            } else {
                $instant = $instant->modify(sprintf('%+d %s', $whole, $normalized . 's'));
            }
        }

        if (abs($fraction) < 0.000000001) {
            return [$instant, $floorCandidate];
        }

        $seconds = match ($normalized) {
            'second' => $fraction,
            'minute' => $fraction * 60.0,
            'hour' => $fraction * 3600.0,
            'day' => $fraction * 86400.0,
            'month' => $fraction * 30.0 * 86400.0,
            'year' => $fraction * 365.0 * 86400.0,
            default => throw new \InvalidArgumentException("Unsupported SQLite date/time modifier unit: {$unit}"),
        };

        return [self::modifyBySeconds($instant, $seconds), null];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private static function applyDateTimeYearMonthDayModifier(\DateTimeImmutable $instant, int $sign, int $years, int $months, int $days): array
    {
        [$instant, $floorCandidate] = self::applyDateTimeMonthShift($instant, $sign * ($years * 12 + $months));
        if ($days !== 0) {
            $instant = $instant->modify(sprintf('%+d days', $sign * $days));
            $floorCandidate = $floorCandidate?->modify(sprintf('%+d days', $sign * $days));
        }

        return [$instant, $floorCandidate];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private static function applyDateTimeMonthShift(\DateTimeImmutable $instant, int $months): array
    {
        if ($months === 0) {
            return [$instant, null];
        }

        $year = (int) $instant->format('Y');
        $month = (int) $instant->format('m');
        $day = (int) $instant->format('d');
        $targetMonthIndex = $year * 12 + ($month - 1) + $months;
        $targetYear = intdiv($targetMonthIndex, 12);
        $targetMonth = $targetMonthIndex % 12 + 1;
        if ($targetMonth <= 0) {
            $targetMonth += 12;
            $targetYear--;
        }
        $daysInTargetMonth = self::daysInGregorianMonth($targetYear, $targetMonth);
        $floorCandidate = null;
        if ($day > $daysInTargetMonth) {
            $floorCandidate = $instant
                ->setDate($targetYear, $targetMonth, $daysInTargetMonth)
                ->setTime((int) $instant->format('H'), (int) $instant->format('i'), (int) $instant->format('s'), (int) $instant->format('u'));
        }

        return [$instant->modify(sprintf('%+d months', $months)), $floorCandidate];
    }

    private static function daysInGregorianMonth(int $year, int $month): int
    {
        if ($month === 2) {
            return ($year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0)) ? 29 : 28;
        }

        return in_array($month, [4, 6, 9, 11], true) ? 30 : 31;
    }

    private static function modifyBySeconds(\DateTimeImmutable $instant, float $seconds): \DateTimeImmutable
    {
        $microseconds = (int) round($seconds * 1000000.0);
        if ($microseconds === 0) {
            return $instant;
        }

        $sign = $microseconds < 0 ? '-' : '+';
        $absolute = abs($microseconds);
        $wholeSeconds = intdiv($absolute, 1000000);
        $remainingMicroseconds = $absolute % 1000000;
        if ($wholeSeconds > 0) {
            $instant = $instant->modify("{$sign}{$wholeSeconds} seconds");
        }
        if ($remainingMicroseconds > 0) {
            $instant = $instant->modify("{$sign}{$remainingMicroseconds} microseconds");
        }

        return $instant;
    }

    private static function formatTime(\DateTimeImmutable $instant, bool $subsecond): string
    {
        return $subsecond ? $instant->format('H:i:s') . self::millisecondSuffix($instant) : $instant->format('H:i:s');
    }

    private static function formatDateTime(\DateTimeImmutable $instant, bool $subsecond): string
    {
        return $subsecond ? $instant->format('Y-m-d H:i:s') . self::millisecondSuffix($instant) : $instant->format('Y-m-d H:i:s');
    }

    private static function millisecondSuffix(\DateTimeImmutable $instant): string
    {
        return sprintf('.%03d', (int) floor(((int) $instant->format('u')) / 1000));
    }

    private static function timeDifference(mixed $left, mixed $right): string
    {
        $leftInstant = self::parseDateTimeValue($left, []);
        $rightInstant = self::parseDateTimeValue($right, []);
        $negative = $leftInstant < $rightInstant;
        $interval = $negative ? $leftInstant->diff($rightInstant) : $rightInstant->diff($leftInstant);

        return sprintf(
            '%s%04d-%02d-%02d %02d:%02d:%02d.%03d',
            $negative ? '-' : '+',
            $interval->y,
            $interval->m,
            $interval->d,
            $interval->h,
            $interval->i,
            $interval->s,
            (int) floor($interval->f * 1000.0)
        );
    }

    /**
     * @param list<mixed> $modifiers
     */
    private static function parseDateTimeValue(mixed $value, array $modifiers): ?\DateTimeImmutable
    {
        $state = self::parseDateTimeState($value, $modifiers);

        return $state['instant'] ?? null;
    }

    /**
     * @param list<mixed> $modifiers
     * @return array{instant: \DateTimeImmutable, floor: ?\DateTimeImmutable}|null
     */
    private static function parseDateTimeState(mixed $value, array $modifiers): ?array
    {
        $timezone = new \DateTimeZone('UTC');
        $modifierTexts = array_map(
            static fn (mixed $modifier): string => strtolower(trim($modifier instanceof SQLiteBlobValue ? $modifier->bytes : (string) $modifier)),
            $modifiers
        );

        $timeInterpretationModifiers = array_intersect($modifierTexts, ['unixepoch', 'julianday', 'auto']);
        if (count($timeInterpretationModifiers) > 1) {
            return null;
        }

        $unixepochOffset = array_search('unixepoch', $modifierTexts, true);
        if ($unixepochOffset !== false) {
            if ($unixepochOffset !== 0) {
                return null;
            }
            $numeric = self::coerceLosslessNumeric($value);
            if ($numeric === null) {
                return null;
            }

            return ['instant' => self::dateTimeFromUnixTimestamp($numeric, $timezone), 'floor' => null];
        }
        $juliandayOffset = array_search('julianday', $modifierTexts, true);
        if ($juliandayOffset !== false) {
            if ($juliandayOffset !== 0) {
                return null;
            }
            $numeric = self::coerceLosslessNumeric($value);
            if ($numeric === null || !self::isSQLiteJulianDayInRange((float) $numeric)) {
                return null;
            }

            return ['instant' => self::dateTimeFromJulianDay($numeric, $timezone), 'floor' => null];
        }
        $autoOffset = array_search('auto', $modifierTexts, true);
        if ($autoOffset !== false) {
            if ($autoOffset !== 0) {
                return null;
            }
            $numeric = self::coerceLosslessNumeric($value);
            if ($numeric === null) {
                return self::parseDateTimeState($value, []);
            }
            if ($numeric >= 0.0 && $numeric <= 5373484.4999999) {
                return ['instant' => self::dateTimeFromJulianDay($numeric, $timezone), 'floor' => null];
            }
            if ($numeric < -210866760000 || $numeric > 253402300799) {
                return null;
            }

            return ['instant' => self::dateTimeFromUnixTimestamp($numeric, $timezone), 'floor' => null];
        }
        if (is_int($value) || is_float($value)) {
            if (!self::isSQLiteJulianDayInRange((float) $value)) {
                return null;
            }

            return ['instant' => self::dateTimeFromJulianDay((float) $value, $timezone), 'floor' => null];
        }

        $text = trim(self::coerceText('date/time', $value, 'time-value'));
        if (preg_match('/\A[+-]?(?:\d+(?:\.\d*)?|\.\d+)\z/', $text) === 1) {
            if (!self::isSQLiteJulianDayInRange((float) $text)) {
                return null;
            }

            return ['instant' => self::dateTimeFromJulianDay((float) $text, $timezone), 'floor' => null];
        }
        if (strcasecmp($text, 'now') === 0) {
            return ['instant' => new \DateTimeImmutable('now', $timezone), 'floor' => null];
        }
        if (preg_match('/\A\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:\s*(?:Z|[+-]\d{2}:\d{2}))?\z/i', $text) === 1) {
            $normalized = self::normalizeDateTimeText('2000-01-01 ' . $text);

            try {
                return ['instant' => (new \DateTimeImmutable($normalized, $timezone))->setTimezone($timezone), 'floor' => null];
            } catch (\Exception) {
                return null;
            }
        }
        if (preg_match('/\A(-?\d{4})-(\d{2})-(\d{2})\z/', $text, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return null;
            }
            $floorCandidate = null;
            if ($month >= 1 && $month <= 12 && $day > self::daysInGregorianMonth($year, $month)) {
                $floorCandidate = new \DateTimeImmutable(
                    sprintf('%04d-%02d-%02d 00:00:00', $year, $month, self::daysInGregorianMonth($year, $month)),
                    $timezone
                );
            }

            try {
                return ['instant' => new \DateTimeImmutable($text . ' 00:00:00', $timezone), 'floor' => $floorCandidate];
            } catch (\Exception) {
                return null;
            }
        }
        if (preg_match('/\A(-?\d{4})-(\d{2})-(\d{2})(?:[ T]+)(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(?:\s*(?:Z|[+-]\d{2}:\d{2}))?\z/i', $text, $matches) === 1) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $hour = (int) $matches[4];
            $minute = (int) $matches[5];
            $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : 0;
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $hour > 24 || $minute > 59 || $second > 59) {
                return null;
            }
            $normalized = self::normalizeDateTimeText($text);

            try {
                return ['instant' => (new \DateTimeImmutable($normalized, $timezone))->setTimezone($timezone), 'floor' => null];
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private static function isNonDeterministicDateTimeValue(mixed $value): bool
    {
        return is_string($value) && strcasecmp(trim($value), 'now') === 0;
    }

    private static function isNonDeterministicDateTimeModifier(mixed $modifier): bool
    {
        if ($modifier === null) {
            return false;
        }
        $text = strtolower(trim($modifier instanceof SQLiteBlobValue ? $modifier->bytes : (string) $modifier));

        return $text === 'localtime' || $text === 'utc';
    }

    private static function normalizeDateTimeText(string $text): string
    {
        $normalized = preg_replace('/[ T]+/', ' ', trim($text));
        if ($normalized === null) {
            throw new \InvalidArgumentException('Failed to normalize SQLite date/time value');
        }
        $normalized = preg_replace_callback(
            '/(\d{2}:\d{2}:\d{2}\.)(\d+)/',
            static fn (array $matches): string => $matches[1] . substr($matches[2], 0, 6),
            $normalized
        );
        if ($normalized === null) {
            throw new \InvalidArgumentException('Failed to normalize SQLite date/time fractional seconds');
        }
        $normalized = preg_replace('/\s+(Z|[+-]\d{2}:\d{2})\z/i', '$1', $normalized);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Failed to normalize SQLite date/time timezone suffix');
        }
        if (preg_match('/Z\z/i', $normalized) === 1) {
            $normalized = rtrim($normalized, 'Zz') . '+00:00';
        }

        return $normalized;
    }

    private static function dateTimeFromUnixTimestamp(float|int $seconds, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $floatSeconds = (float) $seconds;
        $integerSeconds = (int) floor($floatSeconds);
        $microseconds = (int) round(($floatSeconds - (float) $integerSeconds) * 1000000.0);
        if ($microseconds >= 1000000) {
            $integerSeconds++;
            $microseconds -= 1000000;
        }

        return (new \DateTimeImmutable('@' . (string) $integerSeconds))
            ->setTimezone($timezone)
            ->modify(sprintf('+%d microseconds', $microseconds));
    }

    private static function dateTimeFromJulianDay(float|int $julianDay, \DateTimeZone $timezone): \DateTimeImmutable
    {
        if ((float) $julianDay < 1721425.5) {
            return self::dateTimeFromJulianDayCalendar((float) $julianDay, $timezone);
        }

        $seconds = ((float) $julianDay - 2440587.5) * 86400.0;

        return self::dateTimeFromUnixTimestamp($seconds, $timezone);
    }

    private static function dateTimeFromJulianDayCalendar(float $julianDay, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $shifted = $julianDay + 0.5;
        $z = (int) floor($shifted);
        $fraction = $shifted - (float) $z;
        $alpha = (int) floor(((float) $z - 1867216.25) / 36524.25);
        $a = $z + 1 + $alpha - (int) floor((float) $alpha / 4.0);
        $b = $a + 1524;
        $c = (int) floor(((float) $b - 122.1) / 365.25);
        $d = (int) floor(365.25 * (float) $c);
        $e = (int) floor((float) ($b - $d) / 30.6001);
        $day = $b - $d - (int) floor(30.6001 * (float) $e);
        $month = $e < 14 ? $e - 1 : $e - 13;
        $year = $month > 2 ? $c - 4716 : $c - 4715;

        $microseconds = (int) round($fraction * 86400000000.0);
        if ($microseconds >= 86400000000) {
            $microseconds -= 86400000000;
            $day++;
        }

        $date = $year < 0
            ? sprintf('-%04d-%02d-%02d 00:00:00', -$year, $month, $day)
            : sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day);

        return self::modifyBySeconds(new \DateTimeImmutable($date, $timezone), (float) $microseconds / 1000000.0);
    }

    private static function strftimeSql(string $format, \DateTimeImmutable $instant): ?string
    {
        $result = '';
        $length = strlen($format);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($format[$offset] !== '%' || $offset + 1 >= $length) {
                $result .= $format[$offset];
                continue;
            }
            $offset++;
            $replacement = match ($format[$offset]) {
                '%' => '%',
                'Y' => $instant->format('Y'),
                'G' => $instant->format('o'),
                'g' => substr($instant->format('o'), -2),
                'm' => $instant->format('m'),
                'd' => $instant->format('d'),
                'e' => sprintf('%2d', (int) $instant->format('j')),
                'F' => $instant->format('Y-m-d'),
                'H' => $instant->format('H'),
                'k' => sprintf('%2d', (int) $instant->format('G')),
                'I' => $instant->format('h'),
                'l' => sprintf('%2d', (int) $instant->format('g')),
                'M' => $instant->format('i'),
                'S' => $instant->format('s'),
                'f' => sprintf('%02d.%03d', (int) $instant->format('s'), (int) floor(((int) $instant->format('u')) / 1000)),
                'j' => sprintf('%03d', (int) $instant->format('z') + 1),
                'p' => $instant->format('A'),
                'P' => strtolower($instant->format('A')),
                'R' => $instant->format('H:i'),
                's' => $instant->format('U'),
                'T' => $instant->format('H:i:s'),
                'u' => $instant->format('N'),
                'U' => self::strftimeWeekNumber($instant, 0),
                'V' => $instant->format('W'),
                'w' => $instant->format('w'),
                'W' => self::strftimeWeekNumber($instant, 1),
                'J' => rtrim(rtrim(sprintf('%.9F', self::unixTimestampFloat($instant) / 86400.0 + 2440587.5), '0'), '.'),
                default => null,
            };
            if ($replacement === null) {
                return null;
            }
            $result .= $replacement;
        }

        return $result;
    }

    private static function unixTimestampFloat(\DateTimeImmutable $instant): float
    {
        return (float) $instant->format('U') + ((float) $instant->format('u') / 1000000.0);
    }

    private static function strftimeWeekNumber(\DateTimeImmutable $instant, int $firstWeekday): string
    {
        $dayOfYear = (int) $instant->format('z');
        $janFirst = $instant->setDate((int) $instant->format('Y'), 1, 1);
        $janFirstWeekday = (int) $janFirst->format('w');
        $daysUntilFirstWeekday = ($firstWeekday - $janFirstWeekday + 7) % 7;
        if ($dayOfYear < $daysUntilFirstWeekday) {
            return '00';
        }

        return sprintf('%02d', intdiv($dayOfYear - $daysUntilFirstWeekday, 7) + 1);
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

    private static function sqliteTruthValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return self::coerceNumeric($value) != 0;
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

        return strcmp($leftText, $rightText);
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
        if (is_infinite($value)) {
            return $value > 0 ? '9.0e+999' : '-9.0e+999';
        }
        if ($value == 0.0) {
            return '0.0';
        }

        if (floor($value) === $value && abs($value) >= 1.0e16) {
            $digits = sprintf('%.0F', abs($value));
            $mantissa = $digits[0] . '.' . substr($digits, 1);
            $exponent = strlen($digits) - 1;

            return ($value < 0 ? '-' : '') . $mantissa . 'e+' . $exponent;
        }

        $formatted = sprintf('%.15G', $value);
        if (!str_contains($formatted, '.') && !str_contains($formatted, 'E')) {
            $formatted .= '.0';
        }

        $formatted = str_contains($formatted, 'E') ? str_replace('E', 'e', $formatted) : $formatted;

        return preg_replace('/e([+-])(\d)$/', 'e${1}0${2}', $formatted) ?? $formatted;
    }

    private static function powSql(float $base, float $exponent): ?float
    {
        if ($base < 0.0 && floor($exponent) !== $exponent) {
            return null;
        }

        return $base ** $exponent;
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
