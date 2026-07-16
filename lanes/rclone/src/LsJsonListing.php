<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class LsJsonListing
{
    /**
     * @param array{recurse?: bool, noModTime?: bool, noMimeType?: bool, showHash?: bool, hashTypes?: list<string>, filesOnly?: bool, dirsOnly?: bool, metadata?: bool} $options
     * @return list<array<string, mixed>>
     */
    public static function items(MemoryProvider $provider, string $remote = '', array $options = []): array
    {
        $remote = self::normalize($remote);
        if ($remote !== '') {
            $remote = self::directoryPath($provider, $remote) ?? $remote;
        }

        $items = [];
        foreach (self::entries($provider, $remote, (bool) ($options['recurse'] ?? false)) as $entry) {
            if (($options['filesOnly'] ?? false) && $entry['dir']) {
                continue;
            }
            if (($options['dirsOnly'] ?? false) && !$entry['dir']) {
                continue;
            }

            $items[] = self::item($provider, $entry, $options);
        }

        usort($items, static fn (array $a, array $b): int => $a['Path'] <=> $b['Path']);

        return $items;
    }

    /**
     * @param array{noModTime?: bool, noMimeType?: bool, showHash?: bool, hashTypes?: list<string>, filesOnly?: bool, dirsOnly?: bool, metadata?: bool} $options
     * @return array<string, mixed>|null
     */
    public static function stat(MemoryProvider $provider, string $remote = '', array $options = []): ?array
    {
        $remote = self::normalize($remote);
        if ($remote === '') {
            if ($options['filesOnly'] ?? false) {
                return null;
            }

            return self::item($provider, ['path' => '', 'size' => -1, 'dir' => true], $options);
        }

        if (!($options['dirsOnly'] ?? false)) {
            try {
                $info = $provider->info($remote);

                return self::item($provider, ['path' => $info->path, 'size' => $info->size, 'dir' => false], $options);
            } catch (\RuntimeException) {
                if ($options['filesOnly'] ?? false) {
                    return null;
                }
            }
        }

        $dirPath = self::directoryPath($provider, $remote);
        if (($options['filesOnly'] ?? false) || $dirPath === null) {
            return null;
        }

        return self::item($provider, ['path' => $dirPath, 'size' => -1, 'dir' => true], $options);
    }

    /**
     * @param array{recurse?: bool, noModTime?: bool, noMimeType?: bool, showHash?: bool, hashTypes?: list<string>, filesOnly?: bool, dirsOnly?: bool, metadata?: bool} $options
     */
    public static function json(MemoryProvider $provider, string $remote = '', array $options = []): string
    {
        $json = json_encode(self::items($provider, $remote, $options), JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode lsjson output');
        }

        return $json;
    }

    /**
     * @param array{path: string, size: int, dir: bool} $entry
     * @param array{noModTime?: bool, noMimeType?: bool, showHash?: bool, hashTypes?: list<string>, metadata?: bool} $options
     * @return array<string, mixed>
     */
    private static function item(MemoryProvider $provider, array $entry, array $options): array
    {
        $item = [
            'Path' => $entry['path'],
            'Name' => self::baseName($entry['path']),
            'Size' => $entry['size'],
        ];

        if (!($options['noMimeType'] ?? false)) {
            $item['MimeType'] = $entry['dir'] ? 'inode/directory' : self::mimeType($provider->info($entry['path']));
        }
        $item['ModTime'] = '';
        $item['IsDir'] = $entry['dir'];

        if ($entry['dir']) {
            $info = $provider->directoryInfo($entry['path']);
            if (!($options['noModTime'] ?? false) && $info->modTime !== null) {
                $item['ModTime'] = $info->modTime;
            }
            if (($options['metadata'] ?? false) && $info->metadata !== []) {
                $item['Metadata'] = $info->metadata;
            }
            if ($info->id !== null && $info->id !== '') {
                $item['ID'] = $info->id;
            }

            return $item;
        }

        $info = $provider->info($entry['path']);
        if (!($options['noModTime'] ?? false) && $info->modTime !== null) {
            $item['ModTime'] = $info->modTime;
        }

        $hashTypes = $options['hashTypes'] ?? [];
        $showHash = ($options['showHash'] ?? false) || $hashTypes !== [];
        if ($showHash) {
            $types = $hashTypes === []
                ? HashType::supported()
                : array_map(static fn (string $type): string => HashType::fromString($type), $hashTypes);
            $hashes = $provider->hashes($entry['path'], new HashSet(...$types));
            if ($hashes !== []) {
                $item['Hashes'] = $hashes;
            }
        }

        if (($options['metadata'] ?? false) && $info->metadata !== []) {
            $item['Metadata'] = $info->metadata;
        }
        if ($info->id !== null && $info->id !== '') {
            $item['ID'] = $info->id;
        }
        if ($provider->supportsGetTier() && $info->tier !== null && $info->tier !== '') {
            $item['Tier'] = $info->tier;
        }

        return $item;
    }

    /**
     * @return list<array{path: string, size: int, dir: bool}>
     */
    private static function entries(MemoryProvider $provider, string $remote, bool $recurse): array
    {
        $files = [];
        $dirs = [];

        foreach ($provider->directories() as $dirInfo) {
            if (!self::insideOrEqualRemote($provider, $dirInfo->path, $remote)) {
                continue;
            }

            $relative = self::relativePath($dirInfo->path, $remote);
            if ($relative === '') {
                continue;
            }
            if (!$recurse && str_contains($relative, '/')) {
                continue;
            }

            $dirs[$dirInfo->path] = true;
        }

        foreach ($provider->list() as $info) {
            if (!self::insideRemote($provider, $info->path, $remote)) {
                continue;
            }

            $relative = $remote === '' ? $info->path : substr($info->path, strlen($remote) + 1);
            if ($relative === false || $relative === '') {
                continue;
            }

            $segments = explode('/', $relative);
            if (!$recurse && count($segments) > 1) {
                $dirs[$remote === '' ? $segments[0] : $remote . '/' . $segments[0]] = true;
                continue;
            }

            if ($recurse && count($segments) > 1) {
                $prefix = $remote;
                for ($i = 0; $i < count($segments) - 1; $i++) {
                    $prefix = $prefix === '' ? $segments[$i] : $prefix . '/' . $segments[$i];
                    $dirs[$prefix] = true;
                }
            }

            $files[$info->path] = $info->size;
        }

        $entries = [];
        foreach ($files as $path => $size) {
            $entries[] = ['path' => $path, 'size' => $size, 'dir' => false];
        }
        foreach (array_keys($dirs) as $dir) {
            $entries[] = ['path' => $dir, 'size' => -1, 'dir' => true];
        }

        return $entries;
    }

    private static function directoryPath(MemoryProvider $provider, string $remote): ?string
    {
        $remote = self::normalize($remote);
        if ($remote === '') {
            return '';
        }

        try {
            return $provider->directoryInfo($remote)->path;
        } catch (\RuntimeException) {
            return null;
        }
    }

    private static function insideRemote(MemoryProvider $provider, string $path, string $remote): bool
    {
        if ($remote === '') {
            return true;
        }

        if ($provider->isCaseInsensitive()) {
            return str_starts_with(strtolower($path), strtolower($remote . '/'));
        }

        return str_starts_with($path, $remote . '/');
    }

    private static function insideOrEqualRemote(MemoryProvider $provider, string $path, string $remote): bool
    {
        if ($remote === '') {
            return true;
        }

        if ($provider->isCaseInsensitive()) {
            $path = strtolower($path);
            $remote = strtolower($remote);
        }

        return $path === $remote || str_starts_with($path, $remote . '/');
    }

    private static function relativePath(string $path, string $remote): string
    {
        if ($remote === '') {
            return $path;
        }

        return $path === $remote ? '' : substr($path, strlen($remote) + 1);
    }

    private static function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    private static function baseName(string $path): string
    {
        if ($path === '') {
            return '';
        }
        $parts = explode('/', $path);

        return (string) end($parts);
    }

    private static function mimeType(ObjectInfo $info): string
    {
        if ($info->mimeType !== null && $info->mimeType !== '') {
            return $info->mimeType;
        }

        $extension = strtolower(pathinfo($info->path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'gif' => 'image/gif',
            'htm', 'html' => 'text/html; charset=utf-8',
            'jpeg', 'jpg' => 'image/jpeg',
            'js', 'mjs' => 'text/javascript; charset=utf-8',
            'json' => 'application/json',
            'png' => 'image/png',
            'sql' => 'application/sql',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain; charset=utf-8',
            'webp' => 'image/webp',
            'xml' => 'text/xml; charset=utf-8',
            default => 'application/octet-stream',
        };
    }
}
