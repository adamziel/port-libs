<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseObjectStore
{
    public function __construct(private readonly string $gitDirectory)
    {
    }

    public function write(GitObject $object): string
    {
        $oid = $object->oid();
        $path = $this->pathFor($oid);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create object directory: {$directory}");
        }

        $compressed = gzcompress($object->storageBytes());
        if ($compressed === false) {
            throw new \RuntimeException('Unable to zlib-compress Git object');
        }
        file_put_contents($path, $compressed);

        return $oid;
    }

    public function read(string $oid): GitObject
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Loose object id must be a 40-character SHA-1 hex string');
        }

        $path = $this->pathFor($oid);
        if (!is_file($path)) {
            throw new \RuntimeException("Loose object not found: {$oid}");
        }

        $bytes = gzuncompress((string) file_get_contents($path));
        if ($bytes === false) {
            throw new \RuntimeException("Unable to inflate loose object: {$oid}");
        }

        return GitObject::fromStorageBytes($bytes);
    }

    private function pathFor(string $oid): string
    {
        return rtrim($this->gitDirectory, '/') . '/objects/' . substr($oid, 0, 2) . '/' . substr($oid, 2);
    }
}

