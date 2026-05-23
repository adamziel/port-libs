<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVarint
{
    public static function encode(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('SQLite varint cannot encode a negative integer');
        }

        if ($value <= 0x7f) {
            return chr($value);
        }

        if ($value < 0x0100000000000000) {
            $groups = [$value & 0x7f];
            $value >>= 7;
            while ($value > 0) {
                array_unshift($groups, 0x80 | ($value & 0x7f));
                $value >>= 7;
            }

            return implode('', array_map(chr(...), $groups));
        }

        $bytes = array_fill(0, 9, 0);
        $bytes[8] = $value & 0xff;
        $value >>= 8;
        for ($i = 7; $i >= 0; $i--) {
            $bytes[$i] = 0x80 | ($value & 0x7f);
            $value >>= 7;
        }

        return implode('', array_map(chr(...), $bytes));
    }

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
