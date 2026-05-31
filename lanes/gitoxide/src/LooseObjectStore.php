<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LooseObjectStore
{
    private const HEADER_MAX_SIZE = 64;
    private const HASH_ALGORITHMS = [
        'sha1' => 40,
        'sha256' => 64,
    ];

    private readonly string $objectsDirectory;
    private readonly string $algorithm;
    private readonly ?int $allocationLimitBytes;

    public function __construct(
        string $gitDirectory,
        bool $pathIsObjectsDirectory = false,
        string $algorithm = 'sha1',
        ?int $allocationLimitBytes = null,
    )
    {
        $this->objectsDirectory = rtrim($pathIsObjectsDirectory ? $gitDirectory : $gitDirectory . '/objects', '/');
        $this->algorithm = self::normalizeAlgorithm($algorithm);
        $this->allocationLimitBytes = self::normalizeAllocationLimit($allocationLimitBytes);
    }

    public static function fromObjectsDirectory(
        string $objectsDirectory,
        string $algorithm = 'sha1',
        ?int $allocationLimitBytes = null,
    ): self
    {
        return new self($objectsDirectory, true, $algorithm, $allocationLimitBytes);
    }

    public function objectsDirectory(): string
    {
        return $this->objectsDirectory;
    }

    public function objectHash(): string
    {
        return $this->algorithm;
    }

    public function allocationLimitBytes(): ?int
    {
        return $this->allocationLimitBytes;
    }

    public function write(GitObject $object): string
    {
        $oid = $object->oid($this->algorithm);
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
        $this->assertObjectId($oid);

        $path = $this->pathFor($oid);
        if (!self::objectPathExists($path)) {
            throw new \RuntimeException("Loose object not found: {$oid}");
        }
        if (!is_file($path)) {
            throw new \RuntimeException("Loose object path is not a regular file: {$oid}");
        }

        $compressed = file_get_contents($path);
        if ($compressed === false) {
            throw new \RuntimeException("Unable to read loose object: {$oid}");
        }

        $this->assertWithinAllocationLimit($compressed, strtolower($oid));

        $bytes = self::inflateStorageBytesExactly($compressed, strtolower($oid));

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
        $this->assertObjectId($oid);

        $path = $this->pathFor($oid);
        if (!self::objectPathExists($path)) {
            return null;
        }
        if (!is_file($path)) {
            throw new \RuntimeException("Loose object path is not a regular file: {$oid}");
        }

        $compressed = file_get_contents($path);
        if ($compressed === false) {
            throw new \RuntimeException("Unable to read loose object header: {$oid}");
        }

        return GitObject::decodeLooseHeader(self::inflateHeaderBytes($compressed, strtolower($oid)));
    }

    public function tryRead(string $oid): ?GitObject
    {
        $this->assertObjectId($oid);
        if (!self::objectPathExists($this->pathFor($oid))) {
            return null;
        }

        return $this->read($oid);
    }

    public function contains(string $oid): bool
    {
        $this->assertObjectId($oid);

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
        $suffixLength = self::hashHexLength($this->algorithm) - 2;
        foreach ($directories as $directory) {
            $prefix = basename($directory);
            $files = glob($directory . '/*') ?: [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $suffix = basename($file);
                if (is_file($file) && preg_match('/^[0-9a-f]{' . $suffixLength . '}$/', $suffix) === 1) {
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
        foreach ($this->integrityObjectIds() as $oid) {
            try {
                $object = $this->read($oid);
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                throw new \RuntimeException("Loose object {$oid} could not be read exactly: {$exception->getMessage()}", 0, $exception);
            }

            $actual = $object->oid($this->algorithm);
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

    private static function objectPathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    /**
     * @return list<string>
     */
    private function integrityObjectIds(): array
    {
        $objectsDirectory = $this->objectsDirectory;
        if (!is_dir($objectsDirectory)) {
            return [];
        }

        $ids = [];
        $suffixLength = self::hashHexLength($this->algorithm) - 2;
        $this->collectIntegrityObjectIds($objectsDirectory, 0, $suffixLength, $ids);

        $ids = array_keys($ids);
        sort($ids, SORT_STRING);

        return $ids;
    }

    /**
     * @param array<string,true> $ids
     */
    private function collectIntegrityObjectIds(string $directory, int $depth, int $suffixLength, array &$ids): void
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            $entryDepth = $depth + 1;
            if ($entryDepth >= 2 && $entryDepth <= 3) {
                $prefix = basename(dirname($path));
                $suffix = basename($path);
                if (
                    preg_match('/^[0-9a-fA-F]{2}$/', $prefix) === 1
                    && preg_match('/^[0-9a-fA-F]{' . $suffixLength . '}$/', $suffix) === 1
                ) {
                    $ids[strtolower($prefix . $suffix)] = true;
                }
            }

            if ($entryDepth < 3 && is_dir($path) && !is_link($path)) {
                $this->collectIntegrityObjectIds($path, $entryDepth, $suffixLength, $ids);
            }
        }
    }

    private function assertObjectId(string $oid): void
    {
        $length = self::hashHexLength($this->algorithm);
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Loose object id must be a {$length}-character " . strtoupper($this->algorithm) . ' hex string');
        }
    }

    private static function normalizeAlgorithm(string $algorithm): string
    {
        $algorithm = strtolower($algorithm);
        if (!isset(self::HASH_ALGORITHMS[$algorithm])) {
            throw new \InvalidArgumentException("Unsupported loose object hash algorithm: {$algorithm}");
        }

        return $algorithm;
    }

    private static function normalizeAllocationLimit(?int $limit): ?int
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('Loose object allocation limit must not be negative');
        }

        return $limit;
    }

    private function assertWithinAllocationLimit(string $compressed, string $oid): void
    {
        if ($this->allocationLimitBytes === null) {
            return;
        }

        $header = GitObject::decodeLooseHeader(self::inflateHeaderBytes($compressed, $oid));
        if ($header['size'] > $this->allocationLimitBytes) {
            throw new \RuntimeException("Loose object declared size {$header['size']} exceeds allocation limit {$this->allocationLimitBytes} bytes");
        }
    }

    private static function hashHexLength(string $algorithm): int
    {
        return self::HASH_ALGORITHMS[$algorithm];
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

    private static function inflateStorageBytesExactly(string $compressed, string $oid): string
    {
        if ($compressed === '') {
            throw new \RuntimeException("Unable to inflate loose object: {$oid}");
        }

        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new \RuntimeException('Unable to initialize zlib inflate context');
        }

        $inflated = '';
        $expectedLength = null;
        $offset = 0;
        $length = strlen($compressed);
        while ($offset < $length) {
            $remainingInflated = $expectedLength === null ? self::HEADER_MAX_SIZE : $expectedLength - strlen($inflated);
            $chunkSize = $remainingInflated <= self::HEADER_MAX_SIZE ? 16 : 8192;
            $chunk = substr($compressed, $offset, $chunkSize);
            $offset += strlen($chunk);
            $decoded = @inflate_add($context, $chunk, $offset >= $length ? ZLIB_FINISH : ZLIB_NO_FLUSH);
            if ($decoded === false) {
                throw new \RuntimeException("Unable to inflate loose object: {$oid}");
            }

            if ($decoded === '') {
                continue;
            }

            $inflated .= $decoded;
            if ($expectedLength === null) {
                $nul = strpos($inflated, "\0");
                if ($nul === false) {
                    if (strlen($inflated) >= self::HEADER_MAX_SIZE) {
                        throw new \InvalidArgumentException('Loose object header exceeds maximum size of 64 bytes');
                    }
                    continue;
                }
                if ($nul + 1 > self::HEADER_MAX_SIZE) {
                    throw new \InvalidArgumentException('Loose object header exceeds maximum size of 64 bytes');
                }

                $header = GitObject::decodeLooseHeader(substr($inflated, 0, $nul + 1));
                $expectedLength = $header['headerLength'] + $header['size'];
            }

            if ($expectedLength !== null && strlen($inflated) > $expectedLength) {
                throw new \RuntimeException("Loose object inflated size mismatch: expected {$expectedLength}, got " . strlen($inflated));
            }
        }

        if ($expectedLength === null) {
            throw new \InvalidArgumentException('Did not find 0 byte in header');
        }

        $actualLength = strlen($inflated);
        if ($actualLength !== $expectedLength) {
            throw new \RuntimeException("Loose object inflated size mismatch: expected {$expectedLength}, got {$actualLength}");
        }

        return $inflated;
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
