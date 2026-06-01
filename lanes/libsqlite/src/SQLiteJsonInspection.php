<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonInspection
{
    public static function inspectionSqlFunction(
        string $function,
        string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value,
        ?string $path = '$',
    ): string|int|null {
        if (strcasecmp($function, 'json_type') === 0) {
            return self::jsonType($value, $path);
        }
        if (strcasecmp($function, 'json_array_length') === 0) {
            return self::jsonArrayLength($value, $path);
        }

        throw new \InvalidArgumentException('SQLite JSON inspection function must be json_type or json_array_length');
    }

    /**
     * @param list<string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null> $arguments
     */
    public static function inspectionSqlFunctionArguments(string $function, array $arguments): string|int|null
    {
        $count = count($arguments);
        if ($count < 1 || $count > 2) {
            throw new \InvalidArgumentException('SQLite json_type() and json_array_length() expect one or two arguments');
        }

        $path = array_key_exists(1, $arguments) ? $arguments[1] : '$';
        if ($path !== null && !is_string($path)) {
            throw new \InvalidArgumentException('SQLite JSON inspection path argument must be text or NULL');
        }

        return self::inspectionSqlFunction($function, $arguments[0], $path);
    }

    public static function jsonType(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, ?string $path = '$'): ?string
    {
        if ($value === null || $path === null) {
            return null;
        }

        if ($value instanceof SQLiteJsonSubtypeValue) {
            $value = $value->json;
        }

        if ($value instanceof SQLiteBlobValue && SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
            return SQLiteJsonB::type($value->bytes, $path);
        }

        $located = self::locate(self::decodeText($value instanceof SQLiteBlobValue ? $value->bytes : $value), $path);

        return $located['found'] ? self::typeName($located['value']) : null;
    }

    public static function jsonArrayLength(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, ?string $path = '$'): ?int
    {
        if ($value === null || $path === null) {
            return null;
        }

        if ($value instanceof SQLiteJsonSubtypeValue) {
            $value = $value->json;
        }

        if ($value instanceof SQLiteBlobValue && SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
            return SQLiteJsonB::arrayLength($value->bytes, $path);
        }

        $located = self::locate(self::decodeText($value instanceof SQLiteBlobValue ? $value->bytes : $value), $path);
        if (!$located['found']) {
            return null;
        }

        return is_array($located['value']) && array_is_list($located['value'])
            ? count($located['value'])
            : 0;
    }

    /**
     * @return array{found:bool,value:mixed}
     */
    public static function locatePath(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if ($value === null) {
            return ['found' => false, 'value' => null];
        }

        if ($value instanceof SQLiteJsonSubtypeValue) {
            $value = $value->json;
        }

        if ($value instanceof SQLiteBlobValue && SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
            return self::locate(SQLiteJsonB::decode($value->bytes), $path);
        }

        return self::locate(self::decodeText($value instanceof SQLiteBlobValue ? $value->bytes : $value), $path);
    }

    /**
     * @return array{found:bool,value:mixed}
     */
    public static function locatePathForJsonEncoding(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if ($value === null) {
            return ['found' => false, 'value' => null];
        }

        if ($value instanceof SQLiteJsonSubtypeValue) {
            $value = $value->json;
        }

        if ($value instanceof SQLiteBlobValue && SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
            return self::locate(SQLiteJsonB::decodeForJsonEncoding($value->bytes), $path);
        }

        return self::locate(self::decodeText($value instanceof SQLiteBlobValue ? $value->bytes : $value), $path);
    }

    private static function decodeText(string $json): mixed
    {
        try {
            return json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return SQLiteJson5Parser::decode($json);
        }
    }

    /**
     * @return array{found:bool,value:mixed}
     */
    private static function locate(mixed $value, string $path): array
    {
        foreach (self::parsePath($path) as $segment) {
            if (($segment['type'] ?? null) === 'member') {
                $name = $segment['name'] ?? null;
                if (!is_string($name)) {
                    return ['found' => false, 'value' => null];
                }
                if ($value instanceof \stdClass) {
                    if (!property_exists($value, $name)) {
                        return ['found' => false, 'value' => null];
                    }
                    $value = $value->{$name};
                    continue;
                }
                if (!is_array($value) || array_is_list($value) || !array_key_exists($name, $value)) {
                    return ['found' => false, 'value' => null];
                }
                $value = $value[$name];
                continue;
            }

            if (!is_array($value) || !array_is_list($value)) {
                return ['found' => false, 'value' => null];
            }

            $index = self::arrayIndex($segment, count($value));
            if ($index === null || !array_key_exists($index, $value)) {
                return ['found' => false, 'value' => null];
            }

            $value = $value[$index];
        }

        return ['found' => true, 'value' => $value];
    }

    private static function typeName(mixed $value): string
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
            return 'integer';
        }
        if (is_float($value)) {
            return 'real';
        }
        if (is_string($value)) {
            return 'text';
        }
        if ($value instanceof \stdClass) {
            return 'object';
        }
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        throw new \InvalidArgumentException('SQLite JSON value cannot be classified');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parsePath(string $path): array
    {
        if ($path === '' || $path[0] !== '$') {
            throw new \InvalidArgumentException('SQLite JSON path must start with $');
        }

        $segments = [];
        $offset = 1;
        $length = strlen($path);
        while ($offset < $length) {
            $char = $path[$offset];
            if ($char === '.') {
                $offset++;
                if ($offset >= $length) {
                    throw new \InvalidArgumentException('SQLite JSON path object label is missing');
                }

                if ($path[$offset] === '"') {
                    $end = self::quotedPathMemberEnd($path, $offset);
                    $label = SQLiteJson5Parser::decode(substr($path, $offset, $end - $offset + 1));
                    if (!is_string($label)) {
                        throw new \InvalidArgumentException('SQLite JSON path quoted label did not decode to text');
                    }
                    $offset = $end + 1;
                } else {
                    $end = $offset;
                    while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                        $end++;
                    }
                    if ($end === $offset) {
                        throw new \InvalidArgumentException('SQLite JSON path object label is missing');
                    }
                    $label = SQLiteJsonPath::decodeBareMember(substr($path, $offset, $end - $offset));
                    if ($label === null) {
                        throw new \InvalidArgumentException('SQLite JSON path object label is malformed');
                    }
                    $offset = $end;
                }

                $segments[] = [
                    'type' => 'member',
                    'name' => $label,
                ];
                continue;
            }

            if ($char === '[') {
                $end = strpos($path, ']', $offset + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite JSON path array index is unterminated');
                }

                $segments[] = self::arrayPathSegment(substr($path, $offset + 1, $end - $offset - 1));
                $offset = $end + 1;
                continue;
            }

            throw new \InvalidArgumentException('SQLite JSON path segment is malformed');
        }

        return $segments;
    }

    /**
     * @return array{type:string,index:?int,fromEnd:bool,append:bool}
     */
    private static function arrayPathSegment(string $token): array
    {
        if ($token === '#') {
            return [
                'type' => 'index',
                'index' => null,
                'fromEnd' => false,
                'append' => true,
            ];
        }
        if (str_starts_with($token, '#-')) {
            $digits = substr($token, 2);
            if ($digits === '' || preg_match('/^[0-9]+$/', $digits) !== 1) {
                throw new \InvalidArgumentException('SQLite JSON path reverse array index is malformed');
            }

            return [
                'type' => 'index',
                'index' => self::parsePathInteger($digits),
                'fromEnd' => true,
                'append' => false,
            ];
        }
        if (preg_match('/^[0-9]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite JSON path array index is malformed');
        }

        return [
            'type' => 'index',
            'index' => self::parsePathInteger($token),
            'fromEnd' => false,
            'append' => false,
        ];
    }

    private static function quotedPathMemberEnd(string $path, int $offset): int
    {
        $length = strlen($path);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($path[$cursor] === '\\') {
                $cursor++;
                continue;
            }
            if ($path[$cursor] === '"') {
                return $cursor;
            }
        }

        throw new \InvalidArgumentException('SQLite JSON path quoted object label is unterminated');
    }

    private static function parsePathInteger(string $digits): ?int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @param array<string, mixed> $segment
     */
    private static function arrayIndex(array $segment, int $count): ?int
    {
        if (($segment['append'] ?? false) === true) {
            return null;
        }

        $index = $segment['index'] ?? null;
        if (!is_int($index)) {
            return null;
        }

        return ($segment['fromEnd'] ?? false) === true ? $count - $index : $index;
    }
}
