<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MemoryProvider
{
    /** @var array<string, string> */
    private array $objects = [];

    public function put(string $path, string $bytes): ObjectInfo
    {
        $this->objects[$this->normalize($path)] = $bytes;
        return $this->info($path);
    }

    public function get(string $path): string
    {
        $path = $this->normalize($path);
        if (!array_key_exists($path, $this->objects)) {
            throw new \RuntimeException("Object not found: {$path}");
        }

        return $this->objects[$path];
    }

    public function info(string $path): ObjectInfo
    {
        $path = $this->normalize($path);
        $bytes = $this->get($path);
        return new ObjectInfo($path, strlen($bytes), hash('sha256', $bytes));
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

    private function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}

