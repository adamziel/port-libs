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
    public function copyChanged(MemoryProvider $source, MemoryProvider $target, ?FilterRuleSet $filter = null): array
    {
        $copied = [];
        foreach ($this->changedPaths($source, $target, $filter) as $path) {
            $copied[] = $source->copyTo($path, $target, $path);
        }

        return $copied;
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
}
