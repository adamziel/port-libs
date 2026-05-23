<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class MemoryProvider
{
    public const ERROR_CANT_MOVE = "can't move object - incompatible remotes";
    public const ERROR_CANT_COPY = "can't copy object - incompatible remotes";
    public const ERROR_CANT_DIR_MOVE = "can't move directory - incompatible remotes";
    public const ERROR_DIR_EXISTS = "can't copy directory - destination already exists";
    public const ERROR_CANT_PURGE = "can't purge directory";
    public const ERROR_CANT_CLEANUP = "memory provider doesn't support cleanup";

    /**
     * @var array<string, array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}>
     */
    private array $objects = [];

    /**
     * @var array<string, array{path: string, entry: array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}}>
     */
    private array $duplicateObjects = [];

    private int $duplicateObjectSequence = 0;

    /**
     * @var array<string, array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string}>
     */
    private array $directories = [];

    /**
     * @var array<string, array{path: string, entry: array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string}}>
     */
    private array $duplicateDirectories = [];

    private int $duplicateDirectorySequence = 0;

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

    /**
     * @var list<array{path: string, offset: int, length: ?int}>
     */
    private array $closeLog = [];

    /**
     * @var array<string, string>
     */
    private array $publicLinks = [];

    /**
     * @var array<string, \Throwable>
     */
    private array $deleteErrors = [];

    /**
     * @var array<string, \Throwable>
     */
    private array $setModTimeErrors = [];

    /**
     * @var array<string, array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}>
     */
    private array $trashObjects = [];

    /**
     * @var array<string, array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string}>
     */
    private array $trashDirectories = [];

    private int $cleanUpCalls = 0;

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
        private readonly bool $setTier = true,
        private readonly bool $getTier = true,
        private readonly bool $directPurge = true,
        private readonly ?string $directPurgeError = null,
        private readonly bool $cleanUp = false,
        private readonly ?string $cleanUpError = null,
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
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     */
    public function put(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->putEntry($path, $this->objectEntry($bytes, $options));
    }

    /**
     * Model rclone PutStream: callers may not know the source size up front,
     * but a successful provider object reports the stored byte length.
     *
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     */
    public function putStream(string $path, string $bytes, array $options = []): ObjectInfo
    {
        unset($options['unknownSize']);

        return $this->putEntry($path, $this->objectEntry($bytes, $options));
    }

    /**
     * Model fs.Object.Update: the source object's remote name is ignored and
     * the existing object's remote path is kept while bytes/metadata change.
     *
     * @param array{sourcePath?: string, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     */
    public function updateObject(string $path, string $bytes, array $options = []): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $this->assertObjectWritable($path, $this->entry($path));
        unset($options['sourcePath'], $options['unknownSize']);

        return $this->putEntry($path, $this->objectEntry($bytes, $options));
    }

    /**
     * Add another object with the same remote path, matching providers that
     * support duplicate names via unchecked writes.
     *
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     */
    public function putUnchecked(string $path, string $bytes, array $options = []): ObjectInfo
    {
        return $this->putDuplicateEntry($path, $this->objectEntry($bytes, $options));
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null} $options
     */
    public function mkdir(string $path, array $options = []): ObjectInfo
    {
        return $this->putDirectoryEntry($path, [
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'parentId' => $options['parentId'] ?? null,
        ]);
    }

    /**
     * Add another directory with the same remote path, matching providers that
     * expose duplicate directory entries with distinct IDs.
     *
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null} $options
     */
    public function mkdirUnchecked(string $path, array $options = []): ObjectInfo
    {
        return $this->putDuplicateDirectoryEntry($path, [
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'parentId' => $options['parentId'] ?? null,
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
            'parentId' => null,
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

    public function supportsSetTier(): bool
    {
        return $this->setTier;
    }

    public function supportsGetTier(): bool
    {
        return $this->getTier;
    }

    public function supportsDirectPurge(): bool
    {
        return $this->directPurge;
    }

    public function supportsCleanUp(): bool
    {
        return $this->cleanUp;
    }

    public function get(string $path): string
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);
        $this->assertObjectReadable($path, $entry);

        return $entry['bytes'];
    }

    /**
     * Read object bytes with rclone's SeekOption and RangeOption decode
     * semantics. Range ends are inclusive; a negative start with a positive
     * end fetches the final N bytes.
     *
     * @param array{seekOffset?: int, rangeStart?: int, rangeEnd?: int} $options
     */
    public function readObject(string $path, array $options = []): string
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);
        $this->assertObjectReadable($path, $entry);
        $bytes = $entry['bytes'];
        $size = strlen($bytes);
        $offset = 0;
        $limit = null;

        if (array_key_exists('seekOffset', $options)) {
            $offset = max(0, min($size, (int) $options['seekOffset']));
        } elseif (array_key_exists('rangeStart', $options) || array_key_exists('rangeEnd', $options)) {
            $start = (int) ($options['rangeStart'] ?? -1);
            $end = (int) ($options['rangeEnd'] ?? -1);
            if ($start >= 0) {
                $offset = max(0, min($size, $start));
                if ($end >= 0) {
                    $limit = max(0, min($size, $end + 1) - $offset);
                }
            } elseif ($end >= 0) {
                $offset = max(0, $size - $end);
            }
        }

        return $limit === null
            ? substr($bytes, $offset)
            : substr($bytes, $offset, $limit);
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
        $this->throwDeleteError($path);
        $this->forget($path);

        return $info;
    }

    public function deleteListedObject(ObjectInfo $info): ObjectInfo
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            $this->throwDeleteError($this->duplicateObjects[$info->providerKey]['path']);
            $deleted = $this->duplicateInfo($info->providerKey);
            unset($this->duplicateObjects[$info->providerKey]);

            return $deleted;
        }

        return $this->delete($info->path);
    }

    public function setDeleteError(string $path, \Throwable|string|null $error): void
    {
        $path = $this->normalize($path);
        if ($error === null) {
            unset($this->deleteErrors[$path]);

            return;
        }

        $this->deleteErrors[$path] = $this->normalizeThrowable($error);
    }

    public function setModTimeError(string $path, \Throwable|string|null $error): void
    {
        $path = $this->normalize($path);
        if ($error === null) {
            unset($this->setModTimeErrors[$path]);

            return;
        }

        $this->setModTimeErrors[$path] = $this->normalizeThrowable($error);
    }

    /**
     * Add a provider-side trash or old-version object. Cleanup candidates are
     * hidden from ordinary List/Get calls, matching remotes where `cleanup`
     * clears backend trash outside the visible tree.
     *
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     */
    public function putTrashedObject(string $path, string $bytes, array $options = []): ObjectInfo
    {
        $path = $this->normalize($path);
        $this->trashObjects[$path] = $this->objectEntry($bytes, $options);

        return $this->trashObjectInfo($path);
    }

    /**
     * @param array{modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null} $options
     */
    public function mkdirTrashedDirectory(string $path, array $options = []): ObjectInfo
    {
        $path = $this->normalize($path);
        $this->trashDirectories[$path] = [
            'modTime' => $this->normalizeModTime($options['modTime'] ?? null),
            'mimeType' => $options['mimeType'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'id' => $options['id'] ?? null,
            'parentId' => $options['parentId'] ?? null,
        ];

        return $this->trashDirectoryInfo($path);
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

    /**
     * @param array{metadataSet?: array<string, scalar|null>} $options
     */
    public function serverSideCopyTo(string $sourcePath, self $target, string $targetPath, array $options = []): ObjectInfo
    {
        if (!$target->serverSideCopy) {
            throw new \RuntimeException(self::ERROR_CANT_COPY);
        }

        $target->throwConfiguredError($target->serverSideCopyError);

        return $this->copyTo($sourcePath, $target, $targetPath, $options);
    }

    /**
     * @param array{metadataSet?: array<string, scalar|null>} $options
     */
    public function serverSideMoveTo(string $sourcePath, self $target, string $targetPath, array $options = []): ObjectInfo
    {
        $sourcePath = $this->canonicalPath($sourcePath);
        $targetPath = $target->normalize($targetPath);
        if ($this === $target && $sourcePath === $targetPath) {
            return $this->applyMetadataSet($sourcePath, $options);
        }

        $mayFallbackToCopy = false;
        if ($this === $target && $target->serverSideMove) {
            try {
                $target->throwConfiguredError($target->serverSideMoveError);

                $moved = $this->renameObject($sourcePath, $targetPath);

                return $this->applyMetadataSet($moved->path, $options);
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

        $targetInfo = $this->copyTo($sourcePath, $target, $targetPath, $options);
        $this->forget($sourcePath);

        return $targetInfo;
    }

    /**
     * @param array{metadataSet?: array<string, scalar|null>} $options
     */
    public function directServerSideMoveTo(string $sourcePath, self $target, string $targetPath, array $options = []): ObjectInfo
    {
        if ($this !== $target || !$this->serverSideMove) {
            throw new \RuntimeException(self::ERROR_CANT_MOVE);
        }

        $this->throwConfiguredError($this->serverSideMoveError);

        $moved = $this->renameObject($sourcePath, $targetPath);

        return $this->applyMetadataSet($moved->path, $options);
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

        $target = $this->listedDirectoryInfo($directories[0]);
        $targetPath = $this->canonicalDirectoryPath($target->path);
        $moved = [];
        $removed = [];

        for ($i = 1; $i < count($directories); $i++) {
            $source = $this->listedDirectoryInfo($directories[$i]);
            $sourcePath = $this->canonicalDirectoryPath($source->path);
            if ($this->sameDirectoryEntry($source, $target)) {
                continue;
            }
            if ($sourcePath === $targetPath) {
                $samePathMerge = $this->mergeSamePathDirectory($source, $target);
                $moved = array_merge($moved, $samePathMerge['moved']);
                $removed = array_merge($removed, $samePathMerge['removed']);
                continue;
            }
            if (self::pathIsOrUnder($targetPath, $sourcePath)) {
                throw new \InvalidArgumentException("can't merge parent directory {$sourcePath} into child {$targetPath}");
            }

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
                        'parentId' => $directory->parentId,
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

    /**
     * Model rclone's walk.GetAll/list.DirSorted provider contract. Returned
     * paths are provider-absolute, sorted, and limited by depth relative to
     * the requested directory. A max depth of -1 means recursive.
     *
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    public function walk(
        string $dir = '',
        int $maxDepth = -1,
        bool $includeObjects = true,
        bool $includeDirectories = true,
    ): array {
        $dir = $this->canonicalDirectoryPath($dir);
        if (!$this->directoryExists($dir)) {
            throw new \RuntimeException("Directory not found: {$dir}");
        }

        $objects = [];
        if ($includeObjects) {
            foreach ($this->list($dir) as $object) {
                if (!self::pathIsOrUnder($object->path, $dir)) {
                    continue;
                }
                $depth = self::relativeDepth($object->path, $dir);
                if ($depth > 0 && ($maxDepth < 0 || $depth <= $maxDepth)) {
                    $objects[] = $object;
                }
            }
        }

        $directories = [];
        if ($includeDirectories) {
            foreach ($this->directories($dir) as $directory) {
                if (!self::pathIsOrUnder($directory->path, $dir)) {
                    continue;
                }
                $depth = self::relativeDepth($directory->path, $dir);
                if ($depth > 0 && ($maxDepth < 0 || $depth <= $maxDepth)) {
                    $directories[] = $directory;
                }
            }
        }

        return [
            'objects' => $objects,
            'directories' => $directories,
        ];
    }

    /**
     * Purge a directory and its contents. This is deliberately stronger than
     * Rmdir: non-empty subtrees are removed, while a missing directory is an
     * error like rclone's second purge of a non-root directory.
     *
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    public function purge(string $dir = ''): array
    {
        if (!$this->directPurge) {
            throw new \RuntimeException(self::ERROR_CANT_PURGE);
        }
        $this->throwConfiguredError($this->directPurgeError);

        $dir = $this->canonicalDirectoryPath($dir);
        if (!$this->directoryExists($dir)) {
            throw new \RuntimeException("Directory not found: {$dir}");
        }

        $objects = array_values(array_filter(
            $this->list($dir),
            static fn (ObjectInfo $object): bool => self::pathIsOrUnder($object->path, $dir),
        ));
        $directories = array_values(array_filter(
            $this->directories($dir),
            static fn (ObjectInfo $directory): bool => self::pathIsOrUnder($directory->path, $dir),
        ));

        foreach ($objects as $object) {
            $this->forgetListedObject($object);
        }

        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathDepth($b->path) <=> self::pathDepth($a->path)
                ?: $b->path <=> $a->path
                ?: ($b->providerKey ?? '') <=> ($a->providerKey ?? ''),
        );

        foreach ($directories as $directory) {
            $this->forgetListedDirectory($directory);
        }

        return [
            'objects' => $objects,
            'directories' => $directories,
        ];
    }

    /**
     * Model fs.CleanUpper. The feature empties provider trash or old versions
     * without touching the ordinary visible listing.
     *
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    public function cleanUp(): array
    {
        if (!$this->cleanUp) {
            throw new \RuntimeException(self::ERROR_CANT_CLEANUP);
        }
        $this->cleanUpCalls++;
        $this->throwConfiguredError($this->cleanUpError);

        $objects = $this->trashedObjects();
        $directories = $this->trashedDirectories();
        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathDepth($b->path) <=> self::pathDepth($a->path)
                ?: $b->path <=> $a->path,
        );

        $this->trashObjects = [];
        $this->trashDirectories = [];

        return [
            'objects' => $objects,
            'directories' => $directories,
        ];
    }

    public function cleanUpCalls(): int
    {
        return $this->cleanUpCalls;
    }

    /**
     * @return list<ObjectInfo>
     */
    public function trashedObjects(): array
    {
        $objects = [];
        foreach (array_keys($this->trashObjects) as $path) {
            $objects[] = $this->trashObjectInfo($path);
        }
        usort($objects, static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path);

        return $objects;
    }

    /**
     * @return list<ObjectInfo>
     */
    public function trashedDirectories(): array
    {
        $directories = [];
        foreach (array_keys($this->trashDirectories) as $path) {
            $directories[] = $this->trashDirectoryInfo($path);
        }
        usort($directories, static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path);

        return $directories;
    }

    /**
     * Model the optional rclone PublicLink provider feature. Links are stable
     * per provider path until explicitly unlinked, and can target files,
     * directories, or the provider root.
     */
    public function publicLink(string $path, int $expireSeconds = 0, bool $unlink = false): string
    {
        [$type, $remote] = $this->publicLinkTarget($path);
        $key = $type . ':' . $remote;

        if ($unlink) {
            unset($this->publicLinks[$key]);

            return '';
        }

        return $this->publicLinks[$key] ??= sprintf(
            'https://rclone.local/share/%s/%s%s',
            $type,
            substr(hash('sha256', $type . "\0" . $remote), 0, 16),
            $expireSeconds > 0 ? '?expires=' . $expireSeconds : '',
        );
    }

    public function openReader(string $path, int $offset = 0, ?int $length = null): object
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);
        $this->assertObjectReadable($path, $entry);
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

        $closeError = $entry['closeError'];
        $onClose = function () use ($path, $offset, $length): void {
            $this->closeLog[] = ['path' => $path, 'offset' => $offset, 'length' => $length];
        };

        return new class($bytes, $readError, $readErrorAfterBytes, $closeError, $onClose) {
            private int $offset = 0;
            private bool $closed = false;

            public function __construct(
                private readonly string $bytes,
                private readonly ?\Throwable $readError,
                private readonly ?int $readErrorAfterBytes,
                private readonly ?\Throwable $closeError,
                private readonly \Closure $onClose,
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
                if (!$this->closed) {
                    ($this->onClose)();
                    $this->closed = true;
                }
                if ($this->closeError !== null) {
                    throw $this->closeError;
                }
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

    /**
     * @return list<array{path: string, offset: int, length: ?int}>
     */
    public function closeLog(): array
    {
        return $this->closeLog;
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
            $entry['parentId'],
        );
    }

    /**
     * @return list<ObjectInfo>
     */
    public function list(string $prefix = ''): array
    {
        $prefix = $this->normalize($prefix);
        $items = [];
        foreach ($this->objects as $path => $entry) {
            if (!$this->isObjectListable($entry)) {
                continue;
            }
            if ($prefix === '' || $this->pathStartsWith($path, $prefix)) {
                $items[] = $this->info($path);
            }
        }
        foreach (array_keys($this->duplicateObjects) as $key) {
            $path = $this->duplicateObjects[$key]['path'];
            if (!$this->isObjectListable($this->duplicateObjects[$key]['entry'])) {
                continue;
            }
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
        if (!isset($this->directories[$path])) {
            $duplicateKey = $this->duplicateDirectoryKey($path);
            if ($duplicateKey !== null) {
                return $this->duplicateDirectoryInfo($duplicateKey);
            }
        }

        $entry = $this->directories[$path] ?? [
            'modTime' => null,
            'mimeType' => null,
            'metadata' => [],
            'id' => null,
            'parentId' => null,
        ];

        return new ObjectInfo(
            $path,
            -1,
            '',
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
            null,
            [],
            null,
            $entry['parentId'],
        );
    }

    /**
     * @return list<ObjectInfo>
     */
    public function directories(string $prefix = ''): array
    {
        $prefix = $this->normalize($prefix);
        $duplicatePaths = $this->duplicateDirectoryPaths();
        $items = [];
        foreach (array_keys($this->allDirectoryPaths()) as $path) {
            if ($path === '') {
                continue;
            }
            if (!isset($this->directories[$path]) && isset($duplicatePaths[$this->lookupPath($path)])) {
                continue;
            }
            if ($prefix === '' || $path === $prefix || $this->pathStartsWith($path, $prefix)) {
                $items[] = $this->directoryInfo($path);
            }
        }
        foreach (array_keys($this->duplicateDirectories) as $key) {
            $path = $this->duplicateDirectories[$key]['path'];
            if ($prefix === '' || $path === $prefix || $this->pathStartsWith($path, $prefix)) {
                $items[] = $this->duplicateDirectoryInfo($key);
            }
        }
        usort(
            $items,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        return $items;
    }

    /**
     * @param array{metadataSet?: array<string, scalar|null>} $options
     */
    public function copyTo(string $sourcePath, self $target, string $targetPath, array $options = []): ObjectInfo
    {
        $entry = $this->entry($sourcePath);
        if (isset($options['metadataSet'])) {
            $entry = $target->entryWithMetadataSet($entry, $options['metadataSet']);
        }

        return $target->putEntry($targetPath, $entry);
    }

    /**
     * Model fs.SetMetadataer on objects: metadata is replaced, and the common
     * mtime/content-type system keys update the visible object modtime/mimetype.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function setObjectMetadata(string $path, array $metadata): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entryWithMetadataSet($this->entry($path), $metadata);

        return $this->putEntry($path, $entry);
    }

    public function setObjectTier(string $path, string $tier): ObjectInfo
    {
        if (!$this->setTier) {
            throw new \RuntimeException('remote object does not implement SetTier');
        }

        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);
        $entry['tier'] = $tier;

        return $this->putEntry($path, $entry);
    }

    public function setListedObjectTier(ObjectInfo $info, string $tier): ObjectInfo
    {
        if (!$this->setTier) {
            throw new \RuntimeException('remote object does not implement SetTier');
        }

        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            $this->duplicateObjects[$info->providerKey]['entry']['tier'] = $tier;

            return $this->duplicateInfo($info->providerKey);
        }

        return $this->setObjectTier($info->path, $tier);
    }

    public function setListedObjectModTime(ObjectInfo $info, \DateTimeInterface|string|null $modTime): ObjectInfo
    {
        if ($info->providerKey !== null && isset($this->duplicateObjects[$info->providerKey])) {
            $path = $this->duplicateObjects[$info->providerKey]['path'];
            $this->throwSetModTimeError($path);
            $this->duplicateObjects[$info->providerKey]['entry']['modTime'] = $this->normalizeModTime($modTime);

            return $this->duplicateInfo($info->providerKey);
        }

        return $this->setModTime($info->path, $modTime);
    }

    public function getObjectTier(string $path): string
    {
        if (!$this->getTier) {
            throw new \RuntimeException('remote object does not implement GetTier');
        }

        return $this->info($path)->tier ?? '';
    }

    /**
     * @param array<string, scalar|null> $metadata
     */
    public function setDirectoryMetadata(string $path, array $metadata): ObjectInfo
    {
        $path = $this->canonicalDirectoryPath($path);
        if (!$this->directoryExists($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }

        $entry = $this->directoryEntryWithMetadataSet($this->listedDirectoryEntry($this->directoryInfo($path)), $metadata);

        return $this->putDirectoryEntry($path, $entry);
    }

    /**
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>} $options
     */
    public function updateObjectInfo(string $path, array $options): ObjectInfo
    {
        $path = $this->canonicalPath($path);
        $entry = $this->entry($path);

        if (array_key_exists('unknownSize', $options)) {
            $entry['unknownSize'] = (bool) $options['unknownSize'];
        }
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
        if (array_key_exists('parentId', $options)) {
            $entry['parentId'] = $options['parentId'];
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
        $this->entry($path);
        $this->throwSetModTimeError($path);
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
        $info = $directory instanceof ObjectInfo ? $directory : $this->directoryInfo($directory);
        if ($info->id !== null || $info->providerKey !== null || $info->parentId !== null) {
            return $this->providerDirectoryEntryCount($info);
        }

        $path = $this->canonicalDirectoryPath($info->path);
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

    /**
     * Merge duplicate provider directory entries that share the same remote
     * path but have distinct IDs. This is the shape exposed by providers such
     * as Drive during rclone's dedupe duplicate-directory pre-pass.
     *
     * @return array{moved: list<ObjectInfo>, removed: list<ObjectInfo>}
     */
    private function mergeSamePathDirectory(ObjectInfo $source, ObjectInfo $target): array
    {
        $subtree = $this->providerDirectorySubtree($source);
        $sourceId = $this->providerTreeId($source);
        $targetRawId = $target->id ?? $target->providerKey ?? $target->path;
        $moved = [];
        $removed = [];

        usort(
            $subtree['directories'],
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathDepth($a->path) <=> self::pathDepth($b->path)
                ?: $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        foreach ($subtree['directories'] as $directory) {
            if ($this->sameDirectoryEntry($directory, $source)) {
                continue;
            }

            $entry = $this->listedDirectoryEntry($directory);
            if ($directory->parentId !== null && $this->providerTreeIdFromRaw($directory->parentId) === $sourceId) {
                $entry['parentId'] = $targetRawId;
            }

            $this->forgetListedDirectory($directory);
            $moved[] = $this->hasConcreteDirectory($directory->path)
                ? $this->putDuplicateDirectoryEntry($directory->path, $entry)
                : $this->putDirectoryEntry($directory->path, $entry);
        }

        usort(
            $subtree['objects'],
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        foreach ($subtree['objects'] as $object) {
            $entry = $this->listedObjectEntry($object);
            if ($object->parentId !== null && $this->providerTreeIdFromRaw($object->parentId) === $sourceId) {
                $entry['parentId'] = $targetRawId;
            }

            $this->forgetListedObject($object);
            $moved[] = $this->pathExists($object->path)
                ? $this->putDuplicateEntry($object->path, $entry)
                : $this->putEntry($object->path, $entry);
        }

        $removed[] = $source;
        $this->forgetListedDirectory($source);

        return [
            'moved' => $moved,
            'removed' => $removed,
        ];
    }

    private function providerDirectoryEntryCount(ObjectInfo $directory): int
    {
        $subtree = $this->providerDirectorySubtree($directory);

        return count($subtree['directories']) - 1 + count($subtree['objects']);
    }

    /**
     * @return array{ids: array<string, true>, directories: list<ObjectInfo>, objects: list<ObjectInfo>}
     */
    private function providerDirectorySubtree(ObjectInfo $directory): array
    {
        $rootId = $this->providerTreeId($directory);
        $ids = [$rootId => true];
        $directories = $this->directories();

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($directories as $candidate) {
                $candidateId = $this->providerTreeId($candidate);
                if (isset($ids[$candidateId])) {
                    continue;
                }
                if (isset($ids[$this->providerParentTreeId($candidate)])) {
                    $ids[$candidateId] = true;
                    $changed = true;
                }
            }
        }

        $subtreeDirectories = [];
        foreach ($directories as $candidate) {
            if (isset($ids[$this->providerTreeId($candidate)])) {
                $subtreeDirectories[] = $candidate;
            }
        }

        $subtreeObjects = [];
        foreach ($this->list() as $object) {
            if (isset($ids[$this->providerParentTreeId($object)])) {
                $subtreeObjects[] = $object;
            }
        }

        return [
            'ids' => $ids,
            'directories' => $subtreeDirectories,
            'objects' => $subtreeObjects,
        ];
    }

    private function normalize(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    /**
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}
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
     * @param array{unknownSize?: bool, modTime?: \DateTimeInterface|string|null, mimeType?: string|null, metadata?: array<string, string>, id?: string|null, parentId?: string|null, tier?: string|null, hashes?: array<string, string>, openError?: \Throwable|string|null, readError?: \Throwable|string|null, readErrorAfterBytes?: int|null, readBreaks?: list<int>, closeError?: \Throwable|string|null} $options
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}
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
            'parentId' => $options['parentId'] ?? null,
            'tier' => $options['tier'] ?? null,
            'hashes' => $this->normalizeHashes($options['hashes'] ?? []),
            'openError' => $this->normalizeThrowable($options['openError'] ?? null),
            'readError' => $this->normalizeThrowable($options['readError'] ?? null),
            'readErrorAfterBytes' => array_key_exists('readBreaks', $options) && !array_key_exists('readErrorAfterBytes', $options)
                ? null
                : $this->normalizeReadErrorAfterBytes($options),
            'readBreaks' => array_map(static fn (int $break): int => max(0, $break), $options['readBreaks'] ?? []),
            'closeError' => $this->normalizeThrowable($options['closeError'] ?? null),
        ];
    }

    /**
     * @param array{metadataSet?: array<string, scalar|null>} $options
     */
    private function applyMetadataSet(string $path, array $options): ObjectInfo
    {
        if (!isset($options['metadataSet'])) {
            return $this->info($path);
        }

        return $this->setObjectMetadata($path, $options['metadataSet']);
    }

    /**
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable} $entry
     * @param array<string, scalar|null> $metadata
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}
     */
    private function entryWithMetadataSet(array $entry, array $metadata): array
    {
        $metadata = $this->normalizeMetadata($metadata);
        $entry['metadata'] = $metadata;
        if (($metadata['mtime'] ?? '') !== '') {
            $entry['modTime'] = $this->normalizeModTime($metadata['mtime']);
        }
        if (($metadata['content-type'] ?? '') !== '') {
            $entry['mimeType'] = $metadata['content-type'];
        }

        return $entry;
    }

    /**
     * @param array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string} $entry
     * @param array<string, scalar|null> $metadata
     * @return array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string}
     */
    private function directoryEntryWithMetadataSet(array $entry, array $metadata): array
    {
        $metadata = $this->normalizeMetadata($metadata);
        $entry['metadata'] = $metadata;
        if (($metadata['mtime'] ?? '') !== '') {
            $entry['modTime'] = $this->normalizeModTime($metadata['mtime']);
        }
        if (($metadata['content-type'] ?? '') !== '') {
            $entry['mimeType'] = $metadata['content-type'];
        }

        return $entry;
    }

    /**
     * @param array<string, scalar|null> $metadata
     * @return array<string, string>
     */
    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];
        foreach ($metadata as $key => $value) {
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
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
            $entry['parentId'],
        );
    }

    private function trashObjectInfo(string $path): ObjectInfo
    {
        if (!isset($this->trashObjects[$path])) {
            throw new \RuntimeException("Trash object not found: {$path}");
        }

        $entry = $this->trashObjects[$path];
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
            'trash:' . $path,
            $entry['parentId'],
        );
    }

    private function trashDirectoryInfo(string $path): ObjectInfo
    {
        if (!isset($this->trashDirectories[$path])) {
            throw new \RuntimeException("Trash directory not found: {$path}");
        }

        $entry = $this->trashDirectories[$path];

        return new ObjectInfo(
            $path,
            -1,
            '',
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
            null,
            [],
            'trash-dir:' . $path,
            $entry['parentId'],
        );
    }

    private function duplicateDirectoryInfo(string $key): ObjectInfo
    {
        if (!isset($this->duplicateDirectories[$key])) {
            throw new \RuntimeException("Directory not found: {$key}");
        }

        $duplicate = $this->duplicateDirectories[$key];
        $entry = $duplicate['entry'];

        return new ObjectInfo(
            $duplicate['path'],
            -1,
            '',
            $entry['modTime'],
            $entry['mimeType'],
            $entry['metadata'],
            $entry['id'],
            null,
            [],
            $key,
            $entry['parentId'],
        );
    }

    private function listedDirectoryInfo(string|ObjectInfo $directory): ObjectInfo
    {
        if ($directory instanceof ObjectInfo) {
            if ($directory->providerKey !== null && isset($this->duplicateDirectories[$directory->providerKey])) {
                return $this->duplicateDirectoryInfo($directory->providerKey);
            }

            return $this->directoryInfo($directory->path);
        }

        return $this->directoryInfo($directory);
    }

    /**
     * @return array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string}
     */
    private function listedDirectoryEntry(ObjectInfo $info): array
    {
        if ($info->providerKey !== null && isset($this->duplicateDirectories[$info->providerKey])) {
            return $this->duplicateDirectories[$info->providerKey]['entry'];
        }

        $path = $this->canonicalDirectoryPath($info->path);

        return $this->directories[$path] ?? [
            'modTime' => $info->modTime,
            'mimeType' => $info->mimeType,
            'metadata' => $info->metadata,
            'id' => $info->id,
            'parentId' => $info->parentId,
        ];
    }

    private function forgetListedDirectory(ObjectInfo $info): void
    {
        if ($info->providerKey !== null && isset($this->duplicateDirectories[$info->providerKey])) {
            unset($this->duplicateDirectories[$info->providerKey]);

            return;
        }

        $path = $this->canonicalDirectoryPath($info->path);
        if (isset($this->directories[$path])) {
            $this->forgetDirectory($path);
        }
    }

    private function sameDirectoryEntry(ObjectInfo $left, ObjectInfo $right): bool
    {
        if ($left->id !== null || $right->id !== null) {
            return $left->id !== null && $left->id === $right->id;
        }
        if ($left->providerKey !== null || $right->providerKey !== null) {
            return $left->providerKey !== null && $left->providerKey === $right->providerKey;
        }

        return $this->sameProviderPath($left->path, $right->path);
    }

    /**
     * @param array{metadata: array<string, string>} $entry
     */
    private function isObjectListable(array $entry): bool
    {
        return ($entry['metadata']['dropbox_export_type'] ?? '') !== 'hidden';
    }

    /**
     * @param array{metadata: array<string, string>} $entry
     */
    private function assertObjectReadable(string $path, array $entry): void
    {
        if (($entry['metadata']['dropbox_export_type'] ?? '') === 'list-only') {
            throw new \RuntimeException("Object not found: {$path}");
        }
        if (($entry['metadata']['package-type'] ?? '') === 'oneNote') {
            throw new \RuntimeException("can't open a OneNote file");
        }
    }

    /**
     * @param array{metadata: array<string, string>} $entry
     */
    private function assertObjectWritable(string $path, array $entry): void
    {
        if (($entry['metadata']['package-type'] ?? '') === 'oneNote') {
            throw new \RuntimeException("can't upload content to a OneNote file");
        }
    }

    private function hasConcreteDirectory(string $path): bool
    {
        $path = $this->canonicalDirectoryPath($path);

        return isset($this->directories[$path]) || $this->duplicateDirectoryKey($path) !== null;
    }

    private function providerTreeId(ObjectInfo $info): string
    {
        if ($info->id !== null && $info->id !== '') {
            return $this->providerTreeIdFromRaw($info->id);
        }
        if ($info->providerKey !== null && $info->providerKey !== '') {
            return 'provider-key:' . $info->providerKey;
        }

        return 'path:' . $info->path;
    }

    private function providerParentTreeId(ObjectInfo $info): string
    {
        if ($info->parentId !== null && $info->parentId !== '') {
            return $this->providerTreeIdFromRaw($info->parentId);
        }

        return 'path:' . self::parentPath($info->path);
    }

    private function providerTreeIdFromRaw(string $id): string
    {
        return 'id:' . $id;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function publicLinkTarget(string $path): array
    {
        $path = $this->normalize($path);
        try {
            $info = $this->info($path);

            return ['object', $info->path];
        } catch (\RuntimeException) {
            $directory = $this->canonicalDirectoryPath($path);
            if ($this->directoryExists($directory)) {
                return ['directory', $directory];
            }
        }

        throw new \RuntimeException("Object or directory not found: {$path}");
    }

    /**
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable} $entry
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
     * @param array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string} $entry
     */
    private function putDuplicateDirectoryEntry(string $path, array $entry): ObjectInfo
    {
        $key = 'unchecked-dir:' . sprintf('%08d', ++$this->duplicateDirectorySequence);
        $this->duplicateDirectories[$key] = [
            'path' => $this->normalize($path),
            'entry' => $entry,
        ];

        return $this->duplicateDirectoryInfo($key);
    }

    /**
     * @return array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable}
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
     * @param array{bytes: string, unknownSize: bool, modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string, tier: ?string, hashes: array<string, string>, openError: ?\Throwable, readError: ?\Throwable, readErrorAfterBytes: ?int, readBreaks: list<int>, closeError: ?\Throwable} $entry
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
     * @param array{modTime: ?string, mimeType: ?string, metadata: array<string, string>, id: ?string, parentId: ?string} $entry
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

    private function throwDeleteError(string $path): void
    {
        $path = $this->canonicalPath($path);
        if (isset($this->deleteErrors[$path])) {
            throw $this->deleteErrors[$path];
        }
    }

    private function throwSetModTimeError(string $path): void
    {
        $path = $this->canonicalPath($path);
        if (isset($this->setModTimeErrors[$path])) {
            throw $this->setModTimeErrors[$path];
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

    public static function isCantPurgeException(\Throwable $throwable): bool
    {
        return $throwable->getMessage() === self::ERROR_CANT_PURGE;
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
        foreach ($this->duplicateDirectories as $duplicate) {
            if ($this->lookupPath($duplicate['path']) === $lookup) {
                return $duplicate['path'];
            }
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

        return $path === '' || isset($this->allDirectoryPaths()[$path]) || $this->duplicateDirectoryKey($path) !== null;
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

        foreach ($this->duplicateDirectories as $duplicate) {
            $this->addParentDirectories($dirs, $duplicate['path']);
        }

        foreach (array_keys($this->directories) as $path) {
            $dirs[$path] = true;
            $this->addParentDirectories($dirs, $path);
        }

        return $dirs;
    }

    /**
     * @return array<string, true>
     */
    private function duplicateDirectoryPaths(): array
    {
        $paths = [];
        foreach ($this->duplicateDirectories as $duplicate) {
            $paths[$this->lookupPath($duplicate['path'])] = true;
        }

        return $paths;
    }

    private function duplicateDirectoryKey(string $path): ?string
    {
        foreach ($this->duplicateDirectories as $key => $duplicate) {
            if ($this->sameProviderPath($duplicate['path'], $path)) {
                return $key;
            }
        }

        return null;
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
        return $prefix === '' || $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function relativeDepth(string $path, string $prefix): int
    {
        if ($path === $prefix) {
            return 0;
        }
        if ($prefix !== '') {
            $path = substr($path, strlen($prefix) + 1);
        }

        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private static function pathDepth(string $path): int
    {
        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private static function parentPath(string $path): string
    {
        $parent = dirname($path);

        return $parent === '.' ? '' : $parent;
    }

    private static function replacePathPrefix(string $path, string $sourcePrefix, string $targetPrefix): string
    {
        if ($path === $sourcePrefix) {
            return $targetPrefix;
        }

        return $targetPrefix . substr($path, strlen($sourcePrefix));
    }
}
