<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Native slice of rclone's fs/list direct directory filtering.
 */
final class ListDirectory
{
    /**
     * Filter direct directory entries and return them in stable Remote order.
     *
     * This models fs/list.filterAndSortDir: includeAll bypasses object and
     * directory filters, entries outside the listed directory are ignored, and
     * duplicate remotes keep provider order after sorting.
     *
     * @param iterable<mixed> $entries
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return list<ObjectInfo>
     */
    public static function filterAndSortDir(
        iterable $entries,
        bool $includeAll,
        string $dir,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
    ): array {
        $filtered = self::filterDir($entries, $includeAll, $dir, $includeObject, $includeDirectory);
        $decorated = [];

        foreach ($filtered as $index => $entry) {
            $decorated[] = ['index' => $index, 'entry' => $entry];
        }

        usort(
            $decorated,
            static fn (array $a, array $b): int => $a['entry']->path <=> $b['entry']->path
                ?: $a['index'] <=> $b['index'],
        );

        return array_map(static fn (array $item): ObjectInfo => $item['entry'], $decorated);
    }

    public static function directory(string $path): ObjectInfo
    {
        return new ObjectInfo($path, -1, '');
    }

    /**
     * @param iterable<mixed> $entries
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return list<ObjectInfo>
     */
    private static function filterDir(
        iterable $entries,
        bool $includeAll,
        string $dir,
        ?callable $includeObject,
        ?callable $includeDirectory,
    ): array {
        $prefix = '';
        if ($dir !== '') {
            $prefix = $dir;
            if (!self::isAllSlashes($dir)) {
                $prefix .= '/';
            }
        }

        $newEntries = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \RuntimeException("unknown object type {$type}");
            }

            $ok = true;
            if (self::isDirectory($entry)) {
                if (!$includeAll) {
                    if ($includeDirectory === null) {
                        throw new \InvalidArgumentException('directory include callback is required when includeAll is false');
                    }
                    $ok = (bool) $includeDirectory($entry->path);
                }
            } elseif (!$includeAll) {
                if ($includeObject === null) {
                    throw new \InvalidArgumentException('object include callback is required when includeAll is false');
                }
                $ok = (bool) $includeObject($entry);
            }

            if (!$ok || !self::belongsInDirectory($entry->path, $dir, $prefix)) {
                continue;
            }

            $newEntries[] = $entry;
        }

        return $newEntries;
    }

    private static function isDirectory(ObjectInfo $entry): bool
    {
        return $entry->size < 0
            && $entry->sha256 === ''
            && $entry->tier === null
            && $entry->hashes === [];
    }

    private static function belongsInDirectory(string $remote, string $dir, string $prefix): bool
    {
        if (!str_starts_with($remote, $prefix)) {
            return false;
        }
        if ($remote === $dir) {
            return false;
        }

        $leaf = substr($remote, strlen($prefix));

        return !str_contains($leaf, '/') || self::isAllSlashes($leaf);
    }

    private static function isAllSlashes(string $value): bool
    {
        return $value !== '' && strspn($value, '/') === strlen($value);
    }
}
