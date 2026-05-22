<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MemoryProvider
{
    /**
     * @var array<string, array{bytes: string, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string}>
     */
    private array $objects = [];

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        $this->objects[$this->normalize($path)] = [
            'bytes' => $bytes,
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'tier' => $options['tier'] ?? null,
        ];

        return $this->info($path);
    }

    public function get(string $path): string
    {
        return $this->entry($path)['bytes'];
    }

    public function info(string $path): ObjectInfo
    {
        $path = $this->normalize($path);
        $entry = $this->entry($path);
        $bytes = $entry['bytes'];

        return new ObjectInfo(
            $path,
            strlen($bytes),
            hash('sha256', $bytes),
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
            $entry['tier'],
        );
    }

    /**
     * @return list<ObjectInfo>
     */
    public function list(string $prefix = ''): array
    {
        $prefix = $this->normalize($prefix);
        $items = [];
        foreach (array_keys($this->objects) as $path) {
            if ($prefix === '' || str_starts_with($path, $prefix)) {
                $items[] = $this->info($path);
            }
        }
        usort($items, static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path);

        return $items;
    }

    public function copyTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        return $target->put($targetPath, $this->get($sourcePath));
    }

    /**
     * @return array<string, string>
     */
    public function hashes(string $path, ?HashSet $set = null): array
    {
        return MultiHasher::hashBytes($this->get($path), $set);
    }

    private function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    /**
     * @return array{bytes: string, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string}
     */
    private function entry(string $path): array
    {
        $path = $this->normalize($path);
        if (!array_key_exists($path, $this->objects)) {
            throw new \RuntimeException("Object not found: {$path}");
        }

        return $this->objects[$path];
    }

    private function normalizeModTime(\DateTimeInterface|string|null $modTime): ?string
    {
        if ($modTime === null || $modTime === '') {
            return null;
        }
        if ($modTime instanceof \DateTimeInterface) {
            return $modTime->format('Y-m-d\TH:i:s.uP');
        }

        return $modTime;
    }
}
