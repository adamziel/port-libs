<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class LsfListing
{
    /**
     * @param array{format?: string, separator?: string, recurse?: bool, dirSlash?: bool, filesOnly?: bool, dirsOnly?: bool, hashType?: string} $options
     * @return list<string>
     */
    public static function lines(MemoryProvider $provider, array $options = []): array
    {
        $format = $options['format'] ?? 'p';
        $separator = $options['separator'] ?? ';';
        $recurse = $options['recurse'] ?? false;
        $dirSlash = $options['dirSlash'] ?? true;
        $filesOnly = $options['filesOnly'] ?? false;
        $dirsOnly = $options['dirsOnly'] ?? false;
        $hashType = HashType::fromString($options['hashType'] ?? HashType::MD5);
        $items = self::items($provider, $recurse, $dirSlash);
        $lines = [];

        foreach ($items as $item) {
            if ($filesOnly && $item['dir']) {
                continue;
            }
            if ($dirsOnly && !$item['dir']) {
                continue;
            }

            $fields = [];
            foreach (str_split($format) as $part) {
                $fields[] = match ($part) {
                    'p' => $item['path'],
                    's' => (string) $item['size'],
                    'h' => $item['dir'] ? '' : ($provider->hashes($item['path'], new HashSet($hashType))[$hashType] ?? ''),
                    default => throw new \InvalidArgumentException('Unsupported lsf format field "' . $part . '"'),
                };
            }
            $lines[] = implode($separator, $fields);
        }

        return $lines;
    }

    /**
     * @return list<array{path:string, size:int, dir:bool}>
     */
    private static function items(MemoryProvider $provider, bool $recurse, bool $dirSlash): array
    {
        $paths = array_map(static fn (ObjectInfo $info): string => $info->path, $provider->list());
        $files = [];
        $dirs = [];

        foreach ($paths as $path) {
            $segments = explode('/', $path);
            if (count($segments) > 1) {
                $prefix = '';
                for ($i = 0; $i < count($segments) - 1; $i++) {
                    $prefix = $prefix === '' ? $segments[$i] : $prefix . '/' . $segments[$i];
                    $dirs[$prefix] = true;
                }
            }

            if ($recurse || !str_contains($path, '/')) {
                $files[$path] = $provider->info($path)->size;
            }
        }

        $visibleDirs = [];
        foreach (array_keys($dirs) as $dir) {
            if ($recurse || !str_contains($dir, '/')) {
                $visibleDirs[] = $dir;
            }
        }

        $items = [];
        foreach ($files as $path => $size) {
            $items[] = ['path' => $path, 'size' => $size, 'dir' => false];
        }
        foreach ($visibleDirs as $dir) {
            $items[] = ['path' => $dir . ($dirSlash ? '/' : ''), 'size' => -1, 'dir' => true];
        }
        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }
}
