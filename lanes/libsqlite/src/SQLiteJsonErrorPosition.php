<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonErrorPosition
{
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
