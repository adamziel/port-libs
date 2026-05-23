<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MultiHasher
{
    /**
     * @return array<string, string>
     */
    public static function hashBytes(string $bytes, ?HashSet $set = null): array
    {
        $set ??= HashSet::supported();
        $hashes = [];

        foreach ($set->toArray() as $type) {
            $hashes[$type] = $type === HashType::QUICKXOR
                ? self::quickXorHashBytes($bytes)
                : hash(HashType::phpAlgorithm($type), $bytes);
        }

        return $hashes;
    }

    private static function quickXorHashBytes(string $bytes): string
    {
        $size = 20;
        $widthInBits = 8 * $size;
        $dataSize = 11 * $widthInBits;
        $length = strlen($bytes);
        $data = array_fill(0, $dataSize, 0);

        for ($i = 0; $i < $length; $i++) {
            $data[$i % $dataSize] ^= ord($bytes[$i]);
        }

        $hash = array_fill(0, $size + 1, 0);
        for ($i = 0; $i < $dataSize; $i++) {
            if ($data[$i] === 0) {
                continue;
            }
            $shift = ($i * 11) % $widthInBits;
            $shiftBytes = intdiv($shift, 8);
            $shiftBits = $shift % 8;
            $shifted = $data[$i] << $shiftBits;
            $hash[$shiftBytes] ^= $shifted & 0xff;
            $hash[$shiftBytes + 1] ^= ($shifted >> 8) & 0xff;
        }
        $hash[0] ^= $hash[$size];

        for ($i = 0; $i < 8; $i++) {
            $hash[$size - 8 + $i] ^= ($length >> (8 * $i)) & 0xff;
        }

        $raw = '';
        for ($i = 0; $i < $size; $i++) {
            $raw .= chr($hash[$i] & 0xff);
        }

        return bin2hex($raw);
    }
}
