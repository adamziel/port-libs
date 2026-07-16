<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class RootedMemoryProvider
{
    public function __construct(
        private readonly MemoryProvider $provider,
        private readonly string $root,
    ) {
    }

    public function root(): string
    {
        return $this->normalize($this->root);
    }

    /**
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->relativeInfo($this->provider->put($this->absolute($path), $bytes, $options));
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function putStream(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->relativeInfo($this->provider->putStream($this->absolute($path), $bytes, $options));
    }

    /**
     * @param array{sourcePath?: string, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function updateObject(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->relativeInfo($this->provider->updateObject($this->absolute($path), $bytes, $options));
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null} $options
     */
    public function mkdir(string $path, array $options = []): ObjectInfo
    {
        return $this->relativeInfo($this->provider->mkdir($this->absolute($path), $options));
    }

    public function mkdirModTime(string $path, \DateTimeInterface|string|null $modTime): ObjectInfo
    {
        return $this->relativeInfo($this->provider->mkdirModTime($this->absolute($path), $modTime));
    }

    public function get(string $path): string
    {
        return $this->provider->get($this->absolute($path));
    }

    public function info(string $path): ObjectInfo
    {
        return $this->relativeInfo($this->provider->info($this->absolute($path)));
    }

    public function directoryInfo(string $path = ''): ObjectInfo
    {
        return $this->relativeInfo($this->provider->directoryInfo($this->absolute($path)));
    }

    /**
     * @return list<ObjectInfo>
     */
    public function list(string $prefix = ''): array
    {
        return array_map(
            fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info),
            $this->provider->walk($this->absolute($prefix), -1, true, false)['objects'],
        );
    }

    /**
     * @return list<ObjectInfo>
     */
    public function directories(string $prefix = ''): array
    {
        return array_map(
            fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info),
            $this->provider->walk($this->absolute($prefix), -1, false, true)['directories'],
        );
    }

    /**
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    public function walk(
        string $dir = '',
        int $maxDepth = -1,
        bool $includeObjects = true,
        bool $includeDirectories = true,
    ): array {
        $walk = $this->provider->walk($this->absolute($dir), $maxDepth, $includeObjects, $includeDirectories);

        return [
            'objects' => array_map(fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info), $walk['objects']),
            'directories' => array_map(fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info), $walk['directories']),
        ];
    }

    /**
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    public function purge(string $dir = ''): array
    {
        $purge = $this->provider->purge($this->absolute($dir));

        return [
            'objects' => array_map(fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info), $purge['objects']),
            'directories' => array_map(fn (ObjectInfo $info): ObjectInfo => $this->relativeInfo($info), $purge['directories']),
        ];
    }

    public function publicLink(string $path = '', int $expireSeconds = 0, bool $unlink = false): string
    {
        return $this->provider->publicLink($this->absolute($path), $expireSeconds, $unlink);
    }

    private function absolute(string $path): string
    {
        $root = $this->root();
        $path = $this->normalize($path);
        if ($root === '') {
            return $path;
        }

        return $path === '' ? $root : $root . '/' . $path;
    }

    private function relativeInfo(ObjectInfo $info): ObjectInfo
    {
        $root = $this->root();
        $path = $this->normalize($info->path);
        if ($root !== '') {
            if ($path === $root) {
                $path = '';
            } elseif (str_starts_with($path, $root . '/')) {
                $path = substr($path, strlen($root) + 1);
            }
        }

        return new ObjectInfo(
            $path,
            $info->size,
            $info->sha256,
            $info->modTime,
            $info->mimeType,
            $info->metadata,
            $info->id,
            $info->tier,
            $info->hashes,
            $info->providerKey,
            $info->parentId,
        );
    }

    private function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
