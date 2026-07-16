<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonErrorPosition
{
    public static function jsonErrorPositionSqlFunction(string $function, string|SQLiteBlobValue|null $value): ?int
    {
        if (strtolower($function) !== 'json_error_position') {
            throw new \InvalidArgumentException('SQLite JSON error-position function must be json_error_position');
        }

        return self::jsonErrorPosition($value);
    }

    /**
     * @param list<string|SQLiteBlobValue|null> $arguments
     */
    public static function jsonErrorPositionSqlFunctionArguments(string $function, array $arguments): ?int
    {
        if (count($arguments) !== 1) {
            throw new \InvalidArgumentException('SQLite json_error_position() expects one argument');
        }

        return self::jsonErrorPositionSqlFunction($function, $arguments[0]);
    }

    public static function jsonErrorPosition(string|SQLiteBlobValue|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                return SQLiteJsonB::errorPosition($value->bytes);
            }

            return SQLiteJson5Parser::errorPosition($value->bytes);
        }

        return SQLiteJson5Parser::errorPosition($value);
    }
}
