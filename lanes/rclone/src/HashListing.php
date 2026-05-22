<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class HashListing
{
    /**
     * @return list<string>
     */
    public static function lines(MemoryProvider $provider, string $hashType, bool $base64 = false, ?FilterRuleSet $filter = null): array
    {
        $type = HashType::fromString($hashType);
        $lines = [];
        foreach ($provider->list() as $object) {
            if ($filter !== null && !$filter->includes($object->path)) {
                continue;
            }

            $hash = $provider->hashes($object->path, new HashSet($type))[$type] ?? '';
            if ($hash === '') {
                continue;
            }
            if ($base64) {
                $hash = self::base64Hash($hash);
            }
            $lines[] = self::formatLine($hash, $object->path, self::width($type, $base64));
        }

        return $lines;
    }

    public static function streamLine(string $bytes, string $hashType, bool $base64 = false): string
    {
        $type = HashType::fromString($hashType);
        $hash = MultiHasher::hashBytes($bytes, new HashSet($type))[$type];
        if ($base64) {
            $hash = self::base64Hash($hash);
        }

        return self::formatLine($hash, '-', self::width($type, $base64));
    }

    private static function formatLine(string $hash, string $path, int $width): string
    {
        return sprintf('%' . $width . 's  %s', $hash, $path);
    }

    private static function width(string $type, bool $base64): int
    {
        if (!$base64) {
            return HashType::width($type);
        }

        return strlen(self::base64Hash(str_repeat('0', HashType::width($type))));
    }

    private static function base64Hash(string $hex): string
    {
        $raw = hex2bin($hex);
        if ($raw === false) {
            throw new \InvalidArgumentException('Expected hexadecimal hash bytes');
        }

        return base64_encode($raw);
    }
}
