<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MemoryProvider
{
    /**
     * @var array<string, array{bytes: string, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}>
     */
    private array $objects = [];

    /**
     * @var array<string, string>
     */
    private array $caseIndex = [];

    /**
     * @var list<array{path: string, offset: int, length: ?int}>
     */
    private array $openLog = [];

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
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
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
            'openError' => $this->normalizeThrowable($options['openError'] ?? null),
            'readError' => $this->normalizeThrowable($options['readError'] ?? null),
            'readErrorAfterBytes' => array_key_exists('readBreaks', $options) && !array_key_exists('readErrorAfterBytes', $options)
                ? null
                : $this->normalizeReadErrorAfterBytes($options),
            'readBreaks' => array_map(static fn (int $break): int => max(0, $break), $options['readBreaks'] ?? []),
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

    public function openReader(string $path, int $offset = 0, ?int $length = null): object
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);
        $offset = max(0, $offset);
        if ($length !== null) {
            $length = max(0, $length);
        }

        $this->openLog[] = ['path' => $path, 'offset' => $offset, 'length' => $length];
        if ($entry['openError'] !== null) {
            throw $entry['openError'];
        }

        $readError = $entry['readError'];
        $readErrorAfterBytes = $entry['readErrorAfterBytes'];
        if ($entry['readBreaks'] !== []) {
            $readError = $readError ?? new \RuntimeException('read failed');
            $break = array_shift($this->objects[$path]['readBreaks']);
            if ($break === 0) {
                throw $readError;
            }
            $readErrorAfterBytes = $break;
        } elseif ($readErrorAfterBytes !== null) {
            $readErrorAfterBytes = max(0, $readErrorAfterBytes - $offset);
        }

        $bytes = $length === null
            ? substr($entry['bytes'], $offset)
            : substr($entry['bytes'], $offset, $length);

        return new class($bytes, $readError, $readErrorAfterBytes) {
            private int $offset = 0;

            public function __construct(
                private readonly string $bytes,
                private readonly ?\Throwable $readError,
                private readonly ?int $readErrorAfterBytes,
            ) {
            }

            public function read(int $length): string
            {
                if ($length <= 0) {
                    return '';
                }
                if ($this->shouldFail()) {
                    throw $this->readError;
                }
                if ($this->offset >= strlen($this->bytes)) {
                    return '';
                }

                $limit = $length;
                if ($this->readError !== null && $this->readErrorAfterBytes !== null && $this->readErrorAfterBytes > $this->offset) {
                    $limit = min($limit, $this->readErrorAfterBytes - $this->offset);
                }

                $chunk = substr($this->bytes, $this->offset, $limit);
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function eof(): bool
            {
                if ($this->readError !== null && $this->readErrorAfterBytes !== null && $this->readErrorAfterBytes <= strlen($this->bytes) && $this->offset >= $this->readErrorAfterBytes) {
                    return false;
                }

                return $this->offset >= strlen($this->bytes);
            }

            private function shouldFail(): bool
            {
                return $this->readError !== null
                    && $this->readErrorAfterBytes !== null
                    && $this->offset >= $this->readErrorAfterBytes;
            }

            public function close(): void
            {
            }
        };
    }

    /**
     * @return list<array{path: string, offset: int, length: ?int}>
     */
    public function openLog(): array
    {
        return $this->openLog;
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
     * @return array{bytes: string, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}
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

    private function normalizeThrowable(\Throwable|string|null $error): ?\Throwable
    {
        if ($error === null || $error instanceof \Throwable) {
            return $error;
        }

        return new \RuntimeException($error);
    }

    /**
     * @param array{readError?: \Throwable|string|null, readErrorAfterBytes?: int|null} $options
     */
    private function normalizeReadErrorAfterBytes(array $options): ?int
    {
        if (!array_key_exists('readError', $options) || $options['readError'] === null) {
            return null;
        }
        if (!array_key_exists('readErrorAfterBytes', $options) || $options['readErrorAfterBytes'] === null) {
            return 0;
        }

        return max(0, (int) $options['readErrorAfterBytes']);
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
