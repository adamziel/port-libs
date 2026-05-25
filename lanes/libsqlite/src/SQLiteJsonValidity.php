<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonValidity
{
    public const FLAG_STRICT_TEXT = 0x01;
    public const FLAG_JSON5_TEXT = 0x02;
    public const FLAG_SUPERFICIAL_JSONB = 0x04;
    public const FLAG_STRICT_JSONB = 0x08;

    public static function jsonValidSqlFunction(
        string $function,
        string|SQLiteBlobValue|null $value,
        ?int $flags = self::FLAG_STRICT_TEXT,
    ): ?bool {
        if (strcasecmp($function, 'json_valid') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON validity function must be json_valid');
        }
        if ($flags === null) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }

        return self::jsonValid($value, $flags);
    }

    /**
     * @param list<string|SQLiteBlobValue|int|null> $arguments
     */
    public static function jsonValidSqlFunctionArguments(string $function, array $arguments): ?bool
    {
        $count = count($arguments);
        if ($count < 1 || $count > 2) {
            throw new \InvalidArgumentException('SQLite json_valid() expects one or two arguments');
        }

        $flags = array_key_exists(1, $arguments) ? $arguments[1] : self::FLAG_STRICT_TEXT;
        if ($flags !== null && !is_int($flags)) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be an integer between 1 and 15');
        }

        return self::jsonValidSqlFunction($function, $arguments[0], $flags);
    }

    public static function jsonValid(string|SQLiteBlobValue|null $value, int $flags = self::FLAG_STRICT_TEXT): ?bool
    {
        if ($flags < 1 || $flags > 15) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }
        if ($value === null) {
            return null;
        }

        if ($value instanceof SQLiteBlobValue) {
            return self::blobValid($value->bytes, $flags);
        }

        return self::textValid($value, $flags);
    }

    public static function textValid(string $json, int $flags = self::FLAG_STRICT_TEXT): bool
    {
        if ($flags < 1 || $flags > 15) {
            throw new \InvalidArgumentException('FLAGS parameter to json_valid() must be between 1 and 15');
        }
        if (($flags & (self::FLAG_STRICT_TEXT | self::FLAG_JSON5_TEXT)) === 0) {
            return false;
        }
        if (($flags & self::FLAG_JSON5_TEXT) !== 0) {
            return self::json5TextValid($json);
        }

        return self::strictTextValid($json);
    }

    public static function strictTextValid(string $json): bool
    {
        try {
            json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    public static function json5TextValid(string $json): bool
    {
        try {
            SQLiteJson5Parser::decode($json);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function blobValid(string $bytes, int $flags): bool
    {
        if (SQLiteJsonB::isSuperficiallyJsonB($bytes)) {
            if (($flags & self::FLAG_SUPERFICIAL_JSONB) !== 0) {
                return true;
            }
            if (($flags & self::FLAG_STRICT_JSONB) !== 0) {
                return SQLiteJsonB::isStrictlyWellFormed($bytes);
            }

            return false;
        }

        return self::textValid($bytes, $flags);
    }
}
