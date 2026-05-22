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
            $hashes[$type] = hash(HashType::phpAlgorithm($type), $bytes);
        }

        return $hashes;
    }
}
