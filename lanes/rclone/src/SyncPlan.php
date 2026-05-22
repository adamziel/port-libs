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

            try {
                $targetInfo = $target->info($sourceInfo->path);
            } catch (\RuntimeException) {
                $changed[] = $sourceInfo->path;
                continue;
            }
            if ($sourceInfo->sha256 !== $targetInfo->sha256 || $sourceInfo->size !== $targetInfo->size) {
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
     */
    public function copyChanged(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
    ): array {
        $copied = [];
        foreach ($this->changedPaths($source, $target, $filter) as $path) {
            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $hasTarget = true;
                try {
                    $target->info($path);
                } catch (\RuntimeException) {
                    $hasTarget = false;
                }
                if ($hasTarget) {
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
}
