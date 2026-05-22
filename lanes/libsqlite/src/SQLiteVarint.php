<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVarint
{
    /**
     * @return array{0:int, 1:int}
     */
    public static function decode(string $bytes, int $offset = 0): array
    {
        $value = 0;
        for ($i = 0; $i < 8; $i++) {
            if (!isset($bytes[$offset + $i])) {
                throw new \InvalidArgumentException('Truncated SQLite varint');
            }
            $byte = ord($bytes[$offset + $i]);
            $value = ($value << 7) | ($byte & 0x7f);
            if (($byte & 0x80) === 0) {
                return [$value, $i + 1];
            }
        }

        if (!isset($bytes[$offset + 8])) {
            throw new \InvalidArgumentException('Truncated SQLite 9-byte varint');
        }

        return [($value << 8) | ord($bytes[$offset + 8]), 9];
    }
}

