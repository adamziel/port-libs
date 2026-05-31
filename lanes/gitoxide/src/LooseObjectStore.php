<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseObjectStore
{
    private const HEADER_MAX_SIZE = 64;

    private readonly string $objectsDirectory;

    public function __construct(string $gitDirectory, bool $pathIsObjectsDirectory = false)
    {
        $this->objectsDirectory = rtrim($pathIsObjectsDirectory ? $gitDirectory : $gitDirectory . '/objects', '/');
    }

    public static function fromObjectsDirectory(string $objectsDirectory): self
    {
        return new self($objectsDirectory, true);
    }

    public function objectsDirectory(): string
    {
        return $this->objectsDirectory;
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

    /**
     * @return array{type:string,size:int,headerLength:int}
     */
    public function readHeader(string $oid): array
    {
        $header = $this->tryReadHeader($oid);
        if ($header === null) {
            throw new \RuntimeException("Loose object not found: {$oid}");
        }

        return $header;
    }

    /**
     * @return null|array{type:string,size:int,headerLength:int}
     */
    public function tryReadHeader(string $oid): ?array
    {
        self::assertObjectId($oid);

        $path = $this->pathFor($oid);
        if (!is_file($path)) {
            return null;
        }

        $compressed = file_get_contents($path);
        if ($compressed === false) {
            throw new \RuntimeException("Unable to read loose object header: {$oid}");
        }

        return GitObject::decodeLooseHeader(self::inflateHeaderBytes($compressed, strtolower($oid)));
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

    /**
     * @return array{numObjects:int,verifiedObjectIds:list<string>}
     */
    public function verifyIntegrity(): array
    {
        $verified = [];
        foreach ($this->objectIds() as $oid) {
            try {
                $object = $this->read($oid);
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                throw new \RuntimeException("Loose object {$oid} could not be read exactly: {$exception->getMessage()}", 0, $exception);
            }

            $actual = $object->oid();
            if ($actual !== $oid) {
                throw new \RuntimeException("Loose object hash mismatch: expected {$oid}, got {$actual}");
            }

            self::decodeForIntegrity($object, $oid);
            $verified[] = $oid;
        }

        return [
            'numObjects' => count($verified),
            'verifiedObjectIds' => $verified,
        ];
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

    private static function inflateHeaderBytes(string $compressed, string $oid): string
    {
        if ($compressed === '') {
            throw new \RuntimeException("Unable to inflate loose object header: {$oid}");
        }

        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new \RuntimeException('Unable to initialize zlib inflate context');
        }

        $inflated = '';
        $offset = 0;
        $length = strlen($compressed);
        while ($offset < $length) {
            $chunk = substr($compressed, $offset, 16);
            $offset += strlen($chunk);
            $decoded = @inflate_add($context, $chunk, $offset >= $length ? ZLIB_FINISH : ZLIB_NO_FLUSH);
            if ($decoded === false) {
                throw new \RuntimeException("Unable to inflate loose object header: {$oid}");
            }

            $inflated .= $decoded;
            $nul = strpos($inflated, "\0");
            if ($nul !== false) {
                if ($nul + 1 > self::HEADER_MAX_SIZE) {
                    throw new \InvalidArgumentException('Loose object header exceeds maximum size of 64 bytes');
                }

                return substr($inflated, 0, $nul + 1);
            }
            if (strlen($inflated) >= self::HEADER_MAX_SIZE) {
                throw new \InvalidArgumentException('Loose object header exceeds maximum size of 64 bytes');
            }
        }

        throw new \InvalidArgumentException('Did not find 0 byte in header');
    }

    private static function decodeForIntegrity(GitObject $object, string $oid): void
    {
        try {
            match ($object->type) {
                'blob' => null,
                'tree' => Tree::parse($object->body),
                'commit' => Commit::parse($object->body),
                'tag' => GitTag::parse($object->body),
            };
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException("{$object->type} object {$oid} could not be decoded: {$exception->getMessage()}", 0, $exception);
        }
    }
}
