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
     * @var array<string, array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}>
     */
    private array $objects = [];

    /**
     * @var array<string, array{path: string, entry: array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}}>
     */
    private array $duplicateObjects = [];

    private int $duplicateObjectSequence = 0;

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
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->putEntry($path, $this->objectEntry($bytes, $options));
    }

    /**
     * Add another object with the same remote path, matching providers that
     * support duplicate names via unchecked writes.
     *
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     */
    public function putUnchecked(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->putDuplicateEntry($path, $this->objectEntry($bytes, $options));
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

    public function pathExists(string $path): bool
    {
        $path = $this->normalize($path);
        if (array_key_exists($this->canonicalPath($path), $this->objects)) {
            return true;
        }

        foreach ($this->duplicateObjects as $duplicate) {
            if ($this->sameProviderPath($duplicate['path'], $path)) {
                return true;
            }
        }

        return false;
    }

    public function delete(string $path): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $info = $this->info($path);
        $this->forget($path);

        return $info;
    }

    public function deleteListedObject(ObjectInfo $info): ObjectInfo
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            $deleted = $this->duplicateInfo($info->providerKey);
            unset($this->duplicateObjects[$info->providerKey]);

            return $deleted;
        }

        return $this->delete($info->path);
    }

    public function renameListedObject(ObjectInfo $info, string $targetPath): ObjectInfo
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            $entry = $this->duplicateObjects[$info->providerKey]['entry'];
            unset($this->duplicateObjects[$info->providerKey]);

            return $this->putEntry($targetPath, $entry);
        }

        return $this->renameObject($info->path, $targetPath);
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

    public function serverSideCopyTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        if (!$target->serverSideCopy) {
            throw new \RuntimeException(self::ERROR_CANT_COPY);
        }

        $target->throwConfiguredError($target->serverSideCopyError);

        return $this->copyTo($sourcePath, $target, $targetPath);
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

    public function directServerSideMoveTo(string $sourcePath, self $target, string $targetPath): ObjectInfo
    {
        if ($this !== $target || !$this->serverSideMove) {
            throw new \RuntimeException(self::ERROR_CANT_MOVE);
        }

        $this->throwConfiguredError($this->serverSideMoveError);

        return $this->renameObject($sourcePath, $targetPath);
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

    /**
     * Merge directory contents into the first directory, matching the provider
     * MergeDirs contract used by rclone dedupe before duplicate file handling.
     *
     * @param list<string|ObjectInfo> $directories
     * @return array{target: ObjectInfo, moved: list<ObjectInfo>, removed: list<ObjectInfo>}
     */
    public function mergeDirectories(array $directories): array
    {
        if ($directories === []) {
            throw new \InvalidArgumentException('merge directories requires at least one directory');
        }

        $targetPath = $this->canonicalDirectoryPath($this->directoryPath($directories[0]));
        $target = $this->directoryInfo($targetPath);
        $moved = [];
        $removed = [];

        for ($i = 1; $i < count($directories); $i++) {
            $sourcePath = $this->canonicalDirectoryPath($this->directoryPath($directories[$i]));
            if ($sourcePath === $targetPath) {
                continue;
            }
            if (self::pathIsOrUnder($targetPath, $sourcePath)) {
                throw new \InvalidArgumentException("can't merge parent directory {$sourcePath} into child {$targetPath}");
            }

            $source = $this->directoryInfo($sourcePath);
            $childDirs = array_values(array_filter(
                $this->directories($sourcePath),
                static fn (ObjectInfo $info): bool => $info->path !== $sourcePath
                    && self::pathIsOrUnder($info->path, $sourcePath),
            ));
            usort(
                $childDirs,
                static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathDepth($a->path) <=> self::pathDepth($b->path)
                    ?: $a->path <=> $b->path,
            );

            foreach ($childDirs as $directory) {
                $moved[] = $this->mkdir(
                    self::replacePathPrefix($directory->path, $sourcePath, $targetPath),
                    [
                        'modTime' => $directory->modTime,
                        'mimeType' => $directory->mimeType,
                        'metadata' => $directory->metadata,
                        'id' => $directory->id,
                    ],
                );
            }

            $objects = array_values(array_filter(
                $this->list($sourcePath),
                static fn (ObjectInfo $info): bool => self::pathIsOrUnder($info->path, $sourcePath),
            ));
            foreach ($objects as $object) {
                $newPath = self::replacePathPrefix($object->path, $sourcePath, $targetPath);
                $entry = $this->listedObjectEntry($object);
                $this->forgetListedObject($object);
                $moved[] = $this->pathExists($newPath)
                    ? $this->putDuplicateEntry($newPath, $entry)
                    : $this->putEntry($newPath, $entry);
            }

            usort(
                $childDirs,
                static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathDepth($b->path) <=> self::pathDepth($a->path)
                    ?: $b->path <=> $a->path,
            );
            foreach ($childDirs as $directory) {
                if ($this->directoryExists($directory->path)) {
                    $removed[] = $this->directoryInfo($directory->path);
                    $this->forgetDirectory($directory->path);
                }
            }
            if ($this->directoryExists($sourcePath)) {
                $removed[] = $source;
                $this->forgetDirectory($sourcePath);
            }
        }

        return [
            'target' => $target,
            'moved' => $moved,
            'removed' => $removed,
        ];
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
        foreach ($this->duplicateObjects as $duplicate) {
            if (self::pathIsOrUnder($duplicate['path'], $path)) {
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
        if (!array_key_exists($path, $this->objects)) {
            foreach ($this->duplicateObjects as $key => $duplicate) {
                if ($this->sameProviderPath($duplicate['path'], $path)) {
                    return $this->duplicateInfo($key);
                }
            }
        }
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
            $entry['hashes'],
            'path:' . $path,
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
        foreach (array_keys($this->duplicateObjects) as $key) {
            $path = $this->duplicateObjects[$key]['path'];
            if ($prefix === '' || $this->pathStartsWith($path, $prefix)) {
                $items[] = $this->duplicateInfo($key);
            }
        }
        usort(
            $items,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

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

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, hashes?: array<string, string>} $options
     */
    public function updateObjectInfo(string $path, array $options): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);

        if (array_key_exists('modTime', $options)) {
            $entry['modTime'] = $this->normalizeModTime($options['modTime']);
        }
        if (array_key_exists('mimeType', $options)) {
            $entry['mimeType'] = $options['mimeType'];
        }
        if (array_key_exists('metadata', $options)) {
            $entry['metadata'] = $options['metadata'] ?? [];
        }
        if (array_key_exists('id', $options)) {
            $entry['id'] = $options['id'];
        }
        if (array_key_exists('tier', $options)) {
            $entry['tier'] = $options['tier'];
        }
        if (array_key_exists('hashes', $options)) {
            $entry['hashes'] = $this->normalizeHashes($options['hashes'] ?? []);
        }

        return $this->putEntry($path, $entry);
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
        $entry = $this->entry($path);
        $hashes = [];
        foreach ($set->toArray() as $type) {
            if (isset($entry['hashes'][$type])) {
                $hashes[$type] = $entry['hashes'][$type];
            }
        }

        return $hashes + MultiHasher::hashBytes($this->get($path), $set);
    }

    /**
     * @return array<string, string>
     */
    public function hashesForObject(ObjectInfo $info, ?HashSet $set = null): array
    {
        $set = ($set ?? $this->supportedHashes)->overlap($this->supportedHashes);
        $entry = $info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])
            ? $this->duplicateObjects[$info->providerKey]['entry']
            : $this->entry($info->path);
        $hashes = [];
        foreach ($set->toArray() as $type) {
            if (isset($entry['hashes'][$type])) {
                $hashes[$type] = $entry['hashes'][$type];
            }
        }

        return $hashes + MultiHasher::hashBytes($entry['bytes'], $set);
    }

    public function directoryEntryCount(string|ObjectInfo $directory): int
    {
        $path = $this->canonicalDirectoryPath($this->directoryPath($directory));
        $this->directoryInfo($path);
        $count = 0;
        foreach ($this->directories($path) as $info) {
            if ($info->path !== $path && self::pathIsOrUnder($info->path, $path)) {
                $count++;
            }
        }
        foreach ($this->list($path) as $info) {
            if (self::pathIsOrUnder($info->path, $path)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    /**
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}
     */
    private function entry(string $path): array
    {
        $path = $this->canonicalPath($path);
        if (!array_key_exists($path, $this->objects)) {
            foreach ($this->duplicateObjects as $duplicate) {
                if ($this->sameProviderPath($duplicate['path'], $path)) {
                    return $duplicate['entry'];
                }
            }
            throw new \RuntimeException("Object not found: {$path}");
        }

        return $this->objects[$path];
    }

    /**
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>} $options
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}
     */
    private function objectEntry(string $bytes, array $options): array
    {
        return [
            'bytes' => $bytes,
            'unknownSize' => (bool) ($options['unknownSize'] ?? false),
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'tier' => $options['tier'] ?? null,
            'hashes' => $this->normalizeHashes($options['hashes'] ?? []),
            'openError' => $this->normalizeThrowable($options['openError'] ?? null),
            'readError' => $this->normalizeThrowable($options['readError'] ?? null),
            'readErrorAfterBytes' => array_key_exists('readBreaks', $options) && !array_key_exists('readErrorAfterBytes', $options)
                ? null
                : $this->normalizeReadErrorAfterBytes($options),
            'readBreaks' => array_map(static fn (int $break): int => max(0, $break), $options['readBreaks'] ?? []),
        ];
    }

    private function duplicateInfo(string $key): ObjectInfo
    {
        if (!isset($this->duplicateObjects[$key])) {
            throw new \RuntimeException("Object not found: {$key}");
        }

        $duplicate = $this->duplicateObjects[$key];
        $entry = $duplicate['entry'];
        $bytes = $entry['bytes'];

        return new ObjectInfo(
            $duplicate['path'],
            $entry['unknownSize'] ? -1 : strlen($bytes),
            hash('sha256', $bytes),
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
            $entry['tier'],
            $entry['hashes'],
            $key,
        );
    }

    /**
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>} $entry
     */
    private function putDuplicateEntry(string $path, array $entry): ObjectInfo
    {
        $key = 'unchecked:' . sprintf('%08d', ++$this->duplicateObjectSequence);
        $this->duplicateObjects[$key] = [
            'path' => $this->normalize($path),
            'entry' => $entry,
        ];

        return $this->duplicateInfo($key);
    }

    /**
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>}
     */
    private function listedObjectEntry(ObjectInfo $info): array
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            return $this->duplicateObjects[$info->providerKey]['entry'];
        }

        return $this->entry($info->path);
    }

    private function forgetListedObject(ObjectInfo $info): void
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            unset($this->duplicateObjects[$info->providerKey]);

            return;
        }

        $this->forget($info->path);
    }

    /**
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>} $entry
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

    private function directoryPath(string|ObjectInfo $directory): string
    {
        return $directory instanceof ObjectInfo ? $directory->path : $directory;
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
     * @param array<string, string> $hashes
     * @return array<string, string>
     */
    private function normalizeHashes(array $hashes): array
    {
        $normalized = [];
        foreach ($hashes as $type => $hash) {
            $normalized[HashType::fromString((string) $type)] = strtolower($hash);
        }

        return $normalized;
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

        foreach ($this->duplicateObjects as $duplicate) {
            $this->addParentDirectories($dirs, $duplicate['path']);
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

    private function sameProviderPath(string $left, string $right): bool
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);
        if (!$this->caseInsensitive) {
            return $left === $right;
        }

        return strtolower($left) === strtolower($right);
    }

    private static function pathIsOrUnder(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function pathDepth(string $path): int
    {
        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private static function replacePathPrefix(string $path, string $sourcePrefix, string $targetPrefix): string
    {
        if ($path === $sourcePrefix) {
            return $targetPrefix;
        }

        return $targetPrefix . substr($path, strlen($sourcePrefix));
    }
}
