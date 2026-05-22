<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MemoryProvider
{
    public const ERROR_CANT_MOVE = "can't move object - incompatible remotes";
    public const ERROR_CANT_COPY = "can't copy object - incompatible remotes";
    public const ERROR_CANT_DIR_MOVE = "can't move directory - incompatible remotes";
    public const ERROR_DIR_EXISTS = "can't copy directory - destination already exists";

    /**
     * @var array<string, array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}>
     */
    private array $objects = [];

    /**
     * @var array<string, array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string}>
     */
    private array $directories = [];

    /**
     * @var array<string, string>
     */
    private array $caseIndex = [];

    /**
     * @var array<string, string>
     */
    private array $directoryCaseIndex = [];

    /**
     * @var list<array{path: string, offset: int, length: ?int}>
     */
    private array $openLog = [];

    private readonly HashSet $supportedHashes;
    private readonly bool $serverSideMove;
    private readonly bool $serverSideCopy;
    private readonly bool $serverSideDirMove;
    private readonly ?string $serverSideMoveError;
    private readonly ?string $serverSideCopyError;
    private readonly ?string $serverSideDirMoveError;

    public function __construct(
        private readonly bool $caseInsensitive = false,
        ?HashSet $supportedHashes = null,
        bool $serverSideMove = true,
        ?bool $serverSideCopy = null,
        ?bool $serverSideDirMove = null,
        ?string $serverSideMoveError = null,
        ?string $serverSideCopyError = null,
        ?string $serverSideDirMoveError = null,
    )
    {
        $this->supportedHashes = $supportedHashes === null
            ? HashSet::supported()
            : new HashSet(...$supportedHashes->toArray());
        $this->serverSideMove = $serverSideMove;
        $this->serverSideCopy = $serverSideCopy ?? $serverSideMove;
        $this->serverSideDirMove = $serverSideDirMove ?? $serverSideMove;
        $this->serverSideMoveError = $serverSideMoveError;
        $this->serverSideCopyError = $serverSideCopyError;
        $this->serverSideDirMoveError = $serverSideDirMoveError;
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
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->putEntry($path, [
            'bytes' => $bytes,
            'unknownSize' => (bool) ($options['unknownSize'] ?? false),
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
        ]);
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null} $options
     */
    public function mkdir(string $path, array $options = []): ObjectInfo
    {
        return $this->putDirectoryEntry($path, [
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
        ]);
    }

    public function mkdirModTime(string $path, \DateTimeInterface|string|null $modTime): ObjectInfo
    {
        return $this->mkdir($path, ['modTime' => $modTime]);
    }

    public function setDirectoryModTime(
        string $path,
        \DateTimeInterface|string|null $modTime,
        bool $noUpdateDirModTime = false,
    ): ?ObjectInfo {
        if ($noUpdateDirModTime) {
            return null;
        }

        $path = $this->canonicalDirectoryPath($path);
        if (!$this->directoryExists($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }

        $entry = $this->directories[$path] ?? [
            'modTime' => null,
            'mimeType' => null,
            'metadata' => [],
            'id' => null,
        ];
        $entry['modTime'] = $this->normalizeModTime($modTime);

        return $this->putDirectoryEntry($path, $entry);
    }

    public function isCaseInsensitive(): bool
    {
        return $this->caseInsensitive;
    }

    public function supportsServerSideMove(): bool
    {
        return $this->serverSideMove || $this->serverSideCopy;
    }

    public function supportsDirectServerSideMove(): bool
    {
        return $this->serverSideMove;
    }

    public function supportsServerSideCopy(): bool
    {
        return $this->serverSideCopy;
    }

    public function supportsServerSideDirMove(): bool
    {
        return $this->serverSideDirMove;
    }

    public function get(string $path): string
    {
        return $this->entry($path)['bytes'];
    }

    public function delete(string $path): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $info = $this->info($path);
        $this->forget($path);

        return $info;
    }

    public function moveTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        if ($this === $target) {
            return $this->renameObject($sourcePath, $targetPath);
        }

        $sourcePath = $this->canonicalPath($sourcePath);
        $entry = $this->entry($sourcePath);
        $targetInfo = $target->putEntry($targetPath, $entry);
        $targetPath = $target->canonicalPath($targetPath);

        $this->forget($sourcePath);

        return $targetInfo;
    }

    public function serverSideMoveTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        $sourcePath = $this->canonicalPath($sourcePath);
        $targetPath = $target->normalize($targetPath);
        if ($this === $target && $sourcePath === $targetPath) {
            return $this->info($sourcePath);
        }

        $mayFallbackToCopy = false;
        if ($this === $target && $target->serverSideMove) {
            try {
                $target->throwConfiguredError($target->serverSideMoveError);

                return $this->renameObject($sourcePath, $targetPath);
            } catch (\RuntimeException $throwable) {
                if (!self::isCantMoveException($throwable)) {
                    throw $throwable;
                }
                $mayFallbackToCopy = true;
            }
        }

        if (!$mayFallbackToCopy && !$target->serverSideCopy) {
            throw new \RuntimeException(self::ERROR_CANT_MOVE);
        }

        if ($target->serverSideCopy) {
            try {
                $target->throwConfiguredError($target->serverSideCopyError);
            } catch (\RuntimeException $throwable) {
                if (!self::isCantCopyException($throwable)) {
                    throw $throwable;
                }
            }
        }

        $targetInfo = $this->copyTo($sourcePath, $target, $targetPath);
        $this->forget($sourcePath);

        return $targetInfo;
    }

    public function renameObject(string $sourcePath, string $targetPath): ObjectInfo
    {
        $sourcePath = $this->canonicalPath($sourcePath);
        $targetPath = $this->normalize($targetPath);
        $entry = $this->entry($sourcePath);
        if ($sourcePath === $targetPath) {
            return $this->info($sourcePath);
        }

        $this->forget($sourcePath);

        return $this->putEntry($targetPath, $entry);
    }

    public function renameDirectory(string $sourcePath, string $targetPath): ObjectInfo
    {
        $sourcePath = $this->canonicalDirectoryPath($sourcePath);
        $targetPath = $this->normalize($targetPath);
        if (!$this->directoryExists($sourcePath)) {
            throw new \RuntimeException("Directory not found: {$sourcePath}");
        }
        if ($sourcePath === $targetPath) {
            return $this->directoryInfo($sourcePath);
        }

        $directoryMoves = [];
        foreach ($this->directories as $path => $entry) {
            if (self::pathIsOrUnder($path, $sourcePath)) {
                $directoryMoves[] = [
                    'old' => $path,
                    'new' => self::replacePathPrefix($path, $sourcePath, $targetPath),
                    'entry' => $entry,
                ];
            }
        }

        $objectMoves = [];
        foreach ($this->objects as $path => $entry) {
            if (self::pathIsOrUnder($path, $sourcePath)) {
                $objectMoves[] = [
                    'old' => $path,
                    'new' => self::replacePathPrefix($path, $sourcePath, $targetPath),
                    'entry' => $entry,
                ];
            }
        }

        foreach ($directoryMoves as $move) {
            $this->forgetDirectory($move['old']);
        }
        foreach ($objectMoves as $move) {
            $this->forget($move['old']);
        }
        foreach ($directoryMoves as $move) {
            $this->putDirectoryEntry($move['new'], $move['entry']);
        }
        foreach ($objectMoves as $move) {
            $this->putEntry($move['new'], $move['entry']);
        }

        return $this->directoryInfo($targetPath);
    }

    public function serverSideDirMove(string $sourcePath, string $targetPath): ObjectInfo
    {
        if (!$this->serverSideDirMove) {
            throw new \RuntimeException(self::ERROR_CANT_DIR_MOVE);
        }

        $this->throwConfiguredError($this->serverSideDirMoveError);

        return $this->renameDirectory($sourcePath, $targetPath);
    }

    public function rmdir(string $path): ObjectInfo
    {
        $path = $this->canonicalDirectoryPath($path);
        if (!$this->directoryExists($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }
        foreach (array_keys($this->objects) as $objectPath) {
            if (self::pathIsOrUnder($objectPath, $path)) {
                throw new \RuntimeException("Directory not empty: {$path}");
            }
        }
        foreach (array_keys($this->directories) as $directoryPath) {
            if ($directoryPath !== $path && self::pathIsOrUnder($directoryPath, $path)) {
                throw new \RuntimeException("Directory not empty: {$path}");
            }
        }

        $info = $this->directoryInfo($path);
        if ($path !== '') {
            $this->forgetDirectory($path);
        }

        return $info;
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
            $entry['unknownSize'] ? -1 : strlen($bytes),
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

    public function directoryInfo(string $path): ObjectInfo
    {
        $path = $this->canonicalDirectoryPath($path);
        if (!$this->directoryExists($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }

        $entry = $this->directories[$path] ?? [
            'modTime' => null,
            'mimeType' => null,
            'metadata' => [],
            'id' => null,
        ];

        return new ObjectInfo(
            $path,
            -1,
            '',
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
        );
    }

    /**
     * @return list<ObjectInfo>
     */
    public function directories(string $prefix = ''): array
    {
        $prefix = $this->normalize($prefix);
        $items = [];
        foreach (array_keys($this->allDirectoryPaths()) as $path) {
            if ($path === '') {
                continue;
            }
            if ($prefix === '' || $path === $prefix || $this->pathStartsWith($path, $prefix)) {
                $items[] = $this->directoryInfo($path);
            }
        }
        usort($items, static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path);

        return $items;
    }

    public function copyTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        $entry = $this->entry($sourcePath);

        return $target->putEntry($targetPath, $entry);
    }

    public function setModTime(string $path, \DateTimeInterface|string|null $modTime): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $this->objects[$path]['modTime'] = $this->normalizeModTime($modTime);

        return $this->info($path);
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
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}
     */
    private function entry(string $path): array
    {
        $path = $this->canonicalPath($path);
        if (!array_key_exists($path, $this->objects)) {
            throw new \RuntimeException("Object not found: {$path}");
        }

        return $this->objects[$path];
    }

    /**
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>} $entry
     */
    private function putEntry(string $path, array $entry): ObjectInfo
    {
        $path = $this->normalize($path);
        if ($this->caseInsensitive) {
            $lookup = $this->lookupPath($path);
            if (isset($this->caseIndex[$lookup]) && $this->caseIndex[$lookup] !== $path) {
                unset($this->objects[$this->caseIndex[$lookup]]);
            }
            $this->caseIndex[$lookup] = $path;
        }

        $this->objects[$path] = $entry;

        return $this->info($path);
    }

    /**
     * @param array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string} $entry
     */
    private function putDirectoryEntry(string $path, array $entry): ObjectInfo
    {
        $path = $this->normalize($path);
        if ($this->caseInsensitive) {
            $lookup = $this->lookupPath($path);
            if (isset($this->directoryCaseIndex[$lookup]) && $this->directoryCaseIndex[$lookup] !== $path) {
                unset($this->directories[$this->directoryCaseIndex[$lookup]]);
            }
            $this->directoryCaseIndex[$lookup] = $path;
        }

        $this->directories[$path] = $entry;

        return $this->directoryInfo($path);
    }

    private function forget(string $path): void
    {
        unset($this->objects[$path]);
        if ($this->caseInsensitive) {
            unset($this->caseIndex[$this->lookupPath($path)]);
        }
    }

    private function forgetDirectory(string $path): void
    {
        unset($this->directories[$path]);
        if ($this->caseInsensitive) {
            unset($this->directoryCaseIndex[$this->lookupPath($path)]);
        }
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

    private function throwConfiguredError(?string $message): void
    {
        if ($message !== null && $message !== '') {
            throw new \RuntimeException($message);
        }
    }

    public static function isCantMoveException(\Throwable $throwable): bool
    {
        return $throwable->getMessage() === self::ERROR_CANT_MOVE;
    }

    public static function isCantCopyException(\Throwable $throwable): bool
    {
        return $throwable->getMessage() === self::ERROR_CANT_COPY;
    }

    public static function isCantDirMoveException(\Throwable $throwable): bool
    {
        return $throwable->getMessage() === self::ERROR_CANT_DIR_MOVE;
    }

    public static function isDirExistsException(\Throwable $throwable): bool
    {
        return $throwable->getMessage() === self::ERROR_DIR_EXISTS;
    }

    private function canonicalPath(string $path): string
    {
        $path = $this->normalize($path);
        if (!$this->caseInsensitive) {
            return $path;
        }

        return $this->caseIndex[$this->lookupPath($path)] ?? $path;
    }

    private function canonicalDirectoryPath(string $path): string
    {
        $path = $this->normalize($path);
        if ($path === '' || !$this->caseInsensitive) {
            return $path;
        }

        $lookup = $this->lookupPath($path);
        if (isset($this->directoryCaseIndex[$lookup])) {
            return $this->directoryCaseIndex[$lookup];
        }

        foreach (array_keys($this->allDirectoryPaths()) as $candidate) {
            if ($this->lookupPath($candidate) === $lookup) {
                return $candidate;
            }
        }

        return $path;
    }

    private function directoryExists(string $path): bool
    {
        $path = $this->canonicalDirectoryPath($path);

        return $path === '' || isset($this->allDirectoryPaths()[$path]);
    }

    /**
     * @return array<string, true>
     */
    private function allDirectoryPaths(): array
    {
        $dirs = ['' => true];

        foreach (array_keys($this->objects) as $path) {
            $this->addParentDirectories($dirs, $path);
        }

        foreach (array_keys($this->directories) as $path) {
            $dirs[$path] = true;
            $this->addParentDirectories($dirs, $path);
        }

        return $dirs;
    }

    /**
     * @param array<string, true> $dirs
     */
    private function addParentDirectories(array &$dirs, string $path): void
    {
        $segments = explode('/', $path);
        $prefix = '';
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $prefix = $prefix === '' ? $segments[$i] : $prefix . '/' . $segments[$i];
            if ($prefix !== '') {
                $dirs[$prefix] = true;
            }
        }
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

    private static function pathIsOrUnder(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function replacePathPrefix(string $path, string $sourcePrefix, string $targetPrefix): string
    {
        if ($path === $sourcePrefix) {
            return $targetPrefix;
        }

        return $targetPrefix . substr($path, strlen($sourcePrefix));
    }
}
