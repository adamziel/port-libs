<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ChecksumFile
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $hashes = [];
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^([^ ]+) [ *](.+)$/', $line, $m)) {
                continue;
            }

            $sum = $m[1];
            $path = $m[2];
            if ($sum === '' || isset($hashes[$path])) {
                continue;
            }

            $hashes[$path] = strtolower($sum);
        }

        return $hashes;
    }
}
