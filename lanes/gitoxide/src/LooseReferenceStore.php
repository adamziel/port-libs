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

    /**
     * @return list<LooseReference>
     */
    public function all(string $algorithm = 'sha1'): array
    {
        return $this->prefixed('refs/', $algorithm);
    }

    /**
     * @return list<LooseReference>
     */
    public function prefixed(string $prefix, string $algorithm = 'sha1'): array
    {
        ReferenceName::assertValidPartial(rtrim($prefix, '/'));

        $refsDirectory = rtrim($this->gitDirectory, '/\\') . '/refs';
        if (!is_dir($refsDirectory)) {
            return [];
        }

        $references = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($refsDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $gitDirectory = str_replace('\\', '/', rtrim($this->gitDirectory, '/\\')) . '/';
            if (!str_starts_with($path, $gitDirectory)) {
                continue;
            }

            $name = substr($path, strlen($gitDirectory));
            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            $references[] = LooseReference::parse($name, (string) file_get_contents($file->getPathname()), $algorithm);
        }

        usort($references, static fn (LooseReference $a, LooseReference $b): int => strcmp($a->name, $b->name));

        return $references;
    }

    private function pathFor(string $name): string
    {
        ReferenceName::assertValid($name);

        return rtrim($this->gitDirectory, '/\\') . '/' . $name;
    }
}
