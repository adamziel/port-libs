<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseReferenceStore
{
    public function __construct(private readonly string $gitDirectory)
    {
    }

    public function write(LooseReference $reference): void
    {
        $path = $this->pathFor($reference->name);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create reference directory: {$directory}");
        }

        if (file_put_contents($path, $reference->storageBytes()) === false) {
            throw new \RuntimeException("Unable to write loose reference: {$reference->name}");
        }
    }

    public function writeDirect(string $name, string $oid, string $algorithm = 'sha1'): LooseReference
    {
        $reference = LooseReference::direct($name, $oid, $algorithm);
        $this->write($reference);

        return $reference;
    }

    public function writeSymbolic(string $name, string $targetName): LooseReference
    {
        $reference = LooseReference::symbolic($name, $targetName);
        $this->write($reference);

        return $reference;
    }

    public function read(string $name, string $algorithm = 'sha1'): LooseReference
    {
        $reference = $this->tryRead($name, $algorithm);
        if ($reference === null) {
            throw new \RuntimeException("Loose reference not found: {$name}");
        }

        return $reference;
    }

    public function tryRead(string $name, string $algorithm = 'sha1'): ?LooseReference
    {
        $path = $this->pathFor($name);
        if (!is_file($path)) {
            return null;
        }

        return LooseReference::parse($name, (string) file_get_contents($path), $algorithm);
    }

    private function pathFor(string $name): string
    {
        ReferenceName::assertValid($name);

        return rtrim($this->gitDirectory, '/\\') . '/' . $name;
    }
}
