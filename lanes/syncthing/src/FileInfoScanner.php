<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfoScanner
{
    private const DEFAULT_TEMP_LIFETIME_SECONDS = 86400;
    public const WALK_FAILURE_EVENT = 'Failure';
    public const WALK_FAILURE_EVENT_DESCRIPTION = 'Unexpected error while walking the filesystem during scan';

    private string $rootPath;
    private ?\Closure $xattrFilter;
    private ?\Closure $xattrLister;
    private ?\Closure $xattrGetter;
    private ?\Closure $directoryLister;
    private BlockList $blockList;

    /**
     * @param null|callable(string): bool $xattrFilter
     * @param null|callable(string): list<string> $xattrLister
     * @param null|callable(string, string): (?string) $xattrGetter
     * @param null|callable(string): list<string> $directoryLister
     */
    public function __construct(
        string $rootPath,
        private readonly bool $scanOwnership = false,
        private readonly bool $scanXattrs = false,
        private readonly bool $ignorePerms = false,
        ?callable $xattrFilter = null,
        private readonly int $maxSingleXattrSize = 0,
        private readonly int $maxTotalXattrSize = 0,
        ?callable $xattrLister = null,
        ?callable $xattrGetter = null,
        ?BlockList $blockList = null,
        private readonly int $localFlags = 0,
        private readonly int $modTimeWindowNs = 0,
        private readonly bool $autoNormalize = false,
        ?string $platformFamily = null,
        private readonly int $tempLifetimeSeconds = self::DEFAULT_TEMP_LIFETIME_SECONDS,
        ?callable $directoryLister = null,
    ) {
        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('FileInfo scanner root path must be an existing directory');
        }
        if ($this->maxSingleXattrSize < 0 || $this->maxTotalXattrSize < 0) {
            throw new \InvalidArgumentException('Xattr size limits must not be negative');
        }
        if ($this->localFlags < 0 || $this->modTimeWindowNs < 0) {
            throw new \InvalidArgumentException('Scanner local flags and modification time window must not be negative');
        }
        if ($this->tempLifetimeSeconds < 0) {
            throw new \InvalidArgumentException('Temporary file lifetime must not be negative');
        }

        $this->rootPath = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $this->xattrFilter = $xattrFilter === null ? null : \Closure::fromCallable($xattrFilter);
        $this->xattrLister = $xattrLister === null ? null : \Closure::fromCallable($xattrLister);
        $this->xattrGetter = $xattrGetter === null ? null : \Closure::fromCallable($xattrGetter);
        $this->directoryLister = $directoryLister === null ? null : \Closure::fromCallable($directoryLister);
        $this->blockList = $blockList ?? new BlockList();
        $this->platformFamily = strtoupper($platformFamily ?? PHP_OS_FAMILY);
    }

    private readonly string $platformFamily;

    public function scan(string $name, bool $hashBlocks = false, ?int $blockSize = null, ?FileInfo $currentFile = null): FileInfo
    {
        $info = $this->scanCandidate($name, $hashBlocks, $blockSize, $currentFile, false);
        if ($info === null) {
            throw new \LogicException('Unchanged scanner candidates are disabled for direct scan');
        }

        return $info;
    }

    public function scanIfChanged(string $name, bool $hashBlocks = false, ?int $blockSize = null, ?FileInfo $currentFile = null): ?FileInfo
    {
        return $this->scanCandidate($name, $hashBlocks, $blockSize, $currentFile, true);
    }

    private function scanCandidate(
        string $name,
        bool $hashBlocks,
        ?int $blockSize,
        ?FileInfo $currentFile,
        bool $skipUnchanged,
    ): ?FileInfo {
        $this->assertScanInputs($name, $blockSize, $currentFile);

        $path = $this->absolutePath($name);
        $stat = @lstat($path);
        if (!is_array($stat)) {
            throw new \RuntimeException('stat failed for ' . $name);
        }

        $platform = $this->platformData($name, $path, $stat);

        if (is_link($path)) {
            $target = readlink($path);
            if (!is_string($target)) {
                throw new \RuntimeException('readlink failed for ' . $name);
            }

            $info = new FileInfo(
                name: $name,
                type: FileInfo::TYPE_SYMLINK,
                noPermissions: true,
                localFlags: $this->localFlags,
                previousBlocksHash: $currentFile?->blocksHash ?? '',
                symlinkTarget: $target,
                unixOwnerName: $platform['unixOwnerName'],
                unixGroupName: $platform['unixGroupName'],
                unixUid: $platform['unixUid'],
                unixGid: $platform['unixGid'],
                xattrs: $platform['xattrs'],
            );

            return $skipUnchanged && $currentFile !== null && $this->isUnchanged($currentFile, $info) ? null : $info;
        }

        $permissions = (int) $stat['mode'] & 0777;
        $modifiedS = (int) $stat['mtime'];

        if (is_dir($path)) {
            $info = new FileInfo(
                name: $name,
                modifiedS: $modifiedS,
                type: FileInfo::TYPE_DIRECTORY,
                localFlags: $this->localFlags,
                permissions: $permissions,
                noPermissions: $this->ignorePerms,
                previousBlocksHash: $currentFile?->blocksHash ?? '',
                unixOwnerName: $platform['unixOwnerName'],
                unixGroupName: $platform['unixGroupName'],
                unixUid: $platform['unixUid'],
                unixGid: $platform['unixGid'],
                xattrs: $platform['xattrs'],
            );

            return $skipUnchanged && $currentFile !== null && $this->isUnchanged($currentFile, $info) ? null : $info;
        }

        if (!is_file($path)) {
            throw new \UnexpectedValueException('unsupported filesystem entry type for ' . $name);
        }

        $size = (int) $stat['size'];
        if ($currentFile !== null && $currentFile->type === FileInfo::TYPE_FILE && $this->isWindowsPlatform()) {
            $permissions |= ($currentFile->permissions & 0111);
        }

        $currentBlockSize = $currentFile !== null && $currentFile->type === FileInfo::TYPE_FILE
            ? $currentFile->blockSize()
            : null;
        $rawBlockSize = $blockSize ?? BlockList::blockSizeForFileSize($size, $currentBlockSize);
        $previousBlocksHash = $currentFile?->blocksHash ?? '';
        $info = new FileInfo(
            name: $name,
            modifiedS: $modifiedS,
            size: $size,
            type: FileInfo::TYPE_FILE,
            localFlags: $this->localFlags,
            permissions: $permissions,
            noPermissions: $this->ignorePerms,
            rawBlockSize: $rawBlockSize,
            previousBlocksHash: $previousBlocksHash,
            unixOwnerName: $platform['unixOwnerName'],
            unixGroupName: $platform['unixGroupName'],
            unixUid: $platform['unixUid'],
            unixGid: $platform['unixGid'],
            xattrs: $platform['xattrs'],
        );
        if ($skipUnchanged && $currentFile !== null && $this->isUnchanged($currentFile, $info)) {
            return null;
        }

        return $hashBlocks ? $this->hashScannedFile($info) : $info;
    }

    private function isWindowsPlatform(): bool
    {
        return $this->platformFamily === 'WINDOWS';
    }

    private function assertScanInputs(string $name, ?int $blockSize, ?FileInfo $currentFile): void
    {
        ProtocolValidation::checkFilename($name);
        if ($blockSize !== null && $blockSize <= 0) {
            throw new \InvalidArgumentException('Block size must be positive');
        }
        if ($currentFile !== null && $currentFile->name !== $name) {
            throw new \InvalidArgumentException('Current FileInfo must describe the scanned path');
        }
    }

    private function isUnchanged(FileInfo $currentFile, FileInfo $scannedFile): bool
    {
        return $currentFile->isEquivalent($scannedFile, new FileInfoComparison(
            modTimeWindowNs: $this->modTimeWindowNs,
            ignorePerms: $this->ignorePerms,
            ignoreBlocks: true,
            ignoreFlags: $this->localFlags,
            ignoreOwnership: !$this->scanOwnership,
            ignoreXattrs: !$this->scanXattrs,
        ));
    }

    /**
     * @param list<string> $subs
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     * @return list<FileInfo>
     */
    public function walk(
        array $subs = [],
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        iterable $currentFiles = [],
        ?callable $progressLogger = null,
        string $folder = '',
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
    ): array {
        $errorLoggerClosure = $errorLogger === null ? null : \Closure::fromCallable($errorLogger);
        $shouldCancelClosure = $shouldCancel === null ? null : \Closure::fromCallable($shouldCancel);
        $failureLoggerClosure = $failureLogger === null ? null : \Closure::fromCallable($failureLogger);

        if ($hashBlocks && $progressLogger !== null) {
            return $this->walkWithHashProgress(
                $subs,
                $ignoreMatcher,
                $blockSize,
                $currentFiles,
                \Closure::fromCallable($progressLogger),
                $folder,
                $errorLoggerClosure,
                $shouldCancelClosure,
                $failureLoggerClosure,
            );
        }

        $results = [];
        $seen = [];
        $subs = $subs === [] ? [''] : $subs;
        $currentByName = $this->currentFilesByName($currentFiles);
        $scanNow = time();

        foreach ($subs as $sub) {
            if (!is_string($sub)) {
                throw new \InvalidArgumentException('Scanner walk subs must be strings');
            }

            $diagnostic = $this->diagnoseSubWalk($sub);
            $name = $diagnostic->sub;
            if (self::isCancelled($shouldCancelClosure, $name)) {
                break;
            }

            if (!$diagnostic->shouldWalk()) {
                continue;
            }

            $path = $name === '' ? $this->rootPath : $this->absolutePath($name);

            if ($name === '') {
                try {
                    $continue = $this->walkDirectoryChildren('', $path, $ignoreMatcher, $hashBlocks, $blockSize, $currentByName, null, $seen, $results, $errorLoggerClosure, $shouldCancelClosure, $failureLoggerClosure, $scanNow);
                } catch (\Throwable $throwable) {
                    self::reportWalkFailure($failureLoggerClosure, '', $throwable);
                    throw $throwable;
                }
                if (!$continue) {
                    break;
                }
                continue;
            }

            try {
                $continue = $this->walkPath($name, $ignoreMatcher, $hashBlocks, $blockSize, $currentByName, null, $seen, $results, $errorLoggerClosure, $shouldCancelClosure, $failureLoggerClosure, $scanNow);
            } catch (\Throwable $throwable) {
                self::reportWalkFailure($failureLoggerClosure, $name, $throwable);
                throw $throwable;
            }
            if (!$continue) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param list<string> $subs
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function walkWithCheckpoint(
        array $subs = [],
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        iterable $currentFiles = [],
        ?callable $progressLogger = null,
        string $folder = '',
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
        ?FolderScanEventCollector $eventCollector = null,
    ): FileInfoScanResult {
        $cancelled = false;
        $cancelledAt = null;
        $userShouldCancel = $shouldCancel === null ? null : \Closure::fromCallable($shouldCancel);
        $checkpointSubs = self::checkpointSubs($subs);
        $progressLogger = self::chainProgressLogger($eventCollector, $progressLogger);
        $errorLogger = self::chainErrorLogger($eventCollector, $errorLogger);
        $failureLogger = self::chainFailureLogger($eventCollector, $failureLogger);

        $trackingCancel = $userShouldCancel === null
            ? null
            : static function (?string $path) use (&$cancelled, &$cancelledAt, $userShouldCancel): bool {
                $isCancelled = (bool) $userShouldCancel($path);
                if ($isCancelled && !$cancelled) {
                    $cancelled = true;
                    $cancelledAt = $path;
                }

                return $isCancelled;
            };

        $files = $this->walk(
            $subs,
            $ignoreMatcher,
            $hashBlocks,
            $blockSize,
            $currentFiles,
            $progressLogger,
            $folder,
            $errorLogger,
            $trackingCancel,
            $failureLogger,
        );

        return new FileInfoScanResult($files, $cancelled, $cancelledAt, $checkpointSubs, $eventCollector);
    }

    /**
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @return null|\Closure(FolderScanProgress): void
     */
    private static function chainProgressLogger(?FolderScanEventCollector $eventCollector, ?callable $progressLogger): ?\Closure
    {
        if ($eventCollector === null) {
            return $progressLogger === null ? null : \Closure::fromCallable($progressLogger);
        }

        $userLogger = $progressLogger === null ? null : \Closure::fromCallable($progressLogger);

        return static function (FolderScanProgress $progress) use ($eventCollector, $userLogger): void {
            $eventCollector->recordProgress($progress);
            if ($userLogger !== null) {
                $userLogger($progress);
            }
        };
    }

    /**
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @return null|\Closure(string, \Throwable, string): void
     */
    private static function chainErrorLogger(?FolderScanEventCollector $eventCollector, ?callable $errorLogger): ?\Closure
    {
        if ($eventCollector === null) {
            return $errorLogger === null ? null : \Closure::fromCallable($errorLogger);
        }

        $userLogger = $errorLogger === null ? null : \Closure::fromCallable($errorLogger);

        return static function (string $path, \Throwable $error, string $phase) use ($eventCollector, $userLogger): void {
            $eventCollector->recordScanError($path, $error, $phase);
            if ($userLogger !== null) {
                $userLogger($path, $error, $phase);
            }
        };
    }

    /**
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     * @return null|\Closure(string, array{description:string, sub:string, error:string}): void
     */
    private static function chainFailureLogger(?FolderScanEventCollector $eventCollector, ?callable $failureLogger): ?\Closure
    {
        if ($eventCollector === null) {
            return $failureLogger === null ? null : \Closure::fromCallable($failureLogger);
        }

        $userLogger = $failureLogger === null ? null : \Closure::fromCallable($failureLogger);

        return static function (string $type, array $data) use ($eventCollector, $userLogger): void {
            $eventCollector->recordFailure($type, $data);
            if ($userLogger !== null) {
                $userLogger($type, $data);
            }
        };
    }

    /**
     * @param list<string> $subs
     * @return list<FileInfo>
     */
    private function walkWithHashProgress(
        array $subs,
        ?IgnoreMatcher $ignoreMatcher,
        ?int $blockSize,
        iterable $currentFiles,
        \Closure $progressLogger,
        string $folder,
        ?\Closure $errorLogger,
        ?\Closure $shouldCancel,
        ?\Closure $failureLogger,
    ): array {
        $results = $this->walk(
            $subs,
            $ignoreMatcher,
            false,
            $blockSize,
            $currentFiles,
            null,
            '',
            $errorLogger,
            $shouldCancel,
            $failureLogger,
        );
        $fileIndexes = [];
        $total = 1;
        foreach ($results as $index => $file) {
            if ($file->type !== FileInfo::TYPE_FILE) {
                continue;
            }

            $fileIndexes[] = $index;
            $total += $file->size;
        }

        if ($fileIndexes === []) {
            return $results;
        }

        $current = 0;
        $hashedResults = [];
        $fileIndexMap = array_fill_keys($fileIndexes, true);
        foreach ($results as $index => $file) {
            if (!isset($fileIndexMap[$index])) {
                $hashedResults[] = $file;
                continue;
            }

            if (self::isCancelled($shouldCancel, $file->name)) {
                break;
            }

            try {
                $hashed = $this->hashScannedFile($file);
            } catch (\Throwable $throwable) {
                self::reportWalkError($errorLogger, $file->name, $throwable, 'hashing');
                $progressLogger(new FolderScanProgress($folder, $current, $total));
                continue;
            }

            $hashedResults[] = $hashed;
            $current += $hashed->size;
            $progressLogger(new FolderScanProgress($folder, $current, $total));
        }

        return $hashedResults;
    }

    private static function isCancelled(?\Closure $shouldCancel, ?string $name): bool
    {
        return $shouldCancel !== null && (bool) $shouldCancel($name);
    }

    private static function reportWalkError(?\Closure $errorLogger, string $name, \Throwable $throwable, string $phase): void
    {
        if ($errorLogger === null) {
            throw $throwable;
        }

        $errorLogger($name, $throwable, $phase);
    }

    private static function reportWalkFailure(?\Closure $failureLogger, string $sub, \Throwable $throwable): void
    {
        if ($failureLogger === null || !self::isWarnableWalkFailure($throwable)) {
            return;
        }

        $failureLogger(self::WALK_FAILURE_EVENT, [
            'description' => self::WALK_FAILURE_EVENT_DESCRIPTION,
            'sub' => $sub === '' ? '.' : $sub,
            'error' => $throwable->getMessage(),
        ]);
    }

    public static function isWarnableWalkFailure(?\Throwable $throwable): bool
    {
        if ($throwable === null) {
            return false;
        }

        return !in_array($throwable->getMessage(), ['context canceled', 'context deadline exceeded'], true);
    }

    private static function phaseForThrowable(\Throwable $throwable): string
    {
        return str_starts_with($throwable->getMessage(), 'normalizing path:') ? 'normalizing path' : 'scan';
    }

    private function hashScannedFile(FileInfo $info): FileInfo
    {
        if ($info->type !== FileInfo::TYPE_FILE) {
            throw new \LogicException('Only regular files can be block hashed');
        }

        $bytes = @file_get_contents($this->absolutePath($info->name));
        if (!is_string($bytes)) {
            throw new \RuntimeException('read failed for ' . $info->name);
        }

        $blocks = $this->blockList->fromBytes($bytes, $info->rawBlockSize);
        $size = 0;
        foreach ($blocks as $block) {
            $size += $block->size;
        }

        return new FileInfo(
            name: $info->name,
            modifiedS: $info->modifiedS,
            modifiedNs: $info->modifiedNs,
            version: $info->version,
            deleted: $info->deleted,
            localFlags: $info->localFlags,
            size: $size,
            blocksHash: $this->blockList->hashBlocks($blocks),
            previousBlocksHash: $info->previousBlocksHash,
            type: $info->type,
            permissions: $info->permissions,
            noPermissions: $info->noPermissions,
            rawBlockSize: $info->rawBlockSize,
            sequence: $info->sequence,
            symlinkTarget: $info->symlinkTarget,
            blocks: $blocks,
            unixOwnerName: $info->unixOwnerName,
            unixGroupName: $info->unixGroupName,
            unixUid: $info->unixUid,
            unixGid: $info->unixGid,
            modifiedBy: $info->modifiedBy,
            encryptedPayload: $info->encryptedPayload,
            xattrs: $info->xattrs,
        );
    }

    /**
     * @param array<string, mixed> $stat
     * @return array{unixOwnerName:?string, unixGroupName:?string, unixUid:?int, unixGid:?int, xattrs:array<string, string>}
     */
    private function platformData(string $name, string $path, array $stat): array
    {
        $owner = [
            'unixOwnerName' => null,
            'unixGroupName' => null,
            'unixUid' => null,
            'unixGid' => null,
        ];
        if ($this->scanOwnership) {
            $uid = (int) ($stat['uid'] ?? 0);
            $gid = (int) ($stat['gid'] ?? 0);
            $owner = [
                'unixOwnerName' => $this->ownerName($uid),
                'unixGroupName' => $this->groupName($gid),
                'unixUid' => $uid,
                'unixGid' => $gid,
            ];
        }

        return $owner + [
            'xattrs' => $this->scanXattrs ? $this->xattrs($name, $path) : [],
        ];
    }

    private function ownerName(int $uid): ?string
    {
        if (function_exists('posix_getpwuid')) {
            $user = @posix_getpwuid($uid);
            if (is_array($user) && isset($user['name'])) {
                return (string) $user['name'];
            }
        }

        return $uid === 0 ? 'root' : null;
    }

    private function groupName(int $gid): ?string
    {
        if (function_exists('posix_getgrgid')) {
            $group = @posix_getgrgid($gid);
            if (is_array($group) && isset($group['name'])) {
                return (string) $group['name'];
            }
        }

        return $gid === 0 ? 'root' : null;
    }

    /**
     * @return array<string, string>
     */
    private function xattrs(string $name, string $path): array
    {
        try {
            $names = $this->listXattrs($path);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('reading platform data: get xattr ' . $name . ': ' . $throwable->getMessage(), 0, $throwable);
        }

        $xattrs = [];
        $totalSize = 0;
        foreach ($names as $xattrName) {
            if (!is_string($xattrName) || $xattrName === '' || !$this->xattrPermitted($xattrName)) {
                continue;
            }

            try {
                $value = $this->getXattr($path, $xattrName);
            } catch (\Throwable $throwable) {
                throw new \RuntimeException('reading platform data: get xattr ' . $name . ': ' . $throwable->getMessage(), 0, $throwable);
            }
            if ($value === null) {
                continue;
            }

            $entrySize = strlen($xattrName) + strlen($value);
            if ($this->maxSingleXattrSize > 0 && $entrySize > $this->maxSingleXattrSize) {
                continue;
            }

            $totalSize += $entrySize;
            if ($this->maxTotalXattrSize > 0 && $totalSize > $this->maxTotalXattrSize) {
                continue;
            }

            $xattrs[$xattrName] = $value;
        }

        ksort($xattrs);
        return $xattrs;
    }

    /**
     * @return list<string>
     */
    private function listXattrs(string $path): array
    {
        if ($this->xattrLister !== null) {
            $names = ($this->xattrLister)($path);
            if (!is_array($names)) {
                throw new \UnexpectedValueException('xattr lister must return an array');
            }

            sort($names, SORT_STRING);
            return array_values($names);
        }

        if (!function_exists('xattr_list')) {
            return [];
        }

        $names = @xattr_list($path);
        if (!is_array($names)) {
            return [];
        }

        sort($names, SORT_STRING);
        return array_values($names);
    }

    private function getXattr(string $path, string $name): ?string
    {
        if ($this->xattrGetter !== null) {
            $value = ($this->xattrGetter)($path, $name);
            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException('xattr getter must return a string or null');
            }

            return $value;
        }

        if (!function_exists('xattr_get')) {
            return null;
        }

        $value = @xattr_get($path, $name);
        return is_string($value) ? $value : null;
    }

    private function xattrPermitted(string $name): bool
    {
        return $this->xattrFilter === null || (bool) ($this->xattrFilter)($name);
    }

    /**
     * @param array<string, true> $seen
     * @param list<FileInfo> $results
     */
    private function walkPath(
        string $name,
        ?IgnoreMatcher $ignoreMatcher,
        bool $hashBlocks,
        ?int $blockSize,
        array $currentByName,
        ?string $ignoredParent,
        array &$seen,
        array &$results,
        ?\Closure $errorLogger,
        ?\Closure $shouldCancel,
        ?\Closure $failureLogger,
        int $scanNow,
    ): bool {
        if (self::isCancelled($shouldCancel, $name)) {
            return false;
        }

        $path = $this->absolutePath($name);
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }

        try {
            $this->assertValidUtf8Name($name);

            if ($this->handleTemporaryPath($name, $path, $scanNow)) {
                return true;
            }

            if (RequestServer::isInternalName($name)) {
                return true;
            }

            $normalizedName = $this->normalizedWalkName($name);
            if ($normalizedName !== $name) {
                $name = $this->applyNormalization($name, $normalizedName);
                $path = $this->absolutePath($name);
                if (!file_exists($path) && !is_link($path)) {
                    return true;
                }
            }
        } catch (\Throwable $throwable) {
            self::reportWalkError($errorLogger, $name, $throwable, self::phaseForThrowable($throwable));
            return true;
        }

        $isDirectory = is_dir($path) && !is_link($path);
        $isRegularOrSymlink = is_file($path) || is_link($path);
        if (!$isDirectory && !$isRegularOrSymlink) {
            return true;
        }
        if ($this->isWindowsPlatform() && is_link($path)) {
            return true;
        }

        if ($ignoreMatcher !== null) {
            $match = $ignoreMatcher->match($name);
            if ($match->isIgnored()) {
                if ($isDirectory && !$match->canSkipDir()) {
                    $highestIgnoredParent = $ignoredParent;
                    if ($highestIgnoredParent === null || !self::isParentPath($name, $highestIgnoredParent)) {
                        $highestIgnoredParent = $name;
                    }

                    return $this->walkDirectoryChildren($name, $path, $ignoreMatcher, $hashBlocks, $blockSize, $currentByName, $highestIgnoredParent, $seen, $results, $errorLogger, $shouldCancel, $failureLogger, $scanNow);
                }

                return true;
            }
        }

        if ($ignoredParent !== null && self::isParentPath($name, $ignoredParent)) {
            if (!$this->emitIgnoredParentChain($ignoredParent, $name, $hashBlocks, $blockSize, $currentByName, $seen, $results, $errorLogger, $shouldCancel)) {
                return false;
            }
        } elseif (!$this->emitScanned($name, $hashBlocks, $blockSize, $currentByName, $seen, $results, $errorLogger, $shouldCancel)) {
            return false;
        }

        if ($isDirectory) {
            return $this->walkDirectoryChildren($name, $path, $ignoreMatcher, $hashBlocks, $blockSize, $currentByName, null, $seen, $results, $errorLogger, $shouldCancel, $failureLogger, $scanNow);
        }

        return true;
    }

    /**
     * @param array<string, true> $seen
     * @param list<FileInfo> $results
     */
    private function walkDirectoryChildren(
        string $directoryName,
        string $path,
        ?IgnoreMatcher $ignoreMatcher,
        bool $hashBlocks,
        ?int $blockSize,
        array $currentByName,
        ?string $ignoredParent,
        array &$seen,
        array &$results,
        ?\Closure $errorLogger,
        ?\Closure $shouldCancel,
        ?\Closure $failureLogger,
        int $scanNow,
    ): bool {
        try {
            $entries = $this->directoryEntries($path);
        } catch (\Throwable $throwable) {
            self::reportWalkError($errorLogger, $directoryName === '' ? '.' : $directoryName, $throwable, 'scan');
            return true;
        }

        foreach ($entries as $entry) {
            $name = $directoryName === '' ? $entry : $directoryName . '/' . $entry;
            try {
                $continue = $this->walkPath($name, $ignoreMatcher, $hashBlocks, $blockSize, $currentByName, $ignoredParent, $seen, $results, $errorLogger, $shouldCancel, $failureLogger, $scanNow);
            } catch (\Throwable $throwable) {
                self::reportWalkFailure($failureLogger, $name, $throwable);
                throw $throwable;
            }
            if (!$continue) {
                return false;
            }
        }

        return true;
    }

    private function handleTemporaryPath(string $name, string $path, int $scanNow): bool
    {
        if (!RequestServer::isTemporaryName($name)) {
            return false;
        }

        if (!is_link($path) && is_file($path)) {
            $modified = @filemtime($path);
            if (is_int($modified) && $modified + $this->tempLifetimeSeconds < $scanNow) {
                @unlink($path);
            }
        }

        return true;
    }

    /**
     * @param array<string, true> $seen
     * @param list<FileInfo> $results
     */
    private function emitIgnoredParentChain(
        string $ignoredParent,
        string $name,
        bool $hashBlocks,
        ?int $blockSize,
        array $currentByName,
        array &$seen,
        array &$results,
        ?\Closure $errorLogger,
        ?\Closure $shouldCancel,
    ): bool {
        if (!$this->emitScanned($ignoredParent, $hashBlocks, $blockSize, $currentByName, $seen, $results, $errorLogger, $shouldCancel)) {
            return false;
        }

        $relative = substr($name, strlen($ignoredParent) + 1);
        $current = $ignoredParent;
        foreach (explode('/', $relative) as $part) {
            if ($part === '') {
                continue;
            }

            $current .= '/' . $part;
            if (!$this->emitScanned($current, $hashBlocks, $blockSize, $currentByName, $seen, $results, $errorLogger, $shouldCancel)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, true> $seen
     * @param list<FileInfo> $results
     */
    private function emitScanned(
        string $name,
        bool $hashBlocks,
        ?int $blockSize,
        array $currentByName,
        array &$seen,
        array &$results,
        ?\Closure $errorLogger,
        ?\Closure $shouldCancel,
    ): bool {
        if (isset($seen[$name])) {
            return true;
        }

        if (self::isCancelled($shouldCancel, $name)) {
            return false;
        }

        try {
            $file = $this->scanIfChanged($name, $hashBlocks, $blockSize, $currentByName[$name] ?? null);
        } catch (\Throwable $throwable) {
            self::reportWalkError($errorLogger, $name, $throwable, 'scan');
            $seen[$name] = true;
            return true;
        }

        if ($file !== null) {
            $results[] = $file;
        }
        $seen[$name] = true;

        return true;
    }

    /**
     * @param iterable<FileInfo> $currentFiles
     * @return array<string, FileInfo>
     */
    private function currentFilesByName(iterable $currentFiles): array
    {
        $currentByName = [];
        foreach ($currentFiles as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Current scanner files must be FileInfo instances');
            }

            $currentByName[$file->name] = $file;
        }

        return $currentByName;
    }

    /**
     * @return list<string>
     */
    private function directoryEntries(string $path): array
    {
        if ($this->directoryLister !== null) {
            $entries = ($this->directoryLister)($path);
            if (!is_array($entries)) {
                throw new \UnexpectedValueException('directory lister must return an array');
            }

            foreach ($entries as $entry) {
                if (!is_string($entry)) {
                    throw new \UnexpectedValueException('directory lister entries must be strings');
                }
            }

            $entries = array_values(array_filter(
                $entries,
                static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
            ));
            sort($entries, SORT_STRING);
            return $entries;
        }

        $rawEntries = @scandir($path);
        if ($rawEntries === false) {
            throw new \RuntimeException('reading directory entries failed');
        }

        $entries = [];
        foreach ($rawEntries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entries[] = $entry;
        }

        sort($entries, SORT_STRING);
        return $entries;
    }

    private function assertValidUtf8Name(string $name): void
    {
        if (preg_match('//u', $name) !== 1) {
            throw new \RuntimeException('scan: item is not in UTF8 encoding');
        }
    }

    private function normalizedWalkName(string $name): string
    {
        $normalized = ProtocolValidation::normalizeWireName($name, '/');
        if ($normalized === $name) {
            return $name;
        }

        if (!$this->autoNormalize) {
            throw new \RuntimeException('normalizing path: item is not in the correct UTF8 normalization form');
        }

        ProtocolValidation::checkFilename($normalized);
        return $normalized;
    }

    private function applyNormalization(string $name, string $normalizedName): string
    {
        $oldPath = $this->absolutePath($name);
        $newPath = $this->absolutePath($normalizedName);
        if ($oldPath === $newPath) {
            return $normalizedName;
        }

        if (file_exists($newPath) || is_link($newPath)) {
            $oldStat = @lstat($oldPath);
            $newStat = @lstat($newPath);
            if (is_array($oldStat) && is_array($newStat) && self::sameFilesystemEntry($oldStat, $newStat)) {
                $tempPath = self::normalizationTempPath($newPath);
                if (!@rename($oldPath, $tempPath) || !@rename($tempPath, $newPath)) {
                    @rename($tempPath, $oldPath);
                    throw new \RuntimeException('normalizing path: rename failed');
                }

                return $normalizedName;
            }

            throw new \RuntimeException('normalizing path: item has UTF8 encoding conflict with another item');
        }

        if (!@rename($oldPath, $newPath)) {
            throw new \RuntimeException('normalizing path: rename failed');
        }

        return $normalizedName;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function sameFilesystemEntry(array $left, array $right): bool
    {
        return isset($left['dev'], $left['ino'], $right['dev'], $right['ino'])
            && (int) $left['dev'] === (int) $right['dev']
            && (int) $left['ino'] === (int) $right['ino'];
    }

    private static function normalizationTempPath(string $normalizedPath): string
    {
        $base = basename($normalizedPath);
        $candidate = dirname($normalizedPath) . DIRECTORY_SEPARATOR . $base . '.tmp';
        if (!file_exists($candidate) && !is_link($candidate)) {
            return $candidate;
        }

        return dirname($normalizedPath) . DIRECTORY_SEPARATOR . hash('sha256', $base) . '.tmp';
    }

    private static function normalizeWalkSub(string $sub): string
    {
        $sub = trim(str_replace('\\', '/', $sub));
        if ($sub === '' || $sub === '.' || $sub === '/') {
            return '';
        }

        $sub = ltrim($sub, '/');
        $parts = [];
        foreach (explode('/', $sub) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException('Scanner walk sub must not traverse above the root');
            }

            $parts[] = $part;
        }

        $normalized = implode('/', $parts);
        if ($normalized !== '') {
            ProtocolValidation::checkFilename($normalized);
        }

        return $normalized;
    }

    public function diagnoseSubWalk(string $sub): ScannerSubWalkDiagnostic
    {
        $name = self::normalizeWalkSub($sub);
        $parent = self::subWalkParent($name);

        if ($parent !== '.') {
            $current = '';
            foreach (explode('/', $parent) as $part) {
                if ($part === '') {
                    continue;
                }

                $current = $current === '' ? $part : $current . '/' . $part;
                $path = $this->absolutePath($current);
                $stat = @lstat($path);
                if (!is_array($stat)) {
                    return new ScannerSubWalkDiagnostic(
                        $name,
                        $parent,
                        ScannerSubWalkDiagnostic::STATUS_MISSING_PARENT,
                        $current,
                    );
                }
                if (is_link($path)) {
                    return new ScannerSubWalkDiagnostic(
                        $name,
                        $parent,
                        ScannerSubWalkDiagnostic::STATUS_TRAVERSES_SYMLINK,
                        $current,
                        'traverses symlink: ' . $current,
                    );
                }
                if (!is_dir($path)) {
                    return new ScannerSubWalkDiagnostic(
                        $name,
                        $parent,
                        ScannerSubWalkDiagnostic::STATUS_NOT_A_DIRECTORY,
                        $current,
                        'not a directory: ' . $current,
                    );
                }
            }
        }

        $path = $name === '' ? $this->rootPath : $this->absolutePath($name);
        if (!file_exists($path) && !is_link($path)) {
            return new ScannerSubWalkDiagnostic($name, $parent, ScannerSubWalkDiagnostic::STATUS_MISSING, $name);
        }

        return new ScannerSubWalkDiagnostic($name, $parent, ScannerSubWalkDiagnostic::STATUS_ALLOWED);
    }

    private static function subWalkParent(string $name): string
    {
        if ($name === '' || !str_contains($name, '/')) {
            return '.';
        }

        $parent = substr($name, 0, strrpos($name, '/'));
        return $parent === '' ? '.' : $parent;
    }

    /**
     * @param list<string> $subs
     * @return list<string>
     */
    private static function checkpointSubs(array $subs): array
    {
        $checkpointSubs = [];
        foreach ($subs as $sub) {
            if (!is_string($sub)) {
                throw new \InvalidArgumentException('Scanner walk subs must be strings');
            }

            $checkpointSubs[] = self::normalizeWalkSub($sub);
        }

        return array_values(array_unique($checkpointSubs));
    }

    private static function isParentPath(string $name, string $parent): bool
    {
        return $name === $parent || str_starts_with($name, $parent . '/');
    }

    private function absolutePath(string $name): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    }
}
