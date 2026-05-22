<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseObjectStore
{
    private readonly string $objectsDirectory;

    public function __construct(string $gitDirectory, bool $pathIsObjectsDirectory = false)
    {
        $this->objectsDirectory = rtrim($pathIsObjectsDirectory ? $gitDirectory : $gitDirectory . '/objects', '/');
    }

    public static function fromObjectsDirectory(string $objectsDirectory): self
    {
        return new self($objectsDirectory, true);
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
        self::assertObjectId($oid);

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

    public function tryRead(string $oid): ?GitObject
    {
        self::assertObjectId($oid);
        if (!$this->contains($oid)) {
            return null;
        }

        return $this->read($oid);
    }

    public function contains(string $oid): bool
    {
        self::assertObjectId($oid);

        return is_file($this->pathFor(strtolower($oid)));
    }

    /**
     * @return list<string>
     */
    public function objectIds(): array
    {
        $objectsDirectory = $this->objectsDirectory;
        if (!is_dir($objectsDirectory)) {
            return [];
        }

        $ids = [];
        $directories = glob($objectsDirectory . '/[0-9a-f][0-9a-f]', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_STRING);
        foreach ($directories as $directory) {
            $prefix = basename($directory);
            $files = glob($directory . '/*') ?: [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $suffix = basename($file);
                if (is_file($file) && preg_match('/^[0-9a-f]{38}$/', $suffix) === 1) {
                    $ids[] = $prefix . $suffix;
                }
            }
        }

        return $ids;
    }

    private function pathFor(string $oid): string
    {
        $oid = strtolower($oid);

        return $this->objectsDirectory . '/' . substr($oid, 0, 2) . '/' . substr($oid, 2);
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Loose object id must be a 40-character SHA-1 hex string');
        }
    }
}
