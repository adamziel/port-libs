<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class SyncPlan
{
    /**
     * @return list<string>
     */
    public function changedPaths(MemoryProvider $source, MemoryProvider $target, ?FilterRuleSet $filter = null): array
    {
        $changed = [];
        foreach ($source->list() as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }

            $targetInfo = $this->optionalInfo($target, $sourceInfo->path);
            if ($targetInfo === null) {
                $changed[] = $sourceInfo->path;
                continue;
            }
            if (!$this->sameObject($sourceInfo, $targetInfo)) {
                $changed[] = $sourceInfo->path;
            }
        }

        return $changed;
    }

    public function check(MemoryProvider $source, MemoryProvider $target, bool $oneWay = false, ?FilterRuleSet $filter = null): CheckResult
    {
        $sourcePaths = $this->listedPaths($source, $filter);
        $targetPaths = $this->listedPaths($target, $filter);
        $allPaths = array_keys($sourcePaths + $targetPaths);
        sort($allPaths, SORT_STRING);

        $matches = [];
        $differ = [];
        $missingOnSource = [];
        $missingOnTarget = [];

        foreach ($allPaths as $path) {
            $sourceHas = isset($sourcePaths[$path]);
            $targetHas = isset($targetPaths[$path]);

            if (!$sourceHas) {
                if (!$oneWay) {
                    $missingOnSource[] = $path;
                }
                continue;
            }

            if (!$targetHas) {
                $missingOnTarget[] = $path;
                continue;
            }

            $sourceInfo = $sourcePaths[$path];
            $targetInfo = $targetPaths[$path];
            if ($sourceInfo->size !== $targetInfo->size || $sourceInfo->sha256 !== $targetInfo->sha256) {
                $differ[] = $path;
            } else {
                $matches[] = $path;
            }
        }

        return new CheckResult($matches, $differ, $missingOnSource, $missingOnTarget);
    }

    public function checkDownload(MemoryProvider $source, MemoryProvider $target, bool $oneWay = false, ?FilterRuleSet $filter = null): CheckResult
    {
        $sourcePaths = $this->listedPaths($source, $filter);
        $targetPaths = $this->listedPaths($target, $filter);
        $allPaths = array_keys($sourcePaths + $targetPaths);
        sort($allPaths, SORT_STRING);

        $matches = [];
        $differ = [];
        $missingOnSource = [];
        $missingOnTarget = [];
        $errors = [];
        $errorMessages = [];

        foreach ($allPaths as $path) {
            $sourceHas = isset($sourcePaths[$path]);
            $targetHas = isset($targetPaths[$path]);

            if (!$sourceHas) {
                if (!$oneWay) {
                    $missingOnSource[] = $path;
                }
                continue;
            }

            if (!$targetHas) {
                $missingOnTarget[] = $path;
                continue;
            }

            $sourceInfo = $sourcePaths[$path];
            $targetInfo = $targetPaths[$path];
            if ($sourceInfo->size !== $targetInfo->size) {
                $differ[] = $path;
                continue;
            }

            $comparison = $this->downloadComparison($source, $target, $path);
            if ($comparison->error !== null) {
                $errors[] = $path;
                $errorMessages[$path] = 'failed to download: ' . $comparison->error->getMessage();
                continue;
            }
            if (!$comparison->equal) {
                $differ[] = $path;
                continue;
            }

            $matches[] = $path;
        }

        return new CheckResult($matches, $differ, $missingOnSource, $missingOnTarget, $errors, $errorMessages);
    }

    /**
     * @return list<ObjectInfo>
     *
     * @param list<MemoryProvider> $compareDest
     * @param list<MemoryProvider> $copyDest
     */
    public function copyChanged(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        array $compareDest = [],
        array $copyDest = [],
        bool $noCheckDest = false,
        bool $ignoreExisting = false,
        bool $immutable = false,
        bool $ignoreTimes = false,
        bool $updateOlder = false,
        bool $noUpdateModTime = false,
        int $modifyWindowSeconds = 1,
        bool $checksum = false,
        bool $refreshTimes = false,
    ): array {
        $copied = [];
        foreach ($source->list() as $sourceInfo) {
            $path = $sourceInfo->path;
            if ($filter !== null && !$filter->includes($path)) {
                continue;
            }

            $targetInfo = $noCheckDest ? null : $this->optionalInfo($target, $path);
            if (!$noCheckDest && $targetInfo !== null && $ignoreExisting) {
                continue;
            }

            if (!$this->needsTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $ignoreTimes,
                $updateOlder,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            )) {
                continue;
            }

            if ($this->findEqualReference($sourceInfo, $targetInfo, $compareDest) !== null) {
                continue;
            }

            $copyDestReference = $this->findEqualReference($sourceInfo, $targetInfo, $copyDest);
            if ($copyDestReference !== null) {
                if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                    $this->moveToBackup($target, $path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                }
                $copied[] = $copyDestReference['provider']->copyTo($copyDestReference['path'], $target, $path);
                continue;
            }

            if (!$noCheckDest && $targetInfo !== null && $immutable) {
                throw new \RuntimeException('immutable file modified');
            }

            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                if ($targetInfo !== null) {
                    $this->moveToBackup($target, $path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                }
            }
            $copied[] = $source->copyTo($path, $target, $path);
        }

        return $copied;
    }

    /**
     * @return list<string>
     */
    public function deletePaths(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $deleteExcluded = false,
    ): array {
        $deleteMode = DeleteMode::normalize($deleteMode);
        if ($deleteMode === DeleteMode::OFF) {
            return [];
        }

        $sourcePaths = $this->listedPaths($source, $filter);
        $targetPaths = $this->listedPaths($target, $deleteExcluded ? null : $filter);
        $delete = [];
        foreach ($targetPaths as $path => $targetInfo) {
            if (!isset($sourcePaths[$path])) {
                $delete[] = $targetInfo->path;
            }
        }

        sort($delete, SORT_STRING);

        return $delete;
    }

    /**
     * @return list<ObjectInfo>
     */
    public function deleteDestinationOnly(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $deleteExcluded = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
    ): array {
        $deleted = [];
        $deleteCount = 0;
        $deleteBytes = 0;
        foreach ($this->deletePaths($source, $target, $filter, $deleteMode, $deleteExcluded) as $path) {
            if ($backupPrefix !== '' && self::pathUnderPrefix($path, $backupPrefix)) {
                continue;
            }

            $targetInfo = $target->info($path);
            $deleteSize = max(0, $targetInfo->size);
            $this->assertDeleteWithinLimits($deleteCount, $deleteBytes, $deleteSize, $maxDelete, $maxDeleteSize);
            $deleteCount++;
            $deleteBytes += $deleteSize;
            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $deleted[] = $this->moveToBackup($target, $path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
            } else {
                $deleted[] = $target->delete($path);
            }
        }

        return $deleted;
    }

    public static function backupPath(
        string $path,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
    ): string {
        $path = self::normalizePath($path);
        $path = self::suffixName($path, $suffix, $suffixKeepExtension);
        $backupPrefix = self::normalizePath($backupPrefix);

        return $backupPrefix === '' ? $path : $backupPrefix . '/' . $path;
    }

    public static function resolveBackupRoot(
        string $destinationRoot,
        string $sourceRoot,
        string $backupRoot = '',
        string $sourceFileName = '',
        string $suffix = '',
        bool $backupSupportsServerSideMove = true,
    ): string {
        $destinationRoot = self::normalizeRoot($destinationRoot);
        $sourceRoot = self::normalizeRoot($sourceRoot);
        $backupRoot = self::normalizeRoot($backupRoot);

        if ($backupRoot !== '') {
            if (!self::sameRootConfig($destinationRoot, $backupRoot)) {
                throw new \RuntimeException('parameter to --backup-dir has to be on the same remote as destination');
            }
            if ($sourceFileName === '') {
                if (self::rootsOverlap($backupRoot, $destinationRoot)) {
                    throw new \RuntimeException("destination and parameter to --backup-dir mustn't overlap");
                }
                if (self::rootsOverlap($backupRoot, $sourceRoot)) {
                    throw new \RuntimeException("source and parameter to --backup-dir mustn't overlap");
                }
            } elseif ($suffix === '') {
                if (self::sameRootPath($destinationRoot, $backupRoot)) {
                    throw new \RuntimeException("destination and parameter to --backup-dir mustn't be the same");
                }
                if (self::sameRootPath($sourceRoot, $backupRoot)) {
                    throw new \RuntimeException("source and parameter to --backup-dir mustn't be the same");
                }
            }
        } elseif ($suffix !== '') {
            $backupRoot = $destinationRoot;
        } else {
            throw new \RuntimeException('internal error: BackupDir called when --backup-dir and --suffix both empty');
        }

        if (!$backupSupportsServerSideMove) {
            throw new \RuntimeException("can't use --backup-dir on a remote which doesn't support server-side move or copy");
        }

        return $backupRoot;
    }

    public function dirsEqual(
        MemoryProvider $source,
        MemoryProvider $target,
        string $sourcePath,
        ?string $targetPath = null,
        bool $setDirModTime = true,
        bool $setDirMetadata = false,
        bool $ignoreTimes = false,
        bool $immutable = false,
        bool $ignoreExisting = false,
        bool $updateOlder = false,
        bool $sizeOnly = false,
        ?int $modifyWindowSeconds = 1,
    ): bool {
        try {
            $sourceInfo = $source->directoryInfo($sourcePath);
            $targetInfo = $target->directoryInfo($targetPath ?? $sourcePath);
        } catch (\RuntimeException) {
            return false;
        }

        if ($sizeOnly || $immutable || $ignoreExisting || $modifyWindowSeconds === null) {
            return true;
        }
        if ($ignoreTimes) {
            return false;
        }
        if (!$setDirModTime && !$setDirMetadata) {
            return true;
        }

        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if ($dt === null) {
            return false;
        }
        if ($dt < $modifyWindowSeconds && $dt > -$modifyWindowSeconds) {
            return true;
        }
        if ($updateOlder && $dt >= $modifyWindowSeconds) {
            return true;
        }

        return false;
    }

    /**
     * @param list<string|ObjectInfo> $changedPaths
     * @return list<ObjectInfo>
     */
    public function setDelayedDirectoryModTimes(
        MemoryProvider $source,
        MemoryProvider $target,
        array $changedPaths,
        bool $copyEmptySourceDirs = false,
        bool $setDirModTime = true,
        bool $setDirMetadata = false,
        bool $noUpdateDirModTime = false,
    ): array {
        if (!$setDirModTime || $noUpdateDirModTime) {
            return [];
        }

        $modifiedDirs = [];
        foreach ($changedPaths as $changedPath) {
            $dir = $this->changedPathDirectory($source, $changedPath);
            if ($dir !== '') {
                $modifiedDirs[$dir] = true;
            }
        }
        if ($modifiedDirs === []) {
            return [];
        }

        $queue = [];
        $maxLevel = 0;
        foreach ($source->directories() as $sourceDir) {
            $level = self::pathLevel($sourceDir->path);
            $maxLevel = max($maxLevel, $level);
            $queue[] = [
                'info' => $sourceDir,
                'level' => $level,
            ];
        }

        $updated = [];
        for ($level = $maxLevel; $level >= 0; $level--) {
            foreach ($queue as $item) {
                if ($item['level'] !== $level) {
                    continue;
                }

                $sourceDir = $item['info'];
                if (!isset($modifiedDirs[$sourceDir->path])) {
                    continue;
                }
                if (!$copyEmptySourceDirs && $this->sourceDirectoryIsEmpty($source, $sourceDir->path)) {
                    continue;
                }
                $targetDirExists = $this->directoryExists($target, $sourceDir->path);
                if (!$targetDirExists && !$copyEmptySourceDirs) {
                    continue;
                }
                if (!$targetDirExists) {
                    $target->mkdir($sourceDir->path);
                }

                $updated[] = $this->applyDirectoryUpdate($target, $sourceDir, $setDirMetadata);
                $parent = self::parentPath($sourceDir->path);
                if ($parent !== '') {
                    $modifiedDirs[$parent] = true;
                }
            }
        }

        return $updated;
    }

    /**
     * @return array<string, ObjectInfo>
     */
    private function listedPaths(MemoryProvider $provider, ?FilterRuleSet $filter): array
    {
        $paths = [];
        foreach ($provider->list() as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $paths[$info->path] = $info;
        }

        return $paths;
    }

    private function downloadComparison(MemoryProvider $source, MemoryProvider $target, string $path): ReaderComparisonResult
    {
        try {
            $targetReader = $target->openReader($path);
        } catch (\Throwable $throwable) {
            return new ReaderComparisonResult(false, new \RuntimeException(
                'failed to open "' . $path . '": ' . $throwable->getMessage(),
                0,
                $throwable,
            ));
        }

        try {
            $sourceReader = $source->openReader($path);
        } catch (\Throwable $throwable) {
            return new ReaderComparisonResult(false, new \RuntimeException(
                'failed to open "' . $path . '": ' . $throwable->getMessage(),
                0,
                $throwable,
            ));
        }

        return ReaderComparison::checkEqualReaders($targetReader, $sourceReader);
    }

    private function optionalInfo(MemoryProvider $provider, string $path): ?ObjectInfo
    {
        try {
            return $provider->info($path);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function directoryExists(MemoryProvider $provider, string $path): bool
    {
        try {
            $provider->directoryInfo($path);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function applyDirectoryUpdate(MemoryProvider $target, ObjectInfo $sourceDir, bool $setDirMetadata): ObjectInfo
    {
        if ($setDirMetadata) {
            $metadata = $sourceDir->metadata;
            if ($sourceDir->modTime !== null && ($metadata['mtime'] ?? '') === '') {
                $metadata['mtime'] = $sourceDir->modTime;
            }

            return $target->mkdir($sourceDir->path, [
                'modTime' => $sourceDir->modTime,
                'metadata' => $metadata,
            ]);
        }

        return $target->setDirectoryModTime($sourceDir->path, $sourceDir->modTime);
    }

    /**
     * @param string|ObjectInfo $changedPath
     */
    private function changedPathDirectory(MemoryProvider $source, string|ObjectInfo $changedPath): string
    {
        $path = $changedPath instanceof ObjectInfo ? $changedPath->path : self::normalizePath($changedPath);
        if ($path === '') {
            return '';
        }

        if ($this->optionalInfo($source, $path) !== null) {
            return self::parentPath($path);
        }

        try {
            return $source->directoryInfo($path)->path;
        } catch (\RuntimeException) {
            return self::parentPath($path);
        }
    }

    private function sourceDirectoryIsEmpty(MemoryProvider $source, string $dir): bool
    {
        foreach ($source->list() as $info) {
            if (self::pathUnderPrefix($info->path, $dir)) {
                return false;
            }
        }

        return true;
    }

    private function needsTransfer(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ?ObjectInfo $targetInfo,
        bool $ignoreTimes,
        bool $updateOlder,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
    ): bool
    {
        if ($targetInfo === null) {
            return true;
        }
        if ($ignoreTimes) {
            return true;
        }
        if ($updateOlder) {
            return $this->needsUpdateOlderTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            );
        }

        return !$this->objectsEqualOrModTimeUpdated(
            $source,
            $target,
            $sourceInfo,
            $targetInfo,
            $noUpdateModTime,
            $modifyWindowSeconds,
            $checksum,
            $immutable,
            $refreshTimes,
        );
    }

    private function sameObject(ObjectInfo $left, ObjectInfo $right): bool
    {
        return $left->size === $right->size && $left->sha256 === $right->sha256;
    }

    private function needsUpdateOlderTransfer(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
    ): bool {
        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if ($dt === null) {
            return !$this->objectsEqualOrModTimeUpdated(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            );
        }

        if ($this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
            if ($checksum) {
                return !$this->sameSizeAndHash($source, $target, $sourceInfo, $targetInfo, true);
            }

            return $sourceInfo->size !== $targetInfo->size;
        }

        if ($dt > 0) {
            return false;
        }

        return !$this->objectsEqualOrModTimeUpdated(
            $source,
            $target,
            $sourceInfo,
            $targetInfo,
            $noUpdateModTime,
            $modifyWindowSeconds,
            $checksum,
            $immutable,
            $refreshTimes,
            true,
        );
    }

    private function objectsEqualOrModTimeUpdated(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
        bool $forceModTimeMatch = false,
    ): bool {
        if ($sourceInfo->size !== $targetInfo->size) {
            return false;
        }
        if ($checksum) {
            return $this->sameSizeAndHash($source, $target, $sourceInfo, $targetInfo, false);
        }

        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if (!$forceModTimeMatch && $dt !== null && $this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
            return true;
        }

        $sameHash = $this->sameProviderHash($source, $target, $sourceInfo->path, $targetInfo->path);
        if ($sameHash !== true && !($sameHash === null && $refreshTimes)) {
            return false;
        }

        if ($sourceInfo->modTime !== $targetInfo->modTime) {
            if ($immutable) {
                return false;
            }
            if (!$noUpdateModTime) {
                $target->setModTime($targetInfo->path, $sourceInfo->modTime);
            }
        }

        return true;
    }

    private function sameSizeAndHash(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $fallbackToSizeOnly,
    ): bool {
        if ($sourceInfo->size !== $targetInfo->size) {
            return false;
        }

        $sameHash = $this->sameProviderHash($source, $target, $sourceInfo->path, $targetInfo->path);

        return $sameHash ?? $fallbackToSizeOnly;
    }

    private function sameProviderHash(MemoryProvider $source, MemoryProvider $target, string $sourcePath, string $targetPath): ?bool
    {
        $commonHashes = $source->supportedHashes()->overlap($target->supportedHashes());
        if ($commonHashes->count() === 0) {
            return null;
        }

        $hashType = $commonHashes->getOne();

        return ($source->hashes($sourcePath, new HashSet($hashType))[$hashType] ?? null)
            === ($target->hashes($targetPath, new HashSet($hashType))[$hashType] ?? null);
    }

    private function modTimeDeltaSeconds(ObjectInfo $sourceInfo, ObjectInfo $targetInfo): ?float
    {
        $sourceTime = $this->timestamp($sourceInfo->modTime);
        $targetTime = $this->timestamp($targetInfo->modTime);
        if ($sourceTime === null || $targetTime === null) {
            return null;
        }

        return $targetTime - $sourceTime;
    }

    private function timestamp(?string $modTime): ?float
    {
        if ($modTime === null || $modTime === '') {
            return null;
        }

        try {
            $dateTime = new \DateTimeImmutable($modTime);
        } catch (\Exception) {
            return null;
        }

        $seconds = (float) $dateTime->format('U');
        $micros = (float) $dateTime->format('u') / 1_000_000;

        return $seconds + $micros;
    }

    private function modTimesWithinWindow(float $deltaSeconds, int $modifyWindowSeconds): bool
    {
        if ($modifyWindowSeconds <= 0) {
            return $deltaSeconds === 0.0;
        }

        return abs($deltaSeconds) < $modifyWindowSeconds;
    }

    /**
     * @param list<MemoryProvider> $references
     *
     * @return array{provider: MemoryProvider, path: string}|null
     */
    private function findEqualReference(ObjectInfo $sourceInfo, ?ObjectInfo $targetInfo, array $references): ?array
    {
        $referencePath = $targetInfo?->path ?? $sourceInfo->path;
        foreach ($references as $reference) {
            $referenceInfo = $this->optionalInfo($reference, $referencePath);
            if ($referenceInfo !== null && $this->sameObject($sourceInfo, $referenceInfo)) {
                return [
                    'provider' => $reference,
                    'path' => $referenceInfo->path,
                ];
            }
        }

        return null;
    }

    private function assertDeleteWithinLimits(
        int $deleteCount,
        int $deleteBytes,
        int $nextSize,
        ?int $maxDelete,
        ?int $maxDeleteSize,
    ): void {
        if ($maxDelete !== null && $maxDelete >= 0 && $deleteCount + 1 > $maxDelete) {
            throw new \RuntimeException('--max-delete threshold reached');
        }
        if ($maxDeleteSize !== null && $maxDeleteSize >= 0 && $deleteBytes + $nextSize > $maxDeleteSize) {
            throw new \RuntimeException('--max-delete-size threshold reached');
        }
    }

    private function backupRequested(?MemoryProvider $backup, string $backupPrefix, string $suffix): bool
    {
        return $backup !== null || $backupPrefix !== '' || $suffix !== '';
    }

    private function moveToBackup(
        MemoryProvider $target,
        string $path,
        ?MemoryProvider $backup,
        string $backupPrefix,
        string $suffix,
        bool $suffixKeepExtension,
    ): ObjectInfo {
        $backup ??= $target;

        return $target->moveTo(
            $path,
            $backup,
            self::backupPath($path, $backupPrefix, $suffix, $suffixKeepExtension),
        );
    }

    private static function suffixName(string $path, string $suffix, bool $suffixKeepExtension): string
    {
        if ($suffix === '') {
            return $path;
        }
        if (!$suffixKeepExtension) {
            return $path . $suffix;
        }

        [$base, $extensions] = self::splitExtension($path);

        return $base . $suffix . $extensions;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitExtension(string $path): array
    {
        $base = $path;
        $extensions = '';
        $first = true;

        while (($extension = self::pathExtension($base)) !== '') {
            if (!$first && !self::isKnownExtension($extension)) {
                break;
            }

            $base = substr($base, 0, -strlen($extension));
            $extensions = $extension . $extensions;
            $first = false;
        }

        return [$base, $extensions];
    }

    private static function pathExtension(string $path): string
    {
        $slash = strrpos($path, '/');
        $nameStart = $slash === false ? 0 : $slash + 1;
        $dot = strrpos($path, '.');
        if ($dot === false || $dot < $nameStart) {
            return '';
        }

        return substr($path, $dot);
    }

    private static function isKnownExtension(string $extension): bool
    {
        return in_array(strtolower($extension), [
            '.css',
            '.gif',
            '.gz',
            '.htm',
            '.html',
            '.jpeg',
            '.jpg',
            '.js',
            '.json',
            '.mjs',
            '.pdf',
            '.png',
            '.sql',
            '.svg',
            '.tar',
            '.txt',
            '.webp',
            '.wxr',
            '.xml',
            '.zip',
        ], true);
    }

    private static function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    private static function pathUnderPrefix(string $path, string $prefix): bool
    {
        $path = self::normalizePath($path);
        $prefix = self::normalizePath($prefix);

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function parentPath(string $path): string
    {
        $path = self::normalizePath($path);
        if ($path === '' || !str_contains($path, '/')) {
            return '';
        }

        return substr($path, 0, strrpos($path, '/')) ?: '';
    }

    private static function pathLevel(string $path): int
    {
        $path = self::normalizePath($path);

        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private static function normalizeRoot(string $root): string
    {
        $root = str_replace('\\', '/', trim($root));
        $root = preg_replace('#/+#', '/', $root) ?? $root;
        if (str_contains($root, ':')) {
            [$remote, $path] = explode(':', $root, 2);

            return $remote . ':' . trim($path, '/');
        }

        return trim($root, '/');
    }

    private static function sameRootConfig(string $left, string $right): bool
    {
        return self::splitRoot($left)[0] === self::splitRoot($right)[0];
    }

    private static function sameRootPath(string $left, string $right): bool
    {
        return self::splitRoot($left) === self::splitRoot($right);
    }

    private static function rootsOverlap(string $left, string $right): bool
    {
        [$leftConfig, $leftPath] = self::splitRoot($left);
        [$rightConfig, $rightPath] = self::splitRoot($right);
        if ($leftConfig !== $rightConfig) {
            return false;
        }
        if ($leftPath === '' || $rightPath === '') {
            return true;
        }

        return $leftPath === $rightPath
            || str_starts_with($leftPath, $rightPath . '/')
            || str_starts_with($rightPath, $leftPath . '/');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitRoot(string $root): array
    {
        if (str_contains($root, ':')) {
            [$config, $path] = explode(':', $root, 2);

            return [$config, trim($path, '/')];
        }

        return ['local', trim($root, '/')];
    }
}
