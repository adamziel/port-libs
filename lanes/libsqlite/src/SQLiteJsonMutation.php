<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonMutation
{
    public static function mutateSqlFunction(
        string $function,
        string|int|float|bool|SQLiteBlobValue|null $value,
        ?string $path,
        mixed $replacement,
        mixed ...$pathValuePairs,
    ): string|SQLiteBlobValue|null {
        if ($value === null) {
            return null;
        }
        if (count($pathValuePairs) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite JSON mutation requires path/value pairs');
        }

        $operation = self::operationForFunction($function);

        $normalizedPairs = [];
        for ($offset = 0; $offset < count($pathValuePairs); $offset += 2) {
            $nextPath = $pathValuePairs[$offset];
            if ($nextPath === null) {
                continue;
            }
            if (!is_string($nextPath)) {
                throw new \InvalidArgumentException('SQLite JSON mutation path must be a string');
            }

            $normalizedPairs[] = $nextPath;
            $normalizedPairs[] = self::jsonMutationValue($pathValuePairs[$offset + 1]);
        }

        $jsonb = self::jsonbBytes($value);
        if ($path === null) {
            $mutated = $normalizedPairs === []
                ? $jsonb
                : match ($operation) {
                    'insert' => SQLiteJsonB::insert($jsonb, $normalizedPairs[0], $normalizedPairs[1], ...array_slice($normalizedPairs, 2)),
                    'set' => SQLiteJsonB::set($jsonb, $normalizedPairs[0], $normalizedPairs[1], ...array_slice($normalizedPairs, 2)),
                    'replace' => SQLiteJsonB::replace($jsonb, $normalizedPairs[0], $normalizedPairs[1], ...array_slice($normalizedPairs, 2)),
                };
        } else {
            $mutated = match ($operation) {
                'insert' => SQLiteJsonB::insert($jsonb, $path, self::jsonMutationValue($replacement), ...$normalizedPairs),
                'set' => SQLiteJsonB::set($jsonb, $path, self::jsonMutationValue($replacement), ...$normalizedPairs),
                'replace' => SQLiteJsonB::replace($jsonb, $path, self::jsonMutationValue($replacement), ...$normalizedPairs),
            };
        }

        if (str_starts_with(strtolower($function), 'jsonb_')) {
            return new SQLiteBlobValue($mutated);
        }

        return SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($mutated));
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function mutateSqlFunctionArguments(string $function, array $arguments): string|SQLiteBlobValue|null
    {
        if ($arguments === [] || count($arguments) % 2 !== 1) {
            throw new \InvalidArgumentException('SQLite JSON mutation functions expect JSON plus path/value pairs');
        }

        $value = array_shift($arguments);
        if ($value !== null && !$value instanceof SQLiteBlobValue && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
            throw new \InvalidArgumentException('SQLite JSON mutation input must be text, numeric, BLOB, or NULL');
        }

        if ($arguments === []) {
            self::operationForFunction($function);
            if ($value === null) {
                return null;
            }

            $jsonb = self::jsonbBytes($value);
            if (str_starts_with(strtolower($function), 'jsonb_')) {
                return new SQLiteBlobValue($jsonb);
            }

            return SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($jsonb));
        }

        $path = array_shift($arguments);
        if ($path !== null && !is_string($path)) {
            throw new \InvalidArgumentException('SQLite JSON mutation path must be a string');
        }

        return self::mutateSqlFunction($function, $value, $path, array_shift($arguments), ...$arguments);
    }

    private static function operationForFunction(string $function): string
    {
        return match (strtolower($function)) {
            'json_insert', 'jsonb_insert' => 'insert',
            'json_set', 'jsonb_set' => 'set',
            'json_replace', 'jsonb_replace' => 'replace',
            default => throw new \InvalidArgumentException('SQLite JSON mutation function must be json_insert, jsonb_insert, json_set, jsonb_set, json_replace, or jsonb_replace'),
        };
    }

    private static function jsonbBytes(string|int|float|bool|SQLiteBlobValue $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                SQLiteJsonB::decode($value->bytes);

                return $value->bytes;
            }

            return SQLiteJsonB::encode(self::decodeJsonText($value->bytes));
        }

        return SQLiteJsonB::encode(self::decodeJsonInput($value));
    }

    private static function jsonMutationValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return self::decodeJsonText($value->json);
        }
        if ($value instanceof SQLiteBlobValue) {
            if (!SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                throw new \InvalidArgumentException('JSON cannot hold BLOB values');
            }

            return SQLiteJsonB::decode($value->bytes);
        }
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite JSON mutation value must be a SQL scalar, JSON subtype value, or JSONB BLOB value');
    }

    private static function decodeJsonInput(string|int|float|bool $json): mixed
    {
        if (is_int($json)) {
            return $json;
        }
        if (is_float($json)) {
            if (!is_finite($json)) {
                throw new \InvalidArgumentException('SQLite JSON numeric input must be finite');
            }

            return $json;
        }
        if (is_bool($json)) {
            return $json ? 1 : 0;
        }

        return self::decodeJsonText($json);
    }

    private static function decodeJsonText(string $json): mixed
    {
        try {
            return json_decode($json, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return SQLiteJson5Parser::decode($json);
        }
    }
}
