<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class SyncPlan
{
    /**
     * @return list<string>
     */
    public function changedPaths(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $ignoreCaseSync = false,
    ): array
    {
        $changed = [];
        $targetPaths = $ignoreCaseSync ? $this->listedPaths($target, $filter, true) : [];
        $seenSourceKeys = [];
        foreach ($source->list() as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }
            if ($this->skipCaseFoldedDuplicate($sourceInfo->path, $seenSourceKeys, $ignoreCaseSync)) {
                continue;
            }

            $targetInfo = $ignoreCaseSync
                ? ($targetPaths[$this->syncPathKey($sourceInfo->path)] ?? null)
                : $this->optionalInfo($target, $sourceInfo->path);
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
        bool $fixCase = false,
        bool $ignoreCaseSync = false,
    ): array {
        if ($fixCase && !$noCheckDest) {
            $this->fixCase($source, $target, $filter, $immutable);
        }

        $copied = [];
        $targetPaths = $ignoreCaseSync && !$noCheckDest ? $this->listedPaths($target, $filter, true) : [];
        $seenSourceKeys = [];
        foreach ($source->list() as $sourceInfo) {
            $path = $sourceInfo->path;
            if ($filter !== null && !$filter->includes($path)) {
                continue;
            }
            if ($this->skipCaseFoldedDuplicate($path, $seenSourceKeys, $ignoreCaseSync)) {
                continue;
            }

            $targetInfo = $noCheckDest
                ? null
                : ($ignoreCaseSync ? ($targetPaths[$this->syncPathKey($path)] ?? null) : $this->optionalInfo($target, $path));
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
                $destinationPath = $targetInfo?->path ?? $path;
                if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                    $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                }
                $copied[] = $copyDestReference['provider']->copyTo($copyDestReference['path'], $target, $destinationPath);
                continue;
            }

            if (!$noCheckDest && $targetInfo !== null && $immutable) {
                throw new \RuntimeException('immutable file modified');
            }

            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                if ($targetInfo !== null) {
                    $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                    $targetInfo = null;
                }
            }
            $copied[] = $source->copyTo($path, $target, $targetInfo?->path ?? $path);
        }

        return $copied;
    }

    /**
     * @return array{renamed: list<ObjectInfo>, copied: list<ObjectInfo>, deleted: list<ObjectInfo>, trackRenamesEnabled: bool, disabledReason: ?string}
     */
    public function syncWithTrackRenames(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $trackRenamesStrategy = 'hash',
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
        int $modifyWindowSeconds = 1,
        bool $ignoreCaseSync = false,
    ): array {
        $strategy = TrackRenamesStrategy::parse($trackRenamesStrategy);
        $disabledReason = $this->trackRenamesDisabledReason($source, $target, $strategy);
        if ($disabledReason !== null) {
            $copied = $this->copyChanged(
                $source,
                $target,
                $filter,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
            );
            $deleted = $this->deleteDestinationOnly(
                $source,
                $target,
                $filter,
                DeleteMode::AFTER,
                maxDelete: $maxDelete,
                maxDeleteSize: $maxDeleteSize,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
            );

            return [
                'renamed' => [],
                'copied' => $copied,
                'deleted' => $deleted,
                'trackRenamesEnabled' => false,
                'disabledReason' => $disabledReason,
            ];
        }

        $sourcePaths = $this->listedPaths($source, $filter, $ignoreCaseSync);
        $targetPaths = $this->listedPaths($target, $filter, $ignoreCaseSync);
        $commonHash = $this->commonHashType($source, $target);

        $sourceOnly = [];
        $targetOnly = [];
        $copied = [];
        $renamed = [];
        foreach ($sourcePaths as $sourceKey => $sourceInfo) {
            $targetInfo = $targetPaths[$sourceKey] ?? null;
            if ($targetInfo === null) {
                $sourceOnly[] = $sourceInfo;
                continue;
            }

            if (!$this->needsTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                false,
                false,
                false,
                $modifyWindowSeconds,
                false,
                false,
                false,
            )) {
                continue;
            }

            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                $targetInfo = null;
            }
            $copied[] = $source->copyTo($sourceInfo->path, $target, $targetInfo?->path ?? $sourceInfo->path);
        }

        foreach ($targetPaths as $targetKey => $targetInfo) {
            if (isset($sourcePaths[$targetKey])) {
                continue;
            }
            if ($backupPrefix !== '' && self::pathUnderPrefix($targetInfo->path, $backupPrefix)) {
                continue;
            }
            $targetOnly[$targetInfo->path] = $targetInfo;
        }

        $renameMap = $this->buildRenameMap($sourceOnly, array_values($targetOnly), $target, $strategy, $commonHash);
        foreach ($sourceOnly as $sourceInfo) {
            $renameId = $this->trackRenameId($source, $sourceInfo, $strategy, $commonHash);
            $renameSource = $this->popRenameCandidate($renameMap, $renameId, $sourceInfo, $strategy, $modifyWindowSeconds);
            if ($renameSource !== null) {
                try {
                    $renamed[] = $target->serverSideMoveTo($renameSource->path, $target, $sourceInfo->path);
                    unset($targetOnly[$renameSource->path]);
                    continue;
                } catch (\RuntimeException) {
                    // Upstream tryRename logs the failed server-side rename and lets the
                    // normal upload/delete-after path handle the source and stale target.
                }
            }

            $copied[] = $source->copyTo($sourceInfo->path, $target, $sourceInfo->path);
        }

        ksort($targetOnly, SORT_STRING);
        $deleteCount = 0;
        $deleteBytes = 0;
        $deleted = [];
        foreach (array_keys($targetOnly) as $path) {
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

        return [
            'renamed' => $renamed,
            'copied' => $copied,
            'deleted' => $deleted,
            'trackRenamesEnabled' => true,
            'disabledReason' => null,
        ];
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    public function copyFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        array $options = [],
    ): array {
        return $this->moveOrCopyFile($destination, $source, $destinationPath, $sourcePath, true, $options);
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    public function moveFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        array $options = [],
    ): array {
        return $this->moveOrCopyFile($destination, $source, $destinationPath, $sourcePath, false, $options);
    }

    /**
     * Model `rclone dedupe --by-hash`.
     *
     * Interactive mode is represented by a deterministic chooser callback
     * instead of reading from a terminal.
     *
     * @param null|callable(array<string, mixed>): array<string, mixed>|string $interactiveChoice
     * @return array{hashType: string, groups: list<array{hash: string, objects: list<ObjectInfo>, kept: ?ObjectInfo, deleted: list<ObjectInfo>, skipped: bool, action?: string, quit?: bool}>, quit: bool}
     */
    public function deduplicateByHash(MemoryProvider $provider, string $mode, ?callable $interactiveChoice = null): array
    {
        $mode = DeduplicateMode::normalize($mode);
        $hashType = $provider->supportedHashes()->getOne();
        if ($hashType === HashType::NONE) {
            throw new \RuntimeException('provider has no hashes');
        }
        if ($mode === DeduplicateMode::INTERACTIVE && $interactiveChoice === null) {
            throw new \InvalidArgumentException('interactive dedupe mode requires a caller-supplied choice');
        }
        if ($mode === DeduplicateMode::RENAME) {
            throw new \InvalidArgumentException('dedupe by hash rename mode is not available in this native slice');
        }

        $objectsByHash = [];
        foreach ($provider->list() as $info) {
            $hash = $provider->hashesForObject($info, new HashSet($hashType))[$hashType] ?? '';
            if ($hash === '') {
                continue;
            }
            $objectsByHash[$hash][] = $info;
        }
        ksort($objectsByHash, SORT_STRING);

        $groups = [];
        $quit = false;
        foreach ($objectsByHash as $hash => $objects) {
            if (count($objects) <= 1) {
                continue;
            }

            if ($mode === DeduplicateMode::INTERACTIVE) {
                $decision = $this->interactiveDedupeDecision(
                    $interactiveChoice,
                    [
                        'byHash' => true,
                        'hash' => $hash,
                        'hashType' => $hashType,
                        'objects' => $objects,
                    ],
                    count($objects),
                    true,
                );

                if ($decision['action'] === 'skip' || $decision['action'] === 'quit') {
                    $groups[] = [
                        'hash' => $hash,
                        'objects' => $objects,
                        'kept' => null,
                        'deleted' => [],
                        'skipped' => true,
                        'action' => $decision['action'],
                        'quit' => $decision['action'] === 'quit',
                    ];
                    if ($decision['action'] === 'quit') {
                        $quit = true;
                        break;
                    }
                    continue;
                }

                $choice = $this->deleteDedupeObjectsExcept($provider, $objects, $decision['keepIndex'] ?? 0);
                $groups[] = [
                    'hash' => $hash,
                    'objects' => $objects,
                    'kept' => $choice['kept'],
                    'deleted' => $choice['deleted'],
                    'skipped' => false,
                    'action' => 'keep',
                ];
                continue;
            }

            if ($mode === DeduplicateMode::SKIP || $mode === DeduplicateMode::LIST) {
                $groups[] = [
                    'hash' => $hash,
                    'objects' => $objects,
                    'kept' => null,
                    'deleted' => [],
                    'skipped' => true,
                ];
                continue;
            }

            $ordered = $this->dedupeOrderedObjects($objects, $mode);
            $keepIndex = match ($mode) {
                DeduplicateMode::FIRST, DeduplicateMode::OLDEST, DeduplicateMode::SMALLEST => 0,
                DeduplicateMode::NEWEST, DeduplicateMode::LARGEST => count($ordered) - 1,
                default => 0,
            };
            $kept = $ordered[$keepIndex];
            $deleted = [];
            foreach ($ordered as $index => $info) {
                if ($index === $keepIndex) {
                    continue;
                }
                $deleted[] = $provider->deleteListedObject($info);
            }

            $groups[] = [
                'hash' => $hash,
                'objects' => $objects,
                'kept' => $kept,
                'deleted' => $deleted,
                'skipped' => false,
            ];
        }

        return [
            'hashType' => $hashType,
            'groups' => $groups,
            'quit' => $quit,
        ];
    }

    /**
     * Model `rclone dedupe` by duplicate remote name.
     * Identical duplicates are removed before skip/keep/rename modes, matching
     * upstream's by-name flow.
     *
     * Interactive mode is represented by a deterministic chooser callback
     * instead of reading from a terminal.
     *
     * @param null|callable(array<string, mixed>): array<string, mixed>|string $interactiveChoice
     * @return array{groups: list<array{path: string, objects: list<ObjectInfo>, identicalDeleted: list<ObjectInfo>, remaining: list<ObjectInfo>, kept: ?ObjectInfo, deleted: list<ObjectInfo>, renamed: list<ObjectInfo>, skipped: bool, listed: bool, action?: string, quit?: bool}>, quit: bool}
     */
    public function deduplicateByName(
        MemoryProvider $provider,
        string $mode,
        bool $sizeOnly = false,
        ?callable $interactiveChoice = null,
    ): array
    {
        $mode = DeduplicateMode::normalize($mode);
        if ($mode === DeduplicateMode::INTERACTIVE && $interactiveChoice === null) {
            throw new \InvalidArgumentException('interactive dedupe mode requires a caller-supplied choice');
        }

        $objectsByPath = [];
        foreach ($provider->list() as $info) {
            $objectsByPath[$info->path][] = $info;
        }
        ksort($objectsByPath, SORT_STRING);

        $groups = [];
        $quit = false;
        foreach ($objectsByPath as $path => $objects) {
            if (count($objects) <= 1) {
                continue;
            }

            $remaining = $objects;
            $identicalDeleted = [];
            if ($mode !== DeduplicateMode::LIST) {
                $identical = $this->deleteIdenticalDuplicateNames($provider, $remaining, $sizeOnly);
                $remaining = $identical['remaining'];
                $identicalDeleted = $identical['deleted'];
            }

            $kept = null;
            $deleted = [];
            $renamed = [];
            $skipped = false;
            $listed = false;
            $action = null;
            $groupQuit = false;
            if (count($remaining) > 1) {
                if ($mode === DeduplicateMode::INTERACTIVE) {
                    $decision = $this->interactiveDedupeDecision(
                        $interactiveChoice,
                        [
                            'byHash' => false,
                            'path' => $path,
                            'objects' => $remaining,
                        ],
                        count($remaining),
                        false,
                    );
                    $action = $decision['action'];
                    if ($decision['action'] === 'skip') {
                        $skipped = true;
                    } elseif ($decision['action'] === 'quit') {
                        $skipped = true;
                        $groupQuit = true;
                        $quit = true;
                    } elseif ($decision['action'] === 'rename') {
                        $renamed = $this->renameDuplicateNames($provider, $path, $remaining);
                        $remaining = $renamed;
                    } else {
                        $choice = $this->deleteDedupeObjectsExcept($provider, $remaining, $decision['keepIndex'] ?? 0);
                        $kept = $choice['kept'];
                        $deleted = $choice['deleted'];
                        $remaining = [$kept];
                    }
                } elseif ($mode === DeduplicateMode::SKIP) {
                    $skipped = true;
                } elseif ($mode === DeduplicateMode::LIST) {
                    $listed = true;
                } elseif ($mode === DeduplicateMode::RENAME) {
                    $renamed = $this->renameDuplicateNames($provider, $path, $remaining);
                    $remaining = $renamed;
                } else {
                    $ordered = $this->dedupeOrderedObjects($remaining, $mode);
                    $keepIndex = match ($mode) {
                        DeduplicateMode::FIRST, DeduplicateMode::OLDEST, DeduplicateMode::SMALLEST => 0,
                        DeduplicateMode::NEWEST, DeduplicateMode::LARGEST => count($ordered) - 1,
                        default => 0,
                    };
                    $kept = $ordered[$keepIndex];
                    foreach ($ordered as $index => $info) {
                        if ($index === $keepIndex) {
                            continue;
                        }
                        $deleted[] = $provider->deleteListedObject($info);
                    }
                    $remaining = [$kept];
                }
            }

            $groups[] = [
                'path' => $path,
                'objects' => $objects,
                'identicalDeleted' => $identicalDeleted,
                'remaining' => $remaining,
                'kept' => $kept,
                'deleted' => $deleted,
                'renamed' => $renamed,
                'skipped' => $skipped,
                'listed' => $listed,
                'action' => $action ?? $mode,
                'quit' => $groupQuit,
            ];
            if ($quit) {
                break;
            }
        }

        return [
            'groups' => $groups,
            'quit' => $quit,
        ];
    }

    /**
     * Discover duplicate directory entries the way rclone's dedupe pre-pass
     * does: provider IDs identify directories and ParentIDs build recursive
     * entry counts, while missing IDs fall back to remote paths.
     *
     * @return list<array{path: string, directories: list<ObjectInfo>, counts: list<int>}>
     */
    public function findDuplicateDirectories(MemoryProvider $provider): array
    {
        $directories = $provider->directories();
        $dirsById = [];
        $dirsByPath = [];

        foreach ($directories as $directory) {
            $id = $this->dedupeEntryId($directory);
            $dirsById[$id] ??= [
                'directory' => null,
                'parent' => '',
                'count' => 0,
            ];
            $dirsById[$id]['directory'] = $directory;
            $dirsById[$id]['parent'] = $this->dedupeEntryParentId($directory);
            $dirsByPath[$directory->path][] = $id;
        }

        $entries = array_merge($directories, $provider->list());
        usort(
            $entries,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        foreach ($entries as $entry) {
            $this->incrementDedupeDirectoryCount($dirsById, $this->dedupeEntryParentId($entry));
        }

        ksort($dirsByPath, SORT_STRING);
        $groups = [];
        foreach ($dirsByPath as $path => $ids) {
            if (count($ids) <= 1) {
                continue;
            }

            $duplicateDirectories = [];
            $counts = [];
            foreach ($ids as $id) {
                $directory = $dirsById[$id]['directory'] ?? null;
                if (!$directory instanceof ObjectInfo) {
                    continue;
                }
                $duplicateDirectories[] = $directory;
                $counts[] = $dirsById[$id]['count'];
            }

            if (count($duplicateDirectories) > 1) {
                $groups[] = [
                    'path' => $path,
                    'directories' => $duplicateDirectories,
                    'counts' => $counts,
                ];
            }
        }

        return $groups;
    }

    /**
     * Model `rclone dedupe --dedupe-mode list` for duplicate directories.
     *
     * @return array{groups: list<array{path: string, directories: list<ObjectInfo>, counts: list<int>, report: string}>}
     */
    public function listDuplicateDirectories(MemoryProvider $provider): array
    {
        $groups = [];
        foreach ($this->findDuplicateDirectories($provider) as $group) {
            $groups[] = [
                'path' => $group['path'],
                'directories' => $group['directories'],
                'counts' => $group['counts'],
                'report' => sprintf('%s: %d duplicates of this directory', $group['path'], count($group['directories'])),
            ];
        }

        return ['groups' => $groups];
    }

    /**
     * Model the dedupe duplicate-directory merge boundary: non-list modes put
     * the largest recursive directory first before calling the provider
     * MergeDirs feature, while list mode reports duplicates without mutation.
     *
     * @param list<string|ObjectInfo> $directories
     * @return array{listed: bool, ordered: list<string>, target: ?ObjectInfo, merge: ?array{target: ObjectInfo, moved: list<ObjectInfo>, removed: list<ObjectInfo>}}
     */
    public function mergeDuplicateDirectories(MemoryProvider $provider, array $directories, bool $listOnly = false): array
    {
        $items = [];
        foreach ($directories as $index => $directory) {
            $info = $provider->directoryInfo($directory instanceof ObjectInfo ? $directory->path : $directory);
            $items[] = [
                'index' => $index,
                'path' => $info->path,
                'count' => $provider->directoryEntryCount($info),
                'info' => $info,
            ];
        }

        if ($listOnly || count($items) <= 1) {
            return [
                'listed' => true,
                'ordered' => array_map(static fn (array $item): string => $item['path'], $items),
                'target' => null,
                'merge' => null,
            ];
        }

        usort(
            $items,
            static fn (array $a, array $b): int => $b['count'] <=> $a['count']
                ?: $a['index'] <=> $b['index'],
        );
        $ordered = array_map(static fn (array $item): ObjectInfo => $item['info'], $items);
        $merge = $provider->mergeDirectories($ordered);

        return [
            'listed' => false,
            'ordered' => array_map(static fn (ObjectInfo $info): string => $info->path, $ordered),
            'target' => $merge['target'],
            'merge' => $merge,
        ];
    }

    /**
     * @return array{existed: bool, savedPath: ?string, cleanup: \Closure}
     */
    public function removeExisting(
        MemoryProvider $provider,
        string $path,
        string $operation = 'operation',
        ?string $temporarySuffix = null,
    ): array {
        $path = self::normalizePath($path);
        try {
            $provider->info($path);
        } catch (\RuntimeException) {
            return [
                'existed' => false,
                'savedPath' => null,
                'cleanup' => static function (?\Throwable &$operationError): void {
                },
            ];
        }

        if (!$provider->supportsDirectServerSideMove()) {
            throw new \RuntimeException("{$operation}: destination file exists already and can't rename");
        }

        $temporarySuffix ??= '.' . substr(bin2hex(random_bytes(4)), 0, 8);
        $savedPath = self::temporaryExistingPath($path, $temporarySuffix);
        try {
            $saved = $provider->directServerSideMoveTo($path, $provider, $savedPath);
        } catch (\RuntimeException $throwable) {
            throw new \RuntimeException(
                "{$operation}: failed to rename existing file: " . $throwable->getMessage(),
                0,
                $throwable,
            );
        }

        return [
            'existed' => true,
            'savedPath' => $saved->path,
            'cleanup' => static function (?\Throwable &$operationError) use ($provider, $saved, $path, $operation): void {
                if ($operationError === null) {
                    try {
                        $provider->delete($saved->path);
                    } catch (\RuntimeException $throwable) {
                        $operationError = new \RuntimeException(
                            "{$operation}: failed to remove renamed existing file: " . $throwable->getMessage(),
                            0,
                            $throwable,
                        );
                    }

                    return;
                }

                try {
                    $provider->directServerSideMoveTo($saved->path, $provider, $path);
                } catch (\RuntimeException) {
                    // Upstream logs restore failures and preserves the original operation error.
                }
            },
        ];
    }

    /**
     * Model provider Copy implementations that call RemoveExisting before
     * invoking a remote-side copy API that cannot overwrite safely.
     *
     * @param array{
     *     operation?: string,
     *     temporarySuffix?: string,
     *     guardCaseFoldSameRemote?: bool,
     *     precreateDestination?: bool,
     *     simulateCopyError?: bool|string,
     *     provider?: string,
     *     apiResult?: array<string, mixed>,
     *     providerError?: string|array<string, mixed>
     * } $options
     * @return array{copied: ObjectInfo, savedPath: ?string, precreatedPath: ?string, metadataRefresh: list<string>}
     */
    public function serverSideCopyReplace(
        MemoryProvider $provider,
        string $sourcePath,
        string $destinationPath,
        array $options = [],
    ): array {
        $sourcePath = self::normalizePath($sourcePath);
        $destinationPath = self::normalizePath($destinationPath);

        if (!$provider->supportsServerSideCopy()) {
            throw new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }

        $sourceInfo = $provider->info($sourcePath);
        if (
            (bool) ($options['guardCaseFoldSameRemote'] ?? false)
            && strtolower($sourcePath) === strtolower($destinationPath)
        ) {
            throw new \RuntimeException(
                sprintf('can\'t copy "%s" -> "%s" as are same name when lowercase', $sourcePath, $destinationPath),
            );
        }

        $operation = (string) ($options['operation'] ?? 'server side copy');
        $cleanup = $this->removeExisting(
            $provider,
            $destinationPath,
            $operation,
            $options['temporarySuffix'] ?? null,
        );

        $operationError = null;
        $copied = null;
        $metadataRefresh = [];
        try {
            if (array_key_exists('simulateCopyError', $options) && $options['simulateCopyError'] !== false) {
                $message = $options['simulateCopyError'] === true
                    ? 'server side copy failed'
                    : (string) $options['simulateCopyError'];
                throw new \RuntimeException($message);
            }
            if (array_key_exists('providerError', $options)) {
                throw $this->providerCopyFailure(
                    (string) ($options['provider'] ?? ''),
                    $destinationPath,
                    $options['providerError'],
                );
            }

            $copied = $provider->serverSideCopyTo($sourceInfo->path, $provider, $destinationPath);
            if (isset($options['provider'])) {
                $providerResult = $this->providerCopyResultOptions(
                    (string) $options['provider'],
                    $sourceInfo,
                    $options['apiResult'] ?? [],
                );
                $metadataRefresh = $providerResult['refresh'];
                $copied = $provider->updateObjectInfo($copied->path, $providerResult['options']);
            }
        } catch (\RuntimeException $throwable) {
            $operationError = $throwable;
        }

        $cleanupError = $operationError;
        $cleanup['cleanup']($cleanupError);
        if ($operationError !== null) {
            throw $operationError;
        }
        if ($cleanupError !== null) {
            throw $cleanupError;
        }

        return [
            'copied' => $copied,
            'savedPath' => $cleanup['savedPath'],
            'precreatedPath' => (bool) ($options['precreateDestination'] ?? false) ? $destinationPath : null,
            'metadataRefresh' => $metadataRefresh,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>}
     */
    private function providerCopyResultOptions(string $provider, ObjectInfo $sourceInfo, array $apiResult): array
    {
        return match (strtolower($provider)) {
            'dropbox' => $this->dropboxCopyResultOptions($sourceInfo, $apiResult),
            'onedrive' => $this->onedriveCopyResultOptions($sourceInfo, $apiResult),
            'yandex' => $this->yandexCopyResultOptions($sourceInfo, $apiResult),
            'sugarsync' => $this->sugarsyncCopyResultOptions($sourceInfo, $apiResult),
            default => throw new \InvalidArgumentException("unknown provider copy result profile: {$provider}"),
        };
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>}
     */
    private function dropboxCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $metadataType = strtolower($this->optionalString($apiResult['metadataType'] ?? $apiResult['metadata_type'] ?? 'file') ?? 'file');
        if ($metadataType !== 'file') {
            throw new \RuntimeException('is not a regular file');
        }

        $metadata = $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []);
        $contentHash = $this->optionalString($apiResult['contentHash'] ?? $apiResult['content_hash'] ?? null);
        if ($contentHash !== null && $contentHash !== '') {
            $metadata['dropbox_content_hash'] = strtolower($contentHash);
        }

        return [
            'options' => [
                'modTime' => $this->optionalString($apiResult['clientModified'] ?? $apiResult['client_modified'] ?? null) ?? $sourceInfo->modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $metadata,
                'id' => $this->optionalString($apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => $this->stringMetadata($apiResult['hashes'] ?? $sourceInfo->hashes),
            ],
            'refresh' => ['dropbox:relocation-result-metadata'],
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>}
     */
    private function onedriveCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $metadata = $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []);
        $refresh = ['onedrive:async-copy-job', 'onedrive:set-source-modtime'];
        if (isset($metadata['permissions'])) {
            $metadata['onedrive_permissions_mode'] = 'add-only';
            $refresh[] = 'onedrive:metadata-permissions-add-only';
        }

        return [
            'options' => [
                'modTime' => $sourceInfo->modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $metadata,
                'id' => $this->optionalString($apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => $this->onedriveHashes($apiResult) + $sourceInfo->hashes,
            ],
            'refresh' => $refresh,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>}
     */
    private function yandexCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $customProperties = is_array($apiResult['customProperties'] ?? null)
            ? $apiResult['customProperties']
            : [];
        $modTime = $this->optionalString($customProperties['rclone_modified'] ?? null)
            ?? $this->optionalString($apiResult['modified'] ?? null)
            ?? $sourceInfo->modTime;
        $hashes = $sourceInfo->hashes;
        $md5 = $this->optionalString($apiResult['md5'] ?? null);
        if ($md5 !== null && $md5 !== '') {
            $hashes[HashType::MD5] = strtolower($md5);
        }

        return [
            'options' => [
                'modTime' => $modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []),
                'id' => $this->optionalString($apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => $hashes,
            ],
            'refresh' => ['yandex:new-object-metadata-read'],
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>}
     */
    private function sugarsyncCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        return [
            'options' => [
                'modTime' => $this->optionalString($apiResult['lastModified'] ?? $apiResult['last_modified'] ?? null) ?? $sourceInfo->modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []),
                'id' => $this->optionalString($apiResult['location'] ?? $apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => [],
            ],
            'refresh' => ['sugarsync:metadata-read-after-copy'],
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array<string, string>
     */
    private function onedriveHashes(array $apiResult): array
    {
        $hashSource = is_array($apiResult['hashes'] ?? null) ? $apiResult['hashes'] : $apiResult;
        $hashes = [];
        foreach ([
            'sha1Hash' => HashType::SHA1,
            'sha256Hash' => HashType::SHA256,
            'crc32Hash' => HashType::CRC32,
        ] as $apiKey => $hashType) {
            $hash = $this->optionalString($hashSource[$apiKey] ?? null);
            if ($hash !== null && $hash !== '') {
                $hashes[$hashType] = strtolower($hash);
            }
        }

        return $hashes;
    }

    /**
     * @param mixed $metadata
     * @return array<string, string>
     */
    private function stringMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $strings = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $strings[(string) $key] = (string) $value;
            }
        }

        return $strings;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function providerCopyFailure(string $provider, string $destinationPath, mixed $failure): \RuntimeException
    {
        if (is_string($failure)) {
            return new \RuntimeException($failure);
        }
        if (!is_array($failure)) {
            return new \RuntimeException('server side copy failed');
        }

        $kind = (string) ($failure['kind'] ?? '');
        $message = (string) ($failure['message'] ?? 'server side copy failed');

        return match (strtolower($provider)) {
            'dropbox' => new \RuntimeException('copy failed: ' . $message),
            'onedrive' => $this->onedriveCopyFailure($destinationPath, $kind, $message, $failure),
            'yandex' => $this->yandexCopyFailure($kind, $message, $failure),
            'sugarsync' => $this->sugarsyncCopyFailure($message, $failure),
            default => new \RuntimeException($message),
        };
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function onedriveCopyFailure(string $destinationPath, string $kind, string $message, array $failure): \RuntimeException
    {
        if ($kind === 'async-access-denied') {
            return new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }
        if ($kind === 'missing-location') {
            return new \RuntimeException("didn't receive location header in copy response");
        }
        if ($kind === 'async-status-not-json') {
            $body = (string) ($failure['body'] ?? '');

            return new \RuntimeException(sprintf('async status result not JSON: %s: %s', json_encode($body), $message));
        }
        if ($kind === 'async-status') {
            $status = (string) ($failure['status'] ?? 'failed');

            return new \RuntimeException(sprintf('%s: async operation returned "%s"', $destinationPath, $status));
        }

        return new \RuntimeException($message);
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function yandexCopyFailure(string $kind, string $message, array $failure): \RuntimeException
    {
        if ($kind === 'async-info-not-json') {
            $body = (string) ($failure['body'] ?? '');

            return new \RuntimeException(sprintf('couldn\'t copy file: async info result not JSON: %s: %s', json_encode($body), $message));
        }
        if ($kind === 'async-failure') {
            return new \RuntimeException('couldn\'t copy file: async operation returned "failure"');
        }

        return new \RuntimeException('couldn\'t copy file: ' . $message);
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function sugarsyncCopyFailure(string $message, array $failure): \RuntimeException
    {
        if (($failure['kind'] ?? '') === 'html-error') {
            $statusCode = (int) ($failure['status'] ?? 500);
            $statusText = (string) ($failure['statusText'] ?? ($statusCode . ' Error'));

            return new \RuntimeException(sprintf('HTTP error %d (%s): %s', $statusCode, $statusText, $message));
        }

        return new \RuntimeException($message);
    }

    /**
     * @return array{usedDirMove: bool, fallbackReason: ?string, moved: list<ObjectInfo>}
     */
    public function moveDirectory(MemoryProvider $provider, string $sourceDir, string $targetDir): array
    {
        $sourceDir = self::normalizePath($sourceDir);
        $targetDir = self::normalizePath($targetDir);

        try {
            $moved = $provider->serverSideDirMove($sourceDir, $targetDir);

            return [
                'usedDirMove' => true,
                'fallbackReason' => null,
                'moved' => [$moved],
            ];
        } catch (\RuntimeException $throwable) {
            if (
                !MemoryProvider::isCantDirMoveException($throwable)
                && !MemoryProvider::isDirExistsException($throwable)
            ) {
                throw $throwable;
            }
            $fallbackReason = $throwable->getMessage();
        }

        $provider->directoryInfo($sourceDir);

        $directories = array_values(array_filter(
            $provider->directories($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));
        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($a->path) <=> self::pathLevel($b->path),
        );
        foreach ($directories as $directory) {
            $provider->mkdir(self::replacePathPrefix($directory->path, $sourceDir, $targetDir), [
                'modTime' => $directory->modTime,
                'mimeType' => $directory->mimeType,
                'metadata' => $directory->metadata,
                'id' => $directory->id,
            ]);
        }

        $objects = array_values(array_filter(
            $provider->list($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));

        $moved = [];
        foreach ($objects as $object) {
            $moved[] = $provider->serverSideMoveTo(
                $object->path,
                $provider,
                self::replacePathPrefix($object->path, $sourceDir, $targetDir),
            );
        }

        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($b->path) <=> self::pathLevel($a->path),
        );
        foreach ($directories as $directory) {
            try {
                $provider->rmdir($directory->path);
            } catch (\RuntimeException) {
            }
        }

        return [
            'usedDirMove' => false,
            'fallbackReason' => $fallbackReason,
            'moved' => $moved,
        ];
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    private function moveOrCopyFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        bool $copy,
        array $options,
    ): array {
        $destinationPath = self::normalizePath($destinationPath);
        $sourcePath = self::normalizePath($sourcePath);
        $result = $this->fileOperationResult();

        if ($source === $destination && $sourcePath === $destinationPath) {
            $result['skipped'] = true;

            return $result;
        }

        $sourceInfo = $source->info($sourcePath);
        if (!$copy && $this->needsCaseInsensitiveFileMove($destination, $source, $destinationPath, $sourcePath)) {
            $result['moved'] = $this->moveCaseInsensitiveFile($destination, $destinationPath, $sourcePath);
            $result['caseInsensitiveMove'] = true;

            return $result;
        }

        $noCheckDest = (bool) ($options['noCheckDest'] ?? false);
        $targetInfo = $noCheckDest ? null : $this->optionalInfo($destination, $destinationPath);
        if (!$noCheckDest && $targetInfo !== null && (bool) ($options['ignoreExisting'] ?? false)) {
            $result['skipped'] = true;

            return $result;
        }

        $needsTransfer = $this->needsTransfer(
            $source,
            $destination,
            $sourceInfo,
            $targetInfo,
            (bool) ($options['ignoreTimes'] ?? false),
            (bool) ($options['updateOlder'] ?? false),
            (bool) ($options['noUpdateModTime'] ?? false),
            (int) ($options['modifyWindowSeconds'] ?? 1),
            (bool) ($options['checksum'] ?? false),
            (bool) ($options['immutable'] ?? false),
            (bool) ($options['refreshTimes'] ?? false),
        );

        if (!$needsTransfer) {
            if (!$copy && $targetInfo !== null && !$this->sameProviderObject($source, $sourceInfo, $destination, $targetInfo)) {
                $result['deletedSource'] = $source->delete($sourceInfo->path);
            } else {
                $result['skipped'] = true;
            }

            return $result;
        }

        if ($this->findEqualReference($sourceInfo, $targetInfo, $options['compareDest'] ?? []) !== null) {
            if (!$copy && $targetInfo !== null && !$this->sameProviderObject($source, $sourceInfo, $destination, $targetInfo)) {
                $result['deletedSource'] = $source->delete($sourceInfo->path);
            } else {
                $result['skipped'] = true;
            }

            return $result;
        }

        $backup = $options['backup'] ?? null;
        $backupPrefix = (string) ($options['backupPrefix'] ?? '');
        $suffix = (string) ($options['suffix'] ?? '');
        $suffixKeepExtension = (bool) ($options['suffixKeepExtension'] ?? false);
        $copyDestReference = $this->findEqualReference($sourceInfo, $targetInfo, $options['copyDest'] ?? []);
        if ($copyDestReference !== null) {
            if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                $result['backup'] = $this->moveToBackup(
                    $destination,
                    $targetInfo->path,
                    $backup,
                    $backupPrefix,
                    $suffix,
                    $suffixKeepExtension,
                );
                $targetInfo = null;
            }
            $copied = $copyDestReference['provider']->copyTo(
                $copyDestReference['path'],
                $destination,
                $targetInfo?->path ?? $destinationPath,
            );
            if ($copy) {
                $result['copied'] = $copied;
            } else {
                $result['moved'] = $copied;
                if (!$this->sameProviderObject($source, $sourceInfo, $destination, $copied)) {
                    $result['deletedSource'] = $source->delete($sourceInfo->path);
                }
            }

            return $result;
        }

        if ($targetInfo !== null && (bool) ($options['immutable'] ?? false)) {
            throw new \RuntimeException('immutable file modified');
        }

        if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
            $result['backup'] = $this->moveToBackup(
                $destination,
                $targetInfo->path,
                $backup,
                $backupPrefix,
                $suffix,
                $suffixKeepExtension,
            );
            $targetInfo = null;
        }

        if ($copy) {
            try {
                $copyResult = $this->copyFileObject(
                    $source,
                    $sourceInfo->path,
                    $destination,
                    $targetInfo?->path ?? $destinationPath,
                    $options,
                );
            } catch (\RuntimeException $throwable) {
                $result['cleanedPartial'] = true;
                throw $throwable;
            }
            $result['copied'] = $copyResult['object'];
            $result['partialPath'] = $copyResult['partialPath'];

            return $result;
        }

        if ($source === $destination) {
            $result['moved'] = $source->serverSideMoveTo($sourceInfo->path, $destination, $targetInfo?->path ?? $destinationPath);

            return $result;
        }

        try {
            $copyResult = $this->copyFileObject(
                $source,
                $sourceInfo->path,
                $destination,
                $targetInfo?->path ?? $destinationPath,
                $options,
            );
        } catch (\RuntimeException $throwable) {
            $result['cleanedPartial'] = true;
            throw $throwable;
        }
        $result['moved'] = $copyResult['object'];
        $result['partialPath'] = $copyResult['partialPath'];
        if (!$this->sameProviderObject($source, $sourceInfo, $destination, $copyResult['object'])) {
            $result['deletedSource'] = $source->delete($sourceInfo->path);
        }

        return $result;
    }

    /**
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    private function fileOperationResult(): array
    {
        return [
            'copied' => null,
            'moved' => null,
            'deletedSource' => null,
            'backup' => null,
            'skipped' => false,
            'caseInsensitiveMove' => false,
            'partialPath' => null,
            'cleanedPartial' => false,
        ];
    }

    private function needsCaseInsensitiveFileMove(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
    ): bool {
        return $source === $destination
            && $destination->isCaseInsensitive()
            && $destinationPath !== $sourcePath
            && strtolower($destinationPath) === strtolower($sourcePath);
    }

    private function moveCaseInsensitiveFile(MemoryProvider $provider, string $destinationPath, string $sourcePath): ObjectInfo
    {
        $temporaryPath = $destinationPath . '-rclone-move-' . substr(hash('sha256', $sourcePath . "\0" . $destinationPath), 0, 8);
        if ($this->optionalInfo($provider, $temporaryPath) !== null) {
            throw new \RuntimeException('found an already existing file with a randomly generated name. Try the operation again');
        }

        $temporary = $provider->serverSideMoveTo($sourcePath, $provider, $temporaryPath);

        return $provider->serverSideMoveTo($temporary->path, $provider, $destinationPath);
    }

    /**
     * @param array{partialUploads?: bool, partialSuffix?: string, simulatePartialTransferError?: bool} $options
     * @return array{object: ObjectInfo, partialPath: ?string}
     */
    private function copyFileObject(
        MemoryProvider $source,
        string $sourcePath,
        MemoryProvider $destination,
        string $destinationPath,
        array $options,
    ): array {
        $partialPath = null;
        $copyPath = $destinationPath;
        if (
            (bool) ($options['partialUploads'] ?? false)
            && $destination->supportsDirectServerSideMove()
            && !str_ends_with($destinationPath, '.rclonelink')
        ) {
            $partialSuffix = (string) ($options['partialSuffix'] ?? '.partial');
            if (strlen($partialSuffix) > 16) {
                throw new \RuntimeException('expecting length of --partial-suffix to be not greater than 16 but got ' . strlen($partialSuffix));
            }
            $partialPath = $this->partialCopyPath($destinationPath, $source->info($sourcePath), $partialSuffix);
            $copyPath = $partialPath;
        }

        $copied = $source->copyTo($sourcePath, $destination, $copyPath);
        if ((bool) ($options['simulatePartialTransferError'] ?? false)) {
            if ($partialPath !== null) {
                $destination->delete($partialPath);
            }
            throw new \RuntimeException('failed to copy: simulated partial transfer error');
        }

        if ($partialPath !== null && $partialPath !== $destinationPath) {
            $copied = $destination->serverSideMoveTo($partialPath, $destination, $destinationPath);
        }

        return [
            'object' => $copied,
            'partialPath' => $partialPath,
        ];
    }

    private function partialCopyPath(string $destinationPath, ObjectInfo $sourceInfo, string $partialSuffix): string
    {
        $suffix = sprintf(
            '.%08x%s',
            crc32($destinationPath . "\0" . $sourceInfo->size . "\0" . $sourceInfo->sha256),
            $partialSuffix,
        );
        $base = self::pathBase($destinationPath);
        if (strlen($base) <= 100) {
            return $destinationPath . $suffix;
        }

        return substr($destinationPath, 0, max(0, strlen($destinationPath) - strlen($suffix))) . $suffix;
    }

    private static function temporaryExistingPath(string $path, string $suffix): string
    {
        $base = self::pathBase($path);
        if (strlen($base) <= 100) {
            return $path . $suffix;
        }

        return self::truncateValidUtf8($path, max(0, strlen($path) - strlen($suffix))) . $suffix;
    }

    private static function truncateValidUtf8(string $value, int $bytes): string
    {
        $truncated = substr($value, 0, $bytes);
        if (@preg_match('//u', $value) !== 1) {
            return $truncated;
        }

        while ($truncated !== '' && @preg_match('//u', $truncated) !== 1) {
            $truncated = substr($truncated, 0, -1);
        }

        return $truncated;
    }

    private function sameProviderObject(
        MemoryProvider $source,
        ObjectInfo $sourceInfo,
        MemoryProvider $destination,
        ObjectInfo $targetInfo,
    ): bool {
        if ($source === $destination) {
            if ($sourceInfo->path === $targetInfo->path) {
                return true;
            }
            if ($source->isCaseInsensitive() && strtolower($sourceInfo->path) === strtolower($targetInfo->path)) {
                return true;
            }
        }

        return $sourceInfo->id !== null
            && $sourceInfo->id !== ''
            && $sourceInfo->id === $targetInfo->id;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed>|string $chooser
     * @param array<string, mixed> $context
     * @return array{action: string, keepIndex: ?int}
     */
    private function interactiveDedupeDecision(callable $chooser, array $context, int $count, bool $byHash): array
    {
        $choice = $chooser($context);
        $keep = null;
        if (is_string($choice)) {
            $action = $choice;
        } elseif (is_array($choice)) {
            $action = (string) ($choice['action'] ?? $choice['command'] ?? '');
            if (array_key_exists('keep', $choice)) {
                $keep = (int) $choice['keep'];
            } elseif (array_key_exists('keepNumber', $choice)) {
                $keep = (int) $choice['keepNumber'];
            } elseif (array_key_exists('index', $choice)) {
                $keep = (int) $choice['index'] + 1;
            }
        } else {
            throw new \InvalidArgumentException('interactive dedupe choice must be a command string or decision array');
        }

        $action = match (strtolower(trim($action))) {
            's', 'skip' => 'skip',
            'k', 'keep' => 'keep',
            'r', 'rename' => 'rename',
            'q', 'quit' => 'quit',
            default => throw new \InvalidArgumentException('unknown interactive dedupe choice "' . (string) $action . '"'),
        };

        if ($action === 'rename' && $byHash) {
            throw new \InvalidArgumentException('interactive dedupe by hash does not offer rename');
        }
        if ($action !== 'keep') {
            return [
                'action' => $action,
                'keepIndex' => null,
            ];
        }
        if ($keep === null) {
            throw new \InvalidArgumentException('interactive keep choice requires a 1-based keep number');
        }
        if ($keep < 1 || $keep > $count) {
            throw new \OutOfRangeException("interactive keep number {$keep} is outside 1..{$count}");
        }

        return [
            'action' => 'keep',
            'keepIndex' => $keep - 1,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return array{kept: ObjectInfo, deleted: list<ObjectInfo>}
     */
    private function deleteDedupeObjectsExcept(MemoryProvider $provider, array $objects, int $keepIndex): array
    {
        if (!isset($objects[$keepIndex])) {
            throw new \OutOfRangeException('dedupe keep index is outside the duplicate group');
        }

        $deleted = [];
        foreach ($objects as $index => $info) {
            if ($index === $keepIndex) {
                continue;
            }
            $deleted[] = $provider->deleteListedObject($info);
        }

        return [
            'kept' => $objects[$keepIndex],
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return list<ObjectInfo>
     */
    private function dedupeOrderedObjects(array $objects, string $mode): array
    {
        $ordered = $objects;
        if ($mode === DeduplicateMode::NEWEST || $mode === DeduplicateMode::OLDEST) {
            usort(
                $ordered,
                fn (ObjectInfo $a, ObjectInfo $b): int => $this->dedupeObjectTimestamp($a) <=> $this->dedupeObjectTimestamp($b)
                    ?: $a->path <=> $b->path,
            );
        } elseif ($mode === DeduplicateMode::LARGEST || $mode === DeduplicateMode::SMALLEST) {
            usort(
                $ordered,
                static fn (ObjectInfo $a, ObjectInfo $b): int => $a->size <=> $b->size
                    ?: $a->path <=> $b->path,
            );
        }

        return $ordered;
    }

    private function dedupeObjectTimestamp(ObjectInfo $info): float
    {
        return $this->timestamp($info->modTime) ?? 0.0;
    }

    private function dedupeEntryId(ObjectInfo $info): string
    {
        return $info->id !== null && $info->id !== '' ? $info->id : $info->path;
    }

    private function dedupeEntryParentId(ObjectInfo $info): string
    {
        return $info->parentId !== null && $info->parentId !== '' ? $info->parentId : self::parentPath($info->path);
    }

    /**
     * @param array<string, array{directory: ?ObjectInfo, parent: string, count: int}> $dirsById
     */
    private function incrementDedupeDirectoryCount(array &$dirsById, string $parent): void
    {
        while ($parent !== '') {
            $dirsById[$parent] ??= [
                'directory' => null,
                'parent' => '',
                'count' => 0,
            ];
            $dirsById[$parent]['count']++;
            $parent = $dirsById[$parent]['parent'];
        }
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return array{remaining: list<ObjectInfo>, deleted: list<ObjectInfo>}
     */
    private function deleteIdenticalDuplicateNames(MemoryProvider $provider, array $objects, bool $sizeOnly): array
    {
        $idCounts = [];
        foreach ($objects as $info) {
            if ($info->id !== null && $info->id !== '') {
                $idCounts[$info->id] = ($idCounts[$info->id] ?? 0) + 1;
            }
        }

        $eligible = [];
        foreach ($objects as $info) {
            if ($info->id !== null && $info->id !== '' && ($idCounts[$info->id] ?? 0) > 1) {
                continue;
            }
            $eligible[] = $info;
        }

        $hashType = $provider->supportedHashes()->getOne();
        $groups = [];
        $remaining = [];
        foreach ($eligible as $info) {
            $identity = '';
            if ($sizeOnly && $info->size >= 0) {
                $identity = 'size ' . $info->size;
            } elseif ($hashType !== HashType::NONE) {
                $hash = $provider->hashesForObject($info, new HashSet($hashType))[$hashType] ?? '';
                if ($hash !== '') {
                    $identity = $hashType . ' ' . $hash;
                }
            }

            if ($identity === '') {
                $remaining[] = $info;
                continue;
            }

            $groups[$identity][] = $info;
        }

        ksort($groups, SORT_STRING);
        $deleted = [];
        foreach ($groups as $duplicates) {
            $remaining[] = $duplicates[0];
            for ($index = 1; $index < count($duplicates); $index++) {
                $deleted[] = $provider->deleteListedObject($duplicates[$index]);
            }
        }

        usort(
            $remaining,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        return [
            'remaining' => $remaining,
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return list<ObjectInfo>
     */
    private function renameDuplicateNames(MemoryProvider $provider, string $path, array $objects): array
    {
        [$base, $extension] = $this->splitRemoteExtension($path);
        $renamed = [];
        foreach ($objects as $index => $info) {
            $suffix = 1;
            do {
                if ($suffix > 100) {
                    throw new \RuntimeException("Could not find an available new name for {$path}");
                }
                $newName = sprintf('%s-%d%s', $base, $index + $suffix, $extension);
                $suffix++;
            } while ($provider->pathExists($newName));

            $renamed[] = $provider->renameListedObject($info, $newName);
        }

        return $renamed;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRemoteExtension(string $path): array
    {
        $slash = strrpos($path, '/');
        $leafStart = $slash === false ? 0 : $slash + 1;
        $dot = strrpos($path, '.');
        if ($dot === false || $dot < $leafStart) {
            return [$path, ''];
        }

        return [substr($path, 0, $dot), substr($path, $dot)];
    }

    /**
     * @return list<ObjectInfo>
     */
    public function fixCase(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $immutable = false,
    ): array {
        if ($immutable || !$target->isCaseInsensitive()) {
            return [];
        }

        $renamed = [];
        $sourceDirs = $source->directories();
        usort(
            $sourceDirs,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($a->path) <=> self::pathLevel($b->path),
        );

        foreach ($sourceDirs as $sourceDir) {
            if ($sourceDir->path === '' || !$this->filterAllowsDirectory($source, $sourceDir->path, $filter)) {
                continue;
            }

            try {
                $targetDir = $target->directoryInfo($sourceDir->path);
            } catch (\RuntimeException) {
                continue;
            }

            if ($this->samePathDifferentCase($sourceDir->path, $targetDir->path)) {
                $renamed[] = $target->renameDirectory($targetDir->path, $sourceDir->path);
            }
        }

        foreach ($source->list() as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }

            try {
                $targetInfo = $target->info($sourceInfo->path);
            } catch (\RuntimeException) {
                continue;
            }

            if ($this->samePathDifferentCase($sourceInfo->path, $targetInfo->path)) {
                $renamed[] = $target->renameObject($targetInfo->path, $sourceInfo->path);
            }
        }

        return $renamed;
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
        bool $ignoreCaseSync = false,
    ): array {
        $deleteMode = DeleteMode::normalize($deleteMode);
        if ($deleteMode === DeleteMode::OFF) {
            return [];
        }

        $sourcePaths = $this->listedPaths($source, $filter, $ignoreCaseSync);
        $targetPaths = $this->listedPaths($target, $deleteExcluded ? null : $filter, $ignoreCaseSync);
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
        bool $ignoreCaseSync = false,
    ): array {
        $deleted = [];
        $deleteCount = 0;
        $deleteBytes = 0;
        foreach ($this->deletePaths($source, $target, $filter, $deleteMode, $deleteExcluded, $ignoreCaseSync) as $path) {
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
    private function listedPaths(MemoryProvider $provider, ?FilterRuleSet $filter, bool $ignoreCaseSync = false): array
    {
        $paths = [];
        foreach ($provider->list() as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $key = $ignoreCaseSync ? $this->syncPathKey($info->path) : $info->path;
            if (!isset($paths[$key])) {
                $paths[$key] = $info;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, true> $seen
     */
    private function skipCaseFoldedDuplicate(string $path, array &$seen, bool $ignoreCaseSync): bool
    {
        if (!$ignoreCaseSync) {
            return false;
        }

        $key = $this->syncPathKey($path);
        if (isset($seen[$key])) {
            return true;
        }

        $seen[$key] = true;

        return false;
    }

    private function syncPathKey(string $path): string
    {
        $path = self::normalizePath($path);

        return function_exists('mb_strtolower') ? mb_strtolower($path, 'UTF-8') : strtolower($path);
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

    private function filterAllowsDirectory(MemoryProvider $source, string $dir, ?FilterRuleSet $filter): bool
    {
        if ($filter === null || $filter->includes($dir)) {
            return true;
        }

        foreach ($source->list() as $info) {
            if (self::pathUnderPrefix($info->path, $dir) && $filter->includes($info->path)) {
                return true;
            }
        }

        return false;
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

    private function trackRenamesDisabledReason(
        MemoryProvider $source,
        MemoryProvider $target,
        TrackRenamesStrategy $strategy,
    ): ?string {
        if (!$target->supportsServerSideMove()) {
            return 'destination does not support server-side move or copy';
        }
        if ($strategy->usesHash() && $this->commonHashType($source, $target) === null) {
            return 'source and destination do not have a common hash';
        }

        return null;
    }

    private function commonHashType(MemoryProvider $source, MemoryProvider $target): ?string
    {
        $commonHashes = $source->supportedHashes()->overlap($target->supportedHashes());
        if ($commonHashes->count() === 0) {
            return null;
        }

        return $commonHashes->getOne();
    }

    /**
     * @param list<ObjectInfo> $sourceOnly
     * @param list<ObjectInfo> $targetOnly
     * @return array<string, list<ObjectInfo>>
     */
    private function buildRenameMap(
        array $sourceOnly,
        array $targetOnly,
        MemoryProvider $target,
        TrackRenamesStrategy $strategy,
        ?string $hashType,
    ): array {
        $possibleSizes = [];
        foreach ($sourceOnly as $sourceInfo) {
            $possibleSizes[$sourceInfo->size] = true;
        }

        $renameMap = [];
        foreach ($targetOnly as $targetInfo) {
            if (!isset($possibleSizes[$targetInfo->size])) {
                continue;
            }

            $renameId = $this->trackRenameId($target, $targetInfo, $strategy, $hashType);
            if ($renameId === '') {
                continue;
            }

            $renameMap[$renameId][] = $targetInfo;
        }

        return $renameMap;
    }

    private function trackRenameId(
        MemoryProvider $provider,
        ObjectInfo $info,
        TrackRenamesStrategy $strategy,
        ?string $hashType,
    ): string {
        $id = (string) $info->size;
        if ($strategy->usesHash()) {
            if ($hashType === null) {
                return '';
            }
            $hash = $provider->hashes($info->path, new HashSet($hashType))[$hashType] ?? '';
            if ($hash === '') {
                return '';
            }
            $id .= ',' . $hash;
        }
        if ($strategy->usesLeaf()) {
            $id .= ',' . self::pathBase($info->path);
        }

        return $id;
    }

    /**
     * @param array<string, list<ObjectInfo>> $renameMap
     */
    private function popRenameCandidate(
        array &$renameMap,
        string $renameId,
        ObjectInfo $sourceInfo,
        TrackRenamesStrategy $strategy,
        int $modifyWindowSeconds,
    ): ?ObjectInfo {
        if ($renameId === '' || !isset($renameMap[$renameId]) || $renameMap[$renameId] === []) {
            return null;
        }

        $index = 0;
        if ($strategy->usesModTime()) {
            $index = null;
            foreach ($renameMap[$renameId] as $candidateIndex => $targetInfo) {
                $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
                if ($dt !== null && $this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
                    $index = $candidateIndex;
                    break;
                }
            }
            if ($index === null) {
                return null;
            }
        }

        $candidate = $renameMap[$renameId][$index];
        array_splice($renameMap[$renameId], $index, 1);
        if ($renameMap[$renameId] === []) {
            unset($renameMap[$renameId]);
        }

        return $candidate;
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

        return $target->serverSideMoveTo(
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

    private static function pathBase(string $path): string
    {
        $path = self::normalizePath($path);
        if (!str_contains($path, '/')) {
            return $path;
        }

        return substr($path, strrpos($path, '/') + 1);
    }

    private static function replacePathPrefix(string $path, string $sourcePrefix, string $targetPrefix): string
    {
        $path = self::normalizePath($path);
        $sourcePrefix = self::normalizePath($sourcePrefix);
        $targetPrefix = self::normalizePath($targetPrefix);
        if ($path === $sourcePrefix) {
            return $targetPrefix;
        }

        return $targetPrefix . substr($path, strlen($sourcePrefix));
    }

    private static function pathLevel(string $path): int
    {
        $path = self::normalizePath($path);

        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private function samePathDifferentCase(string $left, string $right): bool
    {
        $left = self::normalizePath($left);
        $right = self::normalizePath($right);

        return $left !== $right && strtolower($left) === strtolower($right);
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
