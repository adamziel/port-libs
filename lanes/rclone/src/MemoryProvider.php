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
     * @var array<string, string>
     */
    private array $caseIndex = [];

    private readonly HashSet $supportedHashes;

    public function __construct(private readonly bool $caseInsensitive = false, ?HashSet $supportedHashes = null)
    {
        $this->supportedHashes = $supportedHashes === null
            ? HashSet::supported()
            : new HashSet(...$supportedHashes->toArray());
    }

    public function supportedHashes(): HashSet
    {
        return new HashSet(...$this->supportedHashes->toArray());
    }

    public function supportsHash(string $type): bool
    {
        return $this->supportedHashes->contains($type);
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        $path = $this->normalize($path);
        if ($this->caseInsensitive) {
            $lookup = $this->lookupPath($path);
            if (isset($this->caseIndex[$lookup]) && $this->caseIndex[$lookup] !== $path) {
                unset($this->objects[$this->caseIndex[$lookup]]);
            }
            $this->caseIndex[$lookup] = $path;
        }

        $this->objects[$path] = [
            'bytes' => $bytes,
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'tier' => $options['tier'] ?? null,
        ];

        return $this->info($path);
    }

    public function isCaseInsensitive(): bool
    {
        return $this->caseInsensitive;
    }

    public function get(string $path): string
    {
        return $this->entry($path)['bytes'];
    }

    public function info(string $path): ObjectInfo
    {
        $path = $this->canonicalPath($path);
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
            if ($prefix === '' || $this->pathStartsWith($path, $prefix)) {
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
        $set = ($set ?? $this->supportedHashes)->overlap($this->supportedHashes);

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
        $path = $this->canonicalPath($path);
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

    private function canonicalPath(string $path): string
    {
        $path = $this->normalize($path);
        if (!$this->caseInsensitive) {
            return $path;
        }

        return $this->caseIndex[$this->lookupPath($path)] ?? $path;
    }

    private function lookupPath(string $path): string
    {
        return strtolower($this->normalize($path));
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        if (!$this->caseInsensitive) {
            return str_starts_with($path, $prefix);
        }

        return str_starts_with(strtolower($path), strtolower($prefix));
    }
}
