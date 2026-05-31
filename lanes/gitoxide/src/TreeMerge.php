<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeMerge
{
    public static function mergeFlat(Tree $base, Tree $ours, Tree $theirs): TreeMergeResult
    {
        $baseEntries = self::entriesByName($base);
        $ourEntries = self::entriesByName($ours);
        $theirEntries = self::entriesByName($theirs);

        [$renameConflicts, $consumedPaths, $renameMerged] = self::renameConflicts($baseEntries, $ourEntries, $theirEntries, '');
        $paths = array_keys($baseEntries + $ourEntries + $theirEntries);
        sort($paths, SORT_STRING);

        $merged = $renameMerged;
        $conflicts = $renameConflicts;
        foreach ($paths as $path) {
            if (isset($consumedPaths[$path])) {
                continue;
            }
            $baseEntry = $baseEntries[$path] ?? null;
            $ourEntry = $ourEntries[$path] ?? null;
            $theirEntry = $theirEntries[$path] ?? null;

            if (self::sameEntry($ourEntry, $theirEntry)) {
                if ($ourEntry !== null) {
                    $merged[] = $ourEntry;
                }
                continue;
            }

            $oursChanged = !self::sameEntry($baseEntry, $ourEntry);
            $theirsChanged = !self::sameEntry($baseEntry, $theirEntry);

            if (!$oursChanged && !$theirsChanged) {
                if ($baseEntry !== null) {
                    $merged[] = $baseEntry;
                }
                continue;
            }

            if ($oursChanged && !$theirsChanged) {
                if ($ourEntry !== null) {
                    $merged[] = $ourEntry;
                }
                continue;
            }

            if (!$oursChanged && $theirsChanged) {
                if ($theirEntry !== null) {
                    $merged[] = $theirEntry;
                }
                continue;
            }

            $conflicts[] = new TreeMergeConflict(
                $path,
                self::conflictReason($baseEntry, $ourEntry, $theirEntry),
                $baseEntry,
                $ourEntry,
                $theirEntry,
            );
            if ($baseEntry !== null) {
                $merged[] = $baseEntry;
            }
        }

        self::sortEntries($merged);

        return new TreeMergeResult(new Tree($merged), $conflicts);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    public static function mergeRecursive(
        Tree $base,
        Tree $ours,
        Tree $theirs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle = BlobMerge::STYLE_MERGE,
        ?int $bigFileThreshold = null,
    ): TreeMergeResult {
        if ($bigFileThreshold !== null && $bigFileThreshold < 1) {
            throw new \InvalidArgumentException('Big file threshold must be positive');
        }

        return self::mergeRecursiveAt($base, $ours, $theirs, $readObject, $writeObject, $conflictStyle, '', [], $bigFileThreshold);
    }

    /**
     * @param list<Tree> $mergeBases
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    public static function mergeRecursiveWithVirtualBase(
        Tree $mergeBaseAncestor,
        array $mergeBases,
        Tree $ours,
        Tree $theirs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle = BlobMerge::STYLE_MERGE,
        ?int $bigFileThreshold = null,
    ): TreeMergeResult {
        if ($mergeBases === []) {
            throw new \InvalidArgumentException('At least one merge-base tree is required');
        }

        foreach ($mergeBases as $mergeBase) {
            if (!$mergeBase instanceof Tree) {
                throw new \InvalidArgumentException('Merge-base entries must be Tree instances');
            }
        }

        $virtualBase = array_shift($mergeBases);
        foreach ($mergeBases as $mergeBase) {
            $virtualBaseMerge = self::mergeRecursive(
                $mergeBaseAncestor,
                $virtualBase,
                $mergeBase,
                $readObject,
                $writeObject,
                BlobMerge::STYLE_OURS,
                $bigFileThreshold,
            );
            if (!$virtualBaseMerge->isClean()) {
                throw new \RuntimeException('Virtual merge-base construction left unresolved conflicts');
            }

            $virtualBase = $virtualBaseMerge->tree;
        }

        return self::mergeRecursive(
            $virtualBase,
            $ours,
            $theirs,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function mergeRecursiveAt(
        Tree $base,
        Tree $ours,
        Tree $theirs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        string $pathPrefix,
        array $unionMergePatterns = [],
        ?int $bigFileThreshold = null,
    ): TreeMergeResult {
        $baseEntries = self::entriesByName($base);
        $ourEntries = self::entriesByName($ours);
        $theirEntries = self::entriesByName($theirs);
        $unionMergePatterns = self::unionMergeAttributePatterns($baseEntries, $ourEntries, $theirEntries, $readObject, $pathPrefix, $unionMergePatterns);

        [$renameConflicts, $consumedPaths, $renameMerged] = self::renameConflicts($baseEntries, $ourEntries, $theirEntries, $pathPrefix, $readObject, $writeObject, $conflictStyle, $bigFileThreshold);
        $paths = array_keys($baseEntries + $ourEntries + $theirEntries);
        sort($paths, SORT_STRING);

        $merged = $renameMerged;
        $conflicts = $renameConflicts;
        foreach ($paths as $path) {
            if (isset($consumedPaths[$path])) {
                continue;
            }
            $baseEntry = $baseEntries[$path] ?? null;
            $ourEntry = $ourEntries[$path] ?? null;
            $theirEntry = $theirEntries[$path] ?? null;
            $fullPath = self::joinPath($pathPrefix, $path);

            if (self::sameEntry($ourEntry, $theirEntry)) {
                if ($ourEntry !== null) {
                    $merged[] = $ourEntry;
                }
                continue;
            }

            $oursChanged = !self::sameEntry($baseEntry, $ourEntry);
            $theirsChanged = !self::sameEntry($baseEntry, $theirEntry);

            if (!$oursChanged && !$theirsChanged) {
                if ($baseEntry !== null) {
                    $merged[] = $baseEntry;
                }
                continue;
            }

            if ($oursChanged && !$theirsChanged) {
                if ($ourEntry !== null) {
                    $merged[] = $ourEntry;
                }
                continue;
            }

            if (!$oursChanged && $theirsChanged) {
                if ($theirEntry !== null) {
                    $merged[] = $theirEntry;
                }
                continue;
            }

            $addedDirectoryFile = self::tryMergeAddedDirectoryFileConflict($path, $fullPath, $baseEntry, $ourEntry, $theirEntry);
            if ($addedDirectoryFile !== null) {
                array_push($merged, ...$addedDirectoryFile['merged']);
                $conflicts[] = $addedDirectoryFile['conflict'];
                continue;
            }

            $addedBlobConflict = self::tryMergeAddedBlobConflict($path, $fullPath, $baseEntry, $ourEntry, $theirEntry, $readObject, $writeObject, $conflictStyle, $bigFileThreshold);
            if ($addedBlobConflict !== null) {
                $merged[] = $addedBlobConflict['entry'];
                array_push($conflicts, ...$addedBlobConflict['conflicts']);
                continue;
            }

            $addedSymlinkConflict = self::tryMergeAddedSymlinkConflict($path, $fullPath, $baseEntry, $ourEntry, $theirEntry);
            if ($addedSymlinkConflict !== null) {
                $merged[] = $addedSymlinkConflict['entry'];
                $conflicts[] = $addedSymlinkConflict['conflict'];
                continue;
            }

            $deleteModify = self::tryMergeDeleteModifyEntry($path, $fullPath, $baseEntry, $ourEntry, $theirEntry);
            if ($deleteModify !== null) {
                $merged[] = $deleteModify['entry'];
                $conflicts[] = $deleteModify['conflict'];
                continue;
            }

            $fileDirectory = self::tryMergeFileToDirectoryConflict($path, $fullPath, $baseEntry, $ourEntry, $theirEntry, $readObject, $writeObject, $conflictStyle, $bigFileThreshold);
            if ($fileDirectory !== null) {
                array_push($merged, ...$fileDirectory['merged']);
                array_push($conflicts, ...$fileDirectory['conflicts']);
                continue;
            }

            $treeFile = self::tryMergeTreeToFileConflict($path, $fullPath, $baseEntry, $ourEntry, $theirEntry, $readObject, $writeObject, $conflictStyle);
            if ($treeFile !== null) {
                array_push($merged, ...$treeFile['merged']);
                array_push($conflicts, ...$treeFile['conflicts']);
                continue;
            }

            $contentMerge = self::tryMergeChangedEntry($path, $fullPath, $baseEntry, $ourEntry, $theirEntry, $readObject, $writeObject, $conflictStyle, $unionMergePatterns, $bigFileThreshold);
            if ($contentMerge !== null) {
                if ($contentMerge['entry'] !== null) {
                    $merged[] = $contentMerge['entry'];
                }
                array_push($conflicts, ...$contentMerge['conflicts']);
                continue;
            }

            $conflicts[] = new TreeMergeConflict(
                $fullPath,
                self::conflictReason($baseEntry, $ourEntry, $theirEntry),
                $baseEntry,
                $ourEntry,
                $theirEntry,
            );
            if ($baseEntry !== null) {
                $merged[] = $baseEntry;
            }
        }

        self::sortEntries($merged);

        return new TreeMergeResult(new Tree($merged), $conflicts);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{entry:?TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeChangedEntry(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        array $unionMergePatterns = [],
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($baseEntry === null || $ourEntry === null || $theirEntry === null) {
            return null;
        }

        if ($baseEntry->isTree() && $ourEntry->isTree() && $theirEntry->isTree()) {
            $result = self::mergeRecursiveAt(
                Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')),
                Tree::fromObject(self::readTypedObject($readObject, $ourEntry->oid, 'tree')),
                Tree::fromObject(self::readTypedObject($readObject, $theirEntry->oid, 'tree')),
                $readObject,
                $writeObject,
                $conflictStyle,
                $fullPath,
                $unionMergePatterns,
                $bigFileThreshold,
            );

            return [
                'entry' => new TreeEntry($ourEntry->mode, $path, $writeObject($result->tree->toObject())),
                'conflicts' => $result->conflicts,
            ];
        }

        if (($baseEntry->isBlob() || $baseEntry->isLink()) && $ourEntry->isLink() && $theirEntry->isLink()) {
            return [
                'entry' => new TreeEntry($ourEntry->mode, $path, $ourEntry->oid),
                'conflicts' => [new TreeMergeConflict($fullPath, 'content-conflict', $baseEntry, $ourEntry, $theirEntry)],
            ];
        }

        if (!$baseEntry->isBlob() || !$ourEntry->isBlob() || !$theirEntry->isBlob()) {
            return null;
        }

        $mergedMode = self::mergeBlobMode($baseEntry, $ourEntry, $theirEntry);
        if ($mergedMode === null) {
            return null;
        }

        $baseBlob = self::readTypedObject($readObject, $baseEntry->oid, 'blob');
        $ourBlob = self::readTypedObject($readObject, $ourEntry->oid, 'blob');
        $theirBlob = self::readTypedObject($readObject, $theirEntry->oid, 'blob');
        $merge = self::shouldUseBinaryMerge($baseBlob->body, $ourBlob->body, $theirBlob->body, $bigFileThreshold)
            ? BlobMerge::mergeBinary($baseBlob->body, $ourBlob->body, $theirBlob->body)
            : BlobMerge::mergeText(
                $baseBlob->body,
                $ourBlob->body,
                $theirBlob->body,
                self::usesUnionMerge($fullPath, $unionMergePatterns) ? BlobMerge::STYLE_UNION : $conflictStyle,
                'base/' . $fullPath,
                'ours/' . $fullPath,
                'theirs/' . $fullPath,
            );

        $conflicts = [];
        if (!$merge->isClean()) {
            $conflicts[] = new TreeMergeConflict($fullPath, 'content-conflict', $baseEntry, $ourEntry, $theirEntry);
        }

        return [
            'entry' => new TreeEntry($mergedMode, $path, $writeObject(new GitObject('blob', $merge->content))),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return null|array{merged:list<TreeEntry>,conflict:TreeMergeConflict}
     */
    private static function tryMergeAddedDirectoryFileConflict(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
    ): ?array {
        if ($baseEntry !== null || $ourEntry === null || $theirEntry === null) {
            return null;
        }
        if ($ourEntry->isTree() === $theirEntry->isTree()) {
            return null;
        }

        $fileIsOurs = !$ourEntry->isTree();
        $directoryEntry = $fileIsOurs ? $theirEntry : $ourEntry;
        $fileEntry = $fileIsOurs ? $ourEntry : $theirEntry;
        if (!$directoryEntry->isTree() || $fileEntry->isTree()) {
            return null;
        }

        $relocatedName = $path . ($fileIsOurs ? '~A' : '~B');
        $relocatedPath = $fullPath . ($fileIsOurs ? '~A' : '~B');
        $fileRelocated = new TreeEntry($fileEntry->mode, $relocatedName, $fileEntry->oid);

        return [
            'merged' => [
                new TreeEntry($directoryEntry->mode, $path, $directoryEntry->oid),
                $fileRelocated,
            ],
            'conflict' => new TreeMergeConflict(
                $relocatedPath,
                'directory-file',
                null,
                $fileIsOurs ? $fileRelocated : null,
                $fileIsOurs ? null : $fileRelocated,
            ),
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeAddedBlobConflict(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($baseEntry !== null || $ourEntry === null || $theirEntry === null || !$ourEntry->isBlob() || !$theirEntry->isBlob()) {
            return null;
        }
        $mergedMode = self::mergeAddedBlobMode($ourEntry, $theirEntry);
        if ($mergedMode === null) {
            return null;
        }

        $ourBlob = self::readTypedObject($readObject, $ourEntry->oid, 'blob');
        $theirBlob = self::readTypedObject($readObject, $theirEntry->oid, 'blob');
        $merge = self::shouldUseBinaryMerge('', $ourBlob->body, $theirBlob->body, $bigFileThreshold)
            ? BlobMerge::mergeBinary('', $ourBlob->body, $theirBlob->body)
            : BlobMerge::mergeText(
                '',
                $ourBlob->body,
                $theirBlob->body,
                $conflictStyle,
                'base/' . $fullPath,
                'ours/' . $fullPath,
                'theirs/' . $fullPath,
            );

        $conflicts = [];
        if (!$merge->isClean()) {
            $conflicts[] = new TreeMergeConflict($fullPath, 'add-add', null, $ourEntry, $theirEntry);
        }

        return [
            'entry' => new TreeEntry($mergedMode, $path, $writeObject(new GitObject('blob', $merge->content))),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return null|array{entry:TreeEntry,conflict:TreeMergeConflict}
     */
    private static function tryMergeAddedSymlinkConflict(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
    ): ?array {
        if ($baseEntry !== null || $ourEntry === null || $theirEntry === null || !$ourEntry->isLink() || !$theirEntry->isLink()) {
            return null;
        }

        return [
            'entry' => new TreeEntry($ourEntry->mode, $path, $ourEntry->oid),
            'conflict' => new TreeMergeConflict($fullPath, 'add-add', null, $ourEntry, $theirEntry),
        ];
    }

    /**
     * @return null|array{entry:TreeEntry,conflict:TreeMergeConflict}
     */
    private static function tryMergeDeleteModifyEntry(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
    ): ?array {
        if ($baseEntry === null || ($ourEntry === null) === ($theirEntry === null)) {
            return null;
        }

        $modifiedEntry = $ourEntry ?? $theirEntry;
        if ($modifiedEntry === null || self::sameEntry($baseEntry, $modifiedEntry)) {
            return null;
        }

        return [
            'entry' => new TreeEntry($modifiedEntry->mode, $path, $modifiedEntry->oid),
            'conflict' => new TreeMergeConflict($fullPath, 'delete-modify', $baseEntry, $ourEntry, $theirEntry),
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{merged:list<TreeEntry>,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeFileToDirectoryConflict(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($baseEntry === null || $baseEntry->isTree() || $ourEntry === null || $theirEntry === null) {
            return null;
        }
        if ($ourEntry->isTree() === $theirEntry->isTree()) {
            return null;
        }

        $fileEntry = $ourEntry->isTree() ? $theirEntry : $ourEntry;
        $directoryEntry = $ourEntry->isTree() ? $ourEntry : $theirEntry;
        if ($fileEntry->isTree() || !$directoryEntry->isTree() || $baseEntry->kind() !== $fileEntry->kind()) {
            return null;
        }

        $fileIsOurs = !$ourEntry->isTree();
        $directoryTree = Tree::fromObject(self::readTypedObject($readObject, $directoryEntry->oid, 'tree'));
        $renameIntoDirectory = self::tryMergeFileChangeIntoDirectoryRename(
            $path,
            $fullPath,
            $baseEntry,
            $fileEntry,
            $directoryEntry,
            $directoryTree,
            $fileIsOurs,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        if ($renameIntoDirectory !== null) {
            return $renameIntoDirectory;
        }

        $relocatedName = $path . ($fileIsOurs ? '~A' : '~B');
        $relocatedPath = $fullPath . ($fileIsOurs ? '~A' : '~B');
        $baseRelocated = new TreeEntry($baseEntry->mode, $relocatedName, $baseEntry->oid);
        $fileRelocated = new TreeEntry($fileEntry->mode, $relocatedName, $fileEntry->oid);

        return [
            'merged' => [
                new TreeEntry($directoryEntry->mode, $path, $directoryEntry->oid),
                $fileRelocated,
            ],
            'conflicts' => [
                new TreeMergeConflict(
                    $relocatedPath,
                    'delete-modify',
                    $baseRelocated,
                    $fileIsOurs ? $fileRelocated : null,
                    $fileIsOurs ? null : $fileRelocated,
                ),
            ],
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{merged:list<TreeEntry>,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeFileChangeIntoDirectoryRename(
        string $path,
        string $fullPath,
        TreeEntry $baseEntry,
        TreeEntry $fileEntry,
        TreeEntry $directoryEntry,
        Tree $directoryTree,
        bool $fileIsOurs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        $matches = self::matchingLeafPaths($directoryTree, $baseEntry, $readObject);
        if (count($matches) !== 1) {
            return null;
        }

        $targetPath = $matches[0]['path'];
        $targetEntry = $matches[0]['entry'];
        $targetFilename = basename($targetPath);
        $baseAtTarget = new TreeEntry($baseEntry->mode, $targetFilename, $baseEntry->oid);
        $fileAtTarget = new TreeEntry($fileEntry->mode, $targetFilename, $fileEntry->oid);
        $contentMerge = self::tryMergeChangedEntry(
            $targetFilename,
            self::joinPath($fullPath, $targetPath),
            $baseAtTarget,
            $fileIsOurs ? $fileAtTarget : $targetEntry,
            $fileIsOurs ? $targetEntry : $fileAtTarget,
            $readObject,
            $writeObject,
            $conflictStyle,
            [],
            $bigFileThreshold,
        );
        if ($contentMerge === null || $contentMerge['entry'] === null) {
            return null;
        }

        $mergedTree = self::replaceTreeEntryAtPath($directoryTree, explode('/', $targetPath), $contentMerge['entry'], $readObject, $writeObject);

        return [
            'merged' => [new TreeEntry($directoryEntry->mode, $path, $writeObject($mergedTree->toObject()))],
            'conflicts' => $contentMerge['conflicts'],
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<array{path:string,entry:TreeEntry}>
     */
    private static function matchingLeafPaths(Tree $tree, TreeEntry $target, callable $readObject, string $prefix = ''): array
    {
        $matches = [];
        foreach ($tree->entries as $entry) {
            $path = self::joinPath($prefix, $entry->filename);
            if ($entry->isTree()) {
                array_push($matches, ...self::matchingLeafPaths(
                    Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')),
                    $target,
                    $readObject,
                    $path,
                ));
                continue;
            }
            if (self::sameEntry(new TreeEntry($target->mode, $entry->filename, $target->oid), $entry)) {
                $matches[] = ['path' => $path, 'entry' => $entry];
            }
        }

        return $matches;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{merged:list<TreeEntry>,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeTreeToFileConflict(
        string $path,
        string $fullPath,
        ?TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
    ): ?array {
        if ($baseEntry === null || !$baseEntry->isTree() || $ourEntry === null || $theirEntry === null) {
            return null;
        }
        if ($ourEntry->isTree() === $theirEntry->isTree()) {
            return null;
        }

        $treeEntry = $ourEntry->isTree() ? $ourEntry : $theirEntry;
        $fileEntry = $ourEntry->isTree() ? $theirEntry : $ourEntry;
        if (!$treeEntry->isTree() || $fileEntry->isTree()) {
            return null;
        }

        $subtreeMerge = $ourEntry->isTree()
            ? self::mergeTreeAgainstDeletion(
                Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')),
                Tree::fromObject(self::readTypedObject($readObject, $treeEntry->oid, 'tree')),
                true,
                $readObject,
                $writeObject,
                $fullPath,
            )
            : self::mergeTreeAgainstDeletion(
                Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')),
                Tree::fromObject(self::readTypedObject($readObject, $treeEntry->oid, 'tree')),
                false,
                $readObject,
                $writeObject,
                $fullPath,
            );

        $fileIsOurs = !$ourEntry->isTree();
        $relocatedName = $path . ($fileIsOurs ? '~A' : '~B');
        $relocatedPath = $fullPath . ($fileIsOurs ? '~A' : '~B');
        $fileRelocated = new TreeEntry($fileEntry->mode, $relocatedName, $fileEntry->oid);

        return [
            'merged' => [
                new TreeEntry($treeEntry->mode, $path, $writeObject($subtreeMerge->tree->toObject())),
                $fileRelocated,
            ],
            'conflicts' => [
                ...$subtreeMerge->conflicts,
                new TreeMergeConflict(
                    $relocatedPath,
                    'directory-file',
                    null,
                    $fileIsOurs ? $fileRelocated : null,
                    $fileIsOurs ? null : $fileRelocated,
                ),
            ],
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function mergeTreeAgainstDeletion(
        Tree $base,
        Tree $modified,
        bool $modifiedIsOurs,
        callable $readObject,
        callable $writeObject,
        string $pathPrefix,
    ): TreeMergeResult {
        $baseEntries = self::entriesByName($base);
        $modifiedEntries = self::entriesByName($modified);
        $paths = array_keys($baseEntries + $modifiedEntries);
        sort($paths, SORT_STRING);

        $merged = [];
        $conflicts = [];
        foreach ($paths as $path) {
            $baseEntry = $baseEntries[$path] ?? null;
            $modifiedEntry = $modifiedEntries[$path] ?? null;
            if ($modifiedEntry === null || self::sameEntry($baseEntry, $modifiedEntry)) {
                continue;
            }

            $fullPath = self::joinPath($pathPrefix, $path);
            if ($baseEntry !== null && $baseEntry->isTree() && $modifiedEntry->isTree()) {
                $nested = self::mergeTreeAgainstDeletion(
                    Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')),
                    Tree::fromObject(self::readTypedObject($readObject, $modifiedEntry->oid, 'tree')),
                    $modifiedIsOurs,
                    $readObject,
                    $writeObject,
                    $fullPath,
                );
                if ($nested->tree->entries !== []) {
                    $merged[] = new TreeEntry($modifiedEntry->mode, $path, $writeObject($nested->tree->toObject()));
                }
                array_push($conflicts, ...$nested->conflicts);
                continue;
            }

            $merged[] = $modifiedEntry;
            $conflicts[] = new TreeMergeConflict(
                $fullPath,
                'delete-modify',
                $baseEntry,
                $modifiedIsOurs ? $modifiedEntry : null,
                $modifiedIsOurs ? null : $modifiedEntry,
            );
        }

        self::sortEntries($merged);

        return new TreeMergeResult(new Tree($merged), $conflicts);
    }

    private static function mergeBlobMode(TreeEntry $baseEntry, TreeEntry $ourEntry, TreeEntry $theirEntry): ?string
    {
        if ($ourEntry->mode === $theirEntry->mode) {
            return $ourEntry->mode;
        }
        if ($baseEntry->mode === $ourEntry->mode) {
            return $theirEntry->mode;
        }
        if ($baseEntry->mode === $theirEntry->mode) {
            return $ourEntry->mode;
        }

        return null;
    }

    private static function mergeAddedBlobMode(TreeEntry $ourEntry, TreeEntry $theirEntry): ?string
    {
        if ($ourEntry->mode === $theirEntry->mode) {
            return $ourEntry->mode;
        }
        if (in_array($ourEntry->mode, ['100644', '100755'], true) && in_array($theirEntry->mode, ['100644', '100755'], true)) {
            return '100755';
        }

        return null;
    }

    /**
     * @return array<string, TreeEntry>
     */
    private static function entriesByName(Tree $tree): array
    {
        $entries = [];
        foreach ($tree->entries as $entry) {
            if (isset($entries[$entry->filename])) {
                throw new \InvalidArgumentException("Duplicate tree entry name: {$entry->filename}");
            }
            $entries[$entry->filename] = $entry;
        }

        return $entries;
    }

    private static function sameEntry(?TreeEntry $left, ?TreeEntry $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->mode === $right->mode && $left->oid === $right->oid;
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $ourEntries
     * @param array<string, TreeEntry> $theirEntries
     * @param callable(string): GitObject $readObject
     * @param list<string> $inherited
     * @return list<string>
     */
    private static function unionMergeAttributePatterns(
        array $baseEntries,
        array $ourEntries,
        array $theirEntries,
        callable $readObject,
        string $pathPrefix,
        array $inherited,
    ): array
    {
        $entry = $ourEntries['.gitattributes'] ?? $baseEntries['.gitattributes'] ?? $theirEntries['.gitattributes'] ?? null;
        if ($entry === null || !$entry->isBlob()) {
            return $inherited;
        }

        $object = self::readTypedObject($readObject, $entry->oid, 'blob');
        foreach (self::parseUnionMergeAttributes($object->body) as $pattern) {
            $inherited[] = self::joinPath($pathPrefix, ltrim($pattern, '/'));
        }

        return array_values(array_unique($inherited));
    }

    /**
     * @return list<string>
     */
    private static function parseUnionMergeAttributes(string $attributes): array
    {
        $patterns = [];
        foreach (preg_split('/\r\n|\n|\r/', $attributes) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $pattern = array_shift($parts);
            if ($pattern === null || $pattern === '' || str_starts_with($pattern, '!')) {
                continue;
            }
            if (in_array('merge=union', $parts, true)) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * @param list<string> $patterns
     */
    private static function usesUnionMerge(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::attributePatternMatches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private static function attributePatternMatches(string $pattern, string $path): bool
    {
        $pattern = trim($pattern, '/');
        if ($pattern === '') {
            return false;
        }

        if (!str_contains($pattern, '/')) {
            $path = basename($path);
        }

        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '*') {
                $regex .= '[^/]*';
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '~');
            }
        }

        return preg_match('~^' . $regex . '$~', $path) === 1;
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $ourEntries
     * @param array<string, TreeEntry> $theirEntries
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return array{0:list<TreeMergeConflict>,1:array<string,true>,2:list<TreeEntry>}
     */
    private static function renameConflicts(
        array $baseEntries,
        array $ourEntries,
        array $theirEntries,
        string $pathPrefix,
        ?callable $readObject = null,
        ?callable $writeObject = null,
        string $conflictStyle = BlobMerge::STYLE_MERGE,
        ?int $bigFileThreshold = null,
    ): array
    {
        $ourRenames = self::detectedRenames($baseEntries, $ourEntries, $readObject);
        $theirRenames = self::detectedRenames($baseEntries, $theirEntries, $readObject);
        $ourRenamesByTarget = self::renamesByTarget($ourRenames);
        $theirRenamesByTarget = self::renamesByTarget($theirRenames);
        $directoryFileRelocations = self::directoryRenameFileRelocations(
            $baseEntries,
            $ourEntries,
            $theirEntries,
            $ourRenames,
            $theirRenames,
            $pathPrefix,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        $singleLeafDirectoryRenames = self::singleLeafDirectoryRenameModifyMerges(
            $baseEntries,
            $ourEntries,
            $theirEntries,
            $pathPrefix,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        $conflicts = $singleLeafDirectoryRenames['conflicts'];
        $consumed = $directoryFileRelocations['consumed'] + $singleLeafDirectoryRenames['consumed'];
        $merged = $singleLeafDirectoryRenames['merged'];

        $paths = array_keys($baseEntries);
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            if (isset($consumed[$path])) {
                continue;
            }

            $baseEntry = $baseEntries[$path];
            $ourRename = $ourRenames[$path] ?? null;
            $theirRename = $theirRenames[$path] ?? null;
            if ($ourRename !== null && $theirRename !== null) {
                if ($ourRename['path'] === $theirRename['path']) {
                    $sameTargetMerge = self::tryMergeSameTargetRename(
                        $pathPrefix,
                        $baseEntry,
                        $ourRename,
                        $theirRename,
                        $readObject,
                        $writeObject,
                        $conflictStyle,
                        $bigFileThreshold,
                    );
                    if ($sameTargetMerge !== null) {
                        if ($sameTargetMerge['entry'] !== null) {
                            $merged[] = $sameTargetMerge['entry'];
                        }
                        array_push($conflicts, ...$sameTargetMerge['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                    }
                } else {
                    $handledTargetCollision = false;
                    foreach ([$ourRename['path'], $theirRename['path']] as $targetPath) {
                        if (isset($consumed[$targetPath])) {
                            continue;
                        }
                        $targetCollisionMerge = self::tryMergeDivergentRenameTargetCollision(
                            $pathPrefix,
                            $targetPath,
                            $baseEntries,
                            $ourEntries,
                            $theirEntries,
                            $ourRenamesByTarget,
                            $theirRenamesByTarget,
                            $readObject,
                            $writeObject,
                            $conflictStyle,
                            $bigFileThreshold,
                        );
                        if ($targetCollisionMerge === null) {
                            continue;
                        }

                        if ($targetCollisionMerge['entry'] !== null) {
                            $merged[] = $targetCollisionMerge['entry'];
                        }
                        array_push($conflicts, ...$targetCollisionMerge['conflicts']);
                        $consumed[$targetPath] = true;
                        $handledTargetCollision = true;
                    }
                    if ($handledTargetCollision || (isset($consumed[$ourRename['path']]) && isset($consumed[$theirRename['path']]))) {
                        $consumed[$path] = true;
                        continue;
                    }

                    $divergentSymlinkRename = self::tryMergeDivergentSymlinkRenames(
                        $pathPrefix,
                        $path,
                        $baseEntry,
                        $ourRename,
                        $theirRename,
                    );
                    if ($divergentSymlinkRename !== null) {
                        array_push($merged, ...$divergentSymlinkRename['merged']);
                        array_push($conflicts, ...$divergentSymlinkRename['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                        $consumed[$theirRename['path']] = true;
                        continue;
                    }

                    $conflicts[] = new TreeMergeConflict(
                        self::joinPath($pathPrefix, $path),
                        'rename-rename',
                        $baseEntry,
                        $ourRename['entry'],
                        $theirRename['entry'],
                    );
                    $consumed[$path] = true;
                    $consumed[$ourRename['path']] = true;
                    $consumed[$theirRename['path']] = true;
                    $merged[] = $baseEntry;
                }
                continue;
            }

            if ($ourRename !== null) {
                $theirEntry = $theirEntries[$path] ?? null;
                if ($theirEntry === null || !self::sameEntry($baseEntry, $theirEntry)) {
                    $theirTargetEntry = $theirEntries[$ourRename['path']] ?? null;
                    if ($theirTargetEntry !== null) {
                        if ($theirEntry === null) {
                            $targetAddMerge = self::tryMergeRenameTargetAdd(
                                $pathPrefix,
                                $ourRename['path'],
                                $ourRename['entry'],
                                $theirTargetEntry,
                                $readObject,
                                $writeObject,
                                $conflictStyle,
                                $bigFileThreshold,
                            );
                            if ($targetAddMerge !== null) {
                                $merged[] = $targetAddMerge['entry'];
                                array_push($conflicts, ...$targetAddMerge['conflicts']);
                                $consumed[$path] = true;
                                $consumed[$ourRename['path']] = true;
                                self::consumeRenamesToTarget($ourRename['path'], $ourRenames, $theirRenames, $consumed);
                                continue;
                            }
                        }

                        $conflicts[] = new TreeMergeConflict(
                            self::joinPath($pathPrefix, $path),
                            $theirEntry === null ? 'rename-delete' : 'rename-modify',
                            $baseEntry,
                            $ourRename['entry'],
                            $theirEntry,
                        );
                        $conflicts[] = new TreeMergeConflict(
                            self::joinPath($pathPrefix, $ourRename['path']),
                            'rename-target-add',
                            null,
                            $ourRename['entry'],
                            $theirTargetEntry,
                        );
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                        $merged[] = $baseEntry;
                        continue;
                    }

                    $renameTypeChange = self::tryMergeRenameTypeChange(
                        $pathPrefix,
                        $path,
                        $ourRename['path'],
                        $baseEntry,
                        $ourRename['entry'],
                        $theirEntry,
                        true,
                    );
                    if ($renameTypeChange !== null) {
                        array_push($merged, ...$renameTypeChange['merged']);
                        $conflicts[] = $renameTypeChange['conflict'];
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                        continue;
                    }

                    $fileRenameModifyMerge = self::tryMergeFileRenameModify(
                        $pathPrefix,
                        $path,
                        $ourRename['path'],
                        $baseEntry,
                        $ourRename['entry'],
                        $theirEntry,
                        true,
                        $readObject,
                        $writeObject,
                        $conflictStyle,
                        $bigFileThreshold,
                    );
                    if ($fileRenameModifyMerge !== null) {
                        if ($fileRenameModifyMerge['entry'] !== null) {
                            $merged[] = $fileRenameModifyMerge['entry'];
                        }
                        array_push($conflicts, ...$fileRenameModifyMerge['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                        continue;
                    }

                    $renameModifyMerge = self::tryMergeDirectoryRenameModify(
                        $pathPrefix,
                        $ourRename['path'],
                        $baseEntry,
                        $ourRename['entry'],
                        self::applyDirectoryFileRelocations(
                            $theirEntry,
                            $directoryFileRelocations['byDirectory'][$path] ?? [],
                            $readObject,
                            $writeObject,
                        ),
                        $readObject,
                        $writeObject,
                        $conflictStyle,
                        $bigFileThreshold,
                    );
                    if ($renameModifyMerge !== null) {
                        if ($renameModifyMerge['entry'] !== null) {
                            $merged[] = $renameModifyMerge['entry'];
                        }
                        array_push($conflicts, ...self::relocationConflicts($directoryFileRelocations['byDirectory'][$path] ?? []));
                        array_push($conflicts, ...$renameModifyMerge['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$ourRename['path']] = true;
                        continue;
                    }

                    $conflicts[] = new TreeMergeConflict(
                        self::joinPath($pathPrefix, $path),
                        $theirEntry === null ? 'rename-delete' : 'rename-modify',
                        $baseEntry,
                        $ourRename['entry'],
                        $theirEntry,
                    );
                    $consumed[$path] = true;
                    $consumed[$ourRename['path']] = true;
                    $merged[] = $baseEntry;
                }
                continue;
            }

            if ($theirRename !== null) {
                $ourEntry = $ourEntries[$path] ?? null;
                if ($ourEntry === null || !self::sameEntry($baseEntry, $ourEntry)) {
                    $ourTargetEntry = $ourEntries[$theirRename['path']] ?? null;
                    if ($ourTargetEntry !== null) {
                        if ($ourEntry === null) {
                            $targetAddMerge = self::tryMergeRenameTargetAdd(
                                $pathPrefix,
                                $theirRename['path'],
                                $ourTargetEntry,
                                $theirRename['entry'],
                                $readObject,
                                $writeObject,
                                $conflictStyle,
                                $bigFileThreshold,
                            );
                            if ($targetAddMerge !== null) {
                                $merged[] = $targetAddMerge['entry'];
                                array_push($conflicts, ...$targetAddMerge['conflicts']);
                                $consumed[$path] = true;
                                $consumed[$theirRename['path']] = true;
                                self::consumeRenamesToTarget($theirRename['path'], $ourRenames, $theirRenames, $consumed);
                                continue;
                            }
                        }

                        $conflicts[] = new TreeMergeConflict(
                            self::joinPath($pathPrefix, $path),
                            $ourEntry === null ? 'rename-delete' : 'rename-modify',
                            $baseEntry,
                            $ourEntry,
                            $theirRename['entry'],
                        );
                        $conflicts[] = new TreeMergeConflict(
                            self::joinPath($pathPrefix, $theirRename['path']),
                            'rename-target-add',
                            null,
                            $ourTargetEntry,
                            $theirRename['entry'],
                        );
                        $consumed[$path] = true;
                        $consumed[$theirRename['path']] = true;
                        $merged[] = $baseEntry;
                        continue;
                    }

                    $renameTypeChange = self::tryMergeRenameTypeChange(
                        $pathPrefix,
                        $path,
                        $theirRename['path'],
                        $baseEntry,
                        $theirRename['entry'],
                        $ourEntry,
                        false,
                    );
                    if ($renameTypeChange !== null) {
                        array_push($merged, ...$renameTypeChange['merged']);
                        $conflicts[] = $renameTypeChange['conflict'];
                        $consumed[$path] = true;
                        $consumed[$theirRename['path']] = true;
                        continue;
                    }

                    $fileRenameModifyMerge = self::tryMergeFileRenameModify(
                        $pathPrefix,
                        $path,
                        $theirRename['path'],
                        $baseEntry,
                        $theirRename['entry'],
                        $ourEntry,
                        false,
                        $readObject,
                        $writeObject,
                        $conflictStyle,
                        $bigFileThreshold,
                    );
                    if ($fileRenameModifyMerge !== null) {
                        if ($fileRenameModifyMerge['entry'] !== null) {
                            $merged[] = $fileRenameModifyMerge['entry'];
                        }
                        array_push($conflicts, ...$fileRenameModifyMerge['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$theirRename['path']] = true;
                        continue;
                    }

                    $renameModifyMerge = self::tryMergeDirectoryRenameModify(
                        $pathPrefix,
                        $theirRename['path'],
                        $baseEntry,
                        self::applyDirectoryFileRelocations(
                            $ourEntry,
                            $directoryFileRelocations['byDirectory'][$path] ?? [],
                            $readObject,
                            $writeObject,
                        ),
                        $theirRename['entry'],
                        $readObject,
                        $writeObject,
                        $conflictStyle,
                        $bigFileThreshold,
                    );
                    if ($renameModifyMerge !== null) {
                        if ($renameModifyMerge['entry'] !== null) {
                            $merged[] = $renameModifyMerge['entry'];
                        }
                        array_push($conflicts, ...self::relocationConflicts($directoryFileRelocations['byDirectory'][$path] ?? []));
                        array_push($conflicts, ...$renameModifyMerge['conflicts']);
                        $consumed[$path] = true;
                        $consumed[$theirRename['path']] = true;
                        continue;
                    }

                    $conflicts[] = new TreeMergeConflict(
                        self::joinPath($pathPrefix, $path),
                        $ourEntry === null ? 'rename-delete' : 'rename-modify',
                        $baseEntry,
                        $ourEntry,
                        $theirRename['entry'],
                    );
                    $consumed[$path] = true;
                    $consumed[$theirRename['path']] = true;
                    $merged[] = $baseEntry;
                }
            }
        }

        return [$conflicts, $consumed, $merged];
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $ourEntries
     * @param array<string, TreeEntry> $theirEntries
     * @param array<string, string> $ourRenamesByTarget
     * @param array<string, string> $theirRenamesByTarget
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return null|array{entry:?TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeDivergentRenameTargetCollision(
        string $pathPrefix,
        string $targetPath,
        array $baseEntries,
        array $ourEntries,
        array $theirEntries,
        array $ourRenamesByTarget,
        array $theirRenamesByTarget,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($readObject === null || $writeObject === null) {
            return null;
        }

        $ourSource = $ourRenamesByTarget[$targetPath] ?? null;
        $theirSource = $theirRenamesByTarget[$targetPath] ?? null;
        if ($ourSource === null || $theirSource === null || $ourSource === $theirSource) {
            return null;
        }

        $baseEntry = $baseEntries[$theirSource] ?? $baseEntries[$ourSource] ?? null;
        $ourEntry = $ourEntries[$targetPath] ?? null;
        $theirEntry = $theirEntries[$targetPath] ?? null;
        if ($baseEntry === null || $ourEntry === null || $theirEntry === null) {
            return null;
        }

        return self::tryMergeChangedEntry(
            $targetPath,
            self::joinPath($pathPrefix, $targetPath),
            new TreeEntry($baseEntry->mode, $targetPath, $baseEntry->oid),
            new TreeEntry($ourEntry->mode, $targetPath, $ourEntry->oid),
            new TreeEntry($theirEntry->mode, $targetPath, $theirEntry->oid),
            $readObject,
            $writeObject,
            $conflictStyle,
            [],
            $bigFileThreshold,
        );
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $ourEntries
     * @param array<string, TreeEntry> $theirEntries
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return array{conflicts:list<TreeMergeConflict>,consumed:array<string,true>,merged:list<TreeEntry>}
     */
    private static function singleLeafDirectoryRenameModifyMerges(
        array $baseEntries,
        array $ourEntries,
        array $theirEntries,
        string $pathPrefix,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): array {
        if ($readObject === null || $writeObject === null) {
            return ['conflicts' => [], 'consumed' => [], 'merged' => []];
        }

        $ours = self::collectSingleLeafDirectoryRenameModifyMerges(
            $baseEntries,
            $ourEntries,
            $theirEntries,
            $pathPrefix,
            true,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        $theirs = self::collectSingleLeafDirectoryRenameModifyMerges(
            $baseEntries,
            $theirEntries,
            $ourEntries,
            $pathPrefix,
            false,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );

        return [
            'conflicts' => [...$ours['conflicts'], ...$theirs['conflicts']],
            'consumed' => $ours['consumed'] + $theirs['consumed'],
            'merged' => [...$ours['merged'], ...$theirs['merged']],
        ];
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $renamedSideEntries
     * @param array<string, TreeEntry> $otherSideEntries
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return array{conflicts:list<TreeMergeConflict>,consumed:array<string,true>,merged:list<TreeEntry>}
     */
    private static function collectSingleLeafDirectoryRenameModifyMerges(
        array $baseEntries,
        array $renamedSideEntries,
        array $otherSideEntries,
        string $pathPrefix,
        bool $renamedByOurs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): array {
        $conflicts = [];
        $consumed = [];
        $merged = [];

        foreach ($baseEntries as $directoryPath => $baseDirectory) {
            $otherDirectory = $otherSideEntries[$directoryPath] ?? null;
            if (
                !$baseDirectory->isTree()
                || isset($renamedSideEntries[$directoryPath])
                || $otherDirectory === null
                || !$otherDirectory->isTree()
            ) {
                continue;
            }

            $baseCount = 0;
            $otherCount = 0;
            $baseLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $baseDirectory->oid, 'tree')), $readObject, '', 0, $baseCount);
            $otherLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $otherDirectory->oid, 'tree')), $readObject, '', 0, $otherCount);
            if ($baseLeaves === null || $otherLeaves === null || count($baseLeaves) !== 1 || count($otherLeaves) !== 1) {
                continue;
            }

            $relativePath = array_key_first($baseLeaves);
            if ($relativePath === null || !isset($otherLeaves[$relativePath])) {
                continue;
            }

            $baseLeaf = $baseLeaves[$relativePath];
            $otherLeaf = $otherLeaves[$relativePath];
            if (!$baseLeaf->isBlob() || !$otherLeaf->isBlob()) {
                continue;
            }

            foreach ($renamedSideEntries as $targetPath => $renamedEntry) {
                if (isset($baseEntries[$targetPath]) || isset($consumed[$targetPath]) || !$renamedEntry->isBlob() || $baseLeaf->mode !== $renamedEntry->mode) {
                    continue;
                }
                if (self::blobSimilarity($baseLeaf, $renamedEntry, $readObject) < 60) {
                    continue;
                }

                $merge = self::tryMergeChangedEntry(
                    $targetPath,
                    self::joinPath($pathPrefix, $targetPath),
                    $baseLeaf,
                    $renamedByOurs ? $renamedEntry : $otherLeaf,
                    $renamedByOurs ? $otherLeaf : $renamedEntry,
                    $readObject,
                    $writeObject,
                    $conflictStyle,
                    [],
                    $bigFileThreshold,
                );
                if ($merge === null || $merge['entry'] === null) {
                    continue;
                }

                $merged[] = $merge['entry'];
                array_push($conflicts, ...$merge['conflicts']);
                $consumed[$directoryPath] = true;
                $consumed[$targetPath] = true;
                break;
            }
        }

        return ['conflicts' => $conflicts, 'consumed' => $consumed, 'merged' => $merged];
    }

    /**
     * @return null|array{merged:list<TreeEntry>,conflict:TreeMergeConflict}
     */
    private static function tryMergeRenameTypeChange(
        string $pathPrefix,
        string $sourcePath,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $renameEntry,
        ?TreeEntry $otherEntry,
        bool $renamedByOurs,
    ): ?array {
        if ($otherEntry === null || $baseEntry->kind() === $otherEntry->kind()) {
            return null;
        }

        $sourceEntry = new TreeEntry($otherEntry->mode, $sourcePath, $otherEntry->oid);
        $targetEntry = new TreeEntry($renameEntry->mode, $targetPath, $renameEntry->oid);

        return [
            'merged' => $renamedByOurs ? [$targetEntry, $sourceEntry] : [$sourceEntry, $targetEntry],
            'conflict' => new TreeMergeConflict(
                self::joinPath($pathPrefix, $targetPath),
                'delete-modify',
                $baseEntry,
                $renamedByOurs ? $renameEntry : null,
                $renamedByOurs ? null : $renameEntry,
            ),
        ];
    }

    /**
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return null|array{entry:?TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeFileRenameModify(
        string $pathPrefix,
        string $sourcePath,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $renameEntry,
        ?TreeEntry $otherEntry,
        bool $renamedByOurs,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($otherEntry === null || $readObject === null || $writeObject === null) {
            return null;
        }
        if ($baseEntry->isTree() || $renameEntry->isTree() || $otherEntry->isTree()) {
            return null;
        }
        if ($baseEntry->kind() !== $renameEntry->kind() || $baseEntry->kind() !== $otherEntry->kind()) {
            return null;
        }

        $targetName = basename($targetPath);
        $baseAtTarget = new TreeEntry($baseEntry->mode, $targetName, $baseEntry->oid);
        $renameAtTarget = new TreeEntry($renameEntry->mode, $targetName, $renameEntry->oid);
        $otherAtTarget = new TreeEntry($otherEntry->mode, $targetName, $otherEntry->oid);

        return self::tryMergeChangedEntry(
            $targetName,
            self::joinPath($pathPrefix, $targetPath),
            $baseAtTarget,
            $renamedByOurs ? $renameAtTarget : $otherAtTarget,
            $renamedByOurs ? $otherAtTarget : $renameAtTarget,
            $readObject,
            $writeObject,
            $conflictStyle,
            [],
            $bigFileThreshold,
        );
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $ourEntries
     * @param array<string, TreeEntry> $theirEntries
     * @param array<string, array{path:string,entry:TreeEntry}> $ourRenames
     * @param array<string, array{path:string,entry:TreeEntry}> $theirRenames
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return array{byDirectory:array<string,list<array{relativePath:string,entry:TreeEntry,conflicts:list<TreeMergeConflict>}>>,consumed:array<string,true>}
     */
    private static function directoryRenameFileRelocations(
        array $baseEntries,
        array $ourEntries,
        array $theirEntries,
        array $ourRenames,
        array $theirRenames,
        string $pathPrefix,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): array {
        if ($readObject === null || $writeObject === null) {
            return ['byDirectory' => [], 'consumed' => []];
        }

        $ours = self::collectDirectoryRenameFileRelocations(
            $baseEntries,
            $ourEntries,
            $theirEntries,
            $ourRenames,
            $pathPrefix,
            true,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        $theirs = self::collectDirectoryRenameFileRelocations(
            $baseEntries,
            $theirEntries,
            $ourEntries,
            $theirRenames,
            $pathPrefix,
            false,
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );

        return [
            'byDirectory' => $ours['byDirectory'] + $theirs['byDirectory'],
            'consumed' => $ours['consumed'] + $theirs['consumed'],
        ];
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $renamedSideEntries
     * @param array<string, TreeEntry> $otherSideEntries
     * @param array<string, array{path:string,entry:TreeEntry}> $directoryRenames
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return array{byDirectory:array<string,list<array{relativePath:string,entry:TreeEntry,conflicts:list<TreeMergeConflict>}>>,consumed:array<string,true>}
     */
    private static function collectDirectoryRenameFileRelocations(
        array $baseEntries,
        array $renamedSideEntries,
        array $otherSideEntries,
        array $directoryRenames,
        string $pathPrefix,
        bool $renamedByOurs,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): array {
        $byDirectory = [];
        $consumed = [];

        foreach ($directoryRenames as $directoryPath => $directoryRename) {
            $baseDirectory = $baseEntries[$directoryPath] ?? null;
            $otherDirectory = $otherSideEntries[$directoryPath] ?? null;
            if (
                $baseDirectory === null
                || $otherDirectory === null
                || !$baseDirectory->isTree()
                || !$directoryRename['entry']->isTree()
                || !$otherDirectory->isTree()
            ) {
                continue;
            }

            $baseCount = 0;
            $otherCount = 0;
            $baseLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $baseDirectory->oid, 'tree')), $readObject, '', 0, $baseCount);
            $otherLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $otherDirectory->oid, 'tree')), $readObject, '', 0, $otherCount);
            if ($baseLeaves === null || $otherLeaves === null) {
                continue;
            }

            $addedLeaves = array_diff_key($otherLeaves, $baseLeaves);
            if ($addedLeaves === []) {
                continue;
            }

            $candidatesByBasePath = [];
            foreach ($baseEntries as $basePath => $baseEntry) {
                $renamedSideEntry = $renamedSideEntries[$basePath] ?? null;
                if (
                    $basePath === $directoryPath
                    || isset($otherSideEntries[$basePath])
                    || $renamedSideEntry === null
                    || !$baseEntry->isBlob()
                    || !$renamedSideEntry->isBlob()
                    || self::sameEntry($baseEntry, $renamedSideEntry)
                ) {
                    continue;
                }

                foreach ($addedLeaves as $relativePath => $otherLeaf) {
                    if (!$otherLeaf->isBlob() || $baseEntry->kind() !== $otherLeaf->kind()) {
                        continue;
                    }
                    $score = self::blobSimilarity($baseEntry, $otherLeaf, $readObject);
                    if ($score < 60) {
                        continue;
                    }
                    $candidatesByBasePath[$basePath][$relativePath] = ['score' => $score, 'entry' => $otherLeaf];
                }
            }

            foreach (self::strictBestRelocationCandidates($candidatesByBasePath) as $basePath => $candidate) {
                $baseEntry = $baseEntries[$basePath];
                $renamedSideEntry = $renamedSideEntries[$basePath];
                $otherLeaf = $candidate['entry'];
                $relativePath = $candidate['path'];
                $targetPath = self::joinPath($directoryRename['path'], $relativePath);
                $merge = self::tryMergeChangedEntry(
                    basename($relativePath),
                    self::joinPath($pathPrefix, $targetPath),
                    $baseEntry,
                    $renamedByOurs ? $renamedSideEntry : $otherLeaf,
                    $renamedByOurs ? $otherLeaf : $renamedSideEntry,
                    $readObject,
                    $writeObject,
                    $conflictStyle,
                    [],
                    $bigFileThreshold,
                );
                if ($merge === null || $merge['entry'] === null) {
                    continue;
                }

                $byDirectory[$directoryPath][] = [
                    'relativePath' => $relativePath,
                    'entry' => $merge['entry'],
                    'conflicts' => $merge['conflicts'],
                ];
                $consumed[$basePath] = true;
            }
        }

        return ['byDirectory' => $byDirectory, 'consumed' => $consumed];
    }

    /**
     * @param array<string, array<string, array{score:int,entry:TreeEntry}>> $candidatesByBasePath
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function strictBestRelocationCandidates(array $candidatesByBasePath): array
    {
        $selected = [];
        $selectedTargetCounts = [];
        foreach ($candidatesByBasePath as $basePath => $candidates) {
            uasort(
                $candidates,
                static fn (array $left, array $right): int => $right['score'] <=> $left['score'],
            );
            $candidatePaths = array_keys($candidates);
            $bestPath = $candidatePaths[0] ?? null;
            if ($bestPath === null) {
                continue;
            }
            $secondPath = $candidatePaths[1] ?? null;
            if ($secondPath !== null && $candidates[$secondPath]['score'] === $candidates[$bestPath]['score']) {
                continue;
            }

            $selected[$basePath] = ['path' => $bestPath, 'entry' => $candidates[$bestPath]['entry']];
            $selectedTargetCounts[$bestPath] = ($selectedTargetCounts[$bestPath] ?? 0) + 1;
        }

        foreach ($selected as $basePath => $candidate) {
            if (($selectedTargetCounts[$candidate['path']] ?? 0) !== 1) {
                unset($selected[$basePath]);
            }
        }

        return $selected;
    }

    /**
     * @param ?TreeEntry $treeEntry
     * @param list<array{relativePath:string,entry:TreeEntry,conflicts:list<TreeMergeConflict>}> $relocations
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     */
    private static function applyDirectoryFileRelocations(?TreeEntry $treeEntry, array $relocations, ?callable $readObject, ?callable $writeObject): ?TreeEntry
    {
        if ($treeEntry === null || $relocations === [] || $readObject === null || $writeObject === null || !$treeEntry->isTree()) {
            return $treeEntry;
        }

        $tree = Tree::fromObject(self::readTypedObject($readObject, $treeEntry->oid, 'tree'));
        foreach ($relocations as $relocation) {
            $tree = self::replaceTreeEntryAtPath($tree, explode('/', $relocation['relativePath']), $relocation['entry'], $readObject, $writeObject);
        }

        return new TreeEntry($treeEntry->mode, $treeEntry->filename, $writeObject($tree->toObject()));
    }

    /**
     * @param list<string> $parts
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function replaceTreeEntryAtPath(Tree $tree, array $parts, TreeEntry $replacement, callable $readObject, callable $writeObject): Tree
    {
        $name = array_shift($parts);
        if ($name === null || $name === '') {
            return $tree;
        }

        $entries = self::entriesByName($tree);
        if ($parts === []) {
            $entries[$name] = new TreeEntry($replacement->mode, $name, $replacement->oid);
        } else {
            $entry = $entries[$name] ?? null;
            if ($entry === null || !$entry->isTree()) {
                return $tree;
            }
            $nested = self::replaceTreeEntryAtPath(
                Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')),
                $parts,
                $replacement,
                $readObject,
                $writeObject,
            );
            $entries[$name] = new TreeEntry($entry->mode, $entry->filename, $writeObject($nested->toObject()));
        }

        $values = array_values($entries);
        self::sortEntries($values);

        return new Tree($values);
    }

    /**
     * @param list<array{relativePath:string,entry:TreeEntry,conflicts:list<TreeMergeConflict>}> $relocations
     * @return list<TreeMergeConflict>
     */
    private static function relocationConflicts(array $relocations): array
    {
        $conflicts = [];
        foreach ($relocations as $relocation) {
            array_push($conflicts, ...$relocation['conflicts']);
        }

        return $conflicts;
    }

    /**
     * @param array<string, array{path:string,entry:TreeEntry}> $ourRenames
     * @param array<string, array{path:string,entry:TreeEntry}> $theirRenames
     * @param array<string,true> $consumed
     */
    private static function consumeRenamesToTarget(string $targetPath, array $ourRenames, array $theirRenames, array &$consumed): void
    {
        foreach ([$ourRenames, $theirRenames] as $renames) {
            foreach ($renames as $sourcePath => $rename) {
                if ($rename['path'] === $targetPath) {
                    $consumed[$sourcePath] = true;
                    $consumed[$targetPath] = true;
                }
            }
        }
    }

    /**
     * @param array<string, array{path:string,entry:TreeEntry}> $renames
     * @return array<string, string>
     */
    private static function renamesByTarget(array $renames): array
    {
        $targets = [];
        foreach ($renames as $sourcePath => $rename) {
            $targetPath = $rename['path'];
            if (array_key_exists($targetPath, $targets)) {
                $targets[$targetPath] = null;
                continue;
            }
            $targets[$targetPath] = $sourcePath;
        }

        return array_filter($targets, static fn (?string $sourcePath): bool => $sourcePath !== null);
    }

    /**
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return null|array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeRenameTargetAdd(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $ourEntry,
        TreeEntry $theirEntry,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($readObject === null || $writeObject === null) {
            return null;
        }
        if (!$ourEntry->isBlob() || !$theirEntry->isBlob() || $ourEntry->mode !== $theirEntry->mode) {
            return null;
        }

        $fullPath = self::joinPath($pathPrefix, $targetPath);
        $ourBlob = self::readTypedObject($readObject, $ourEntry->oid, 'blob');
        $theirBlob = self::readTypedObject($readObject, $theirEntry->oid, 'blob');
        $merge = self::shouldUseBinaryMerge('', $ourBlob->body, $theirBlob->body, $bigFileThreshold)
            ? BlobMerge::mergeBinary('', $ourBlob->body, $theirBlob->body)
            : BlobMerge::mergeText(
                '',
                $ourBlob->body,
                $theirBlob->body,
                $conflictStyle,
                'base/' . $fullPath,
                'ours/' . $fullPath,
                'theirs/' . $fullPath,
            );

        $conflicts = [];
        if (!$merge->isClean()) {
            $conflicts[] = new TreeMergeConflict($fullPath, 'content-conflict', null, $ourEntry, $theirEntry);
        }

        return [
            'entry' => new TreeEntry($ourEntry->mode, $targetPath, $writeObject(new GitObject('blob', $merge->content))),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param array{path:string,entry:TreeEntry} $ourRename
     * @param array{path:string,entry:TreeEntry} $theirRename
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return null|array{entry:?TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeSameTargetRename(
        string $pathPrefix,
        TreeEntry $baseEntry,
        array $ourRename,
        array $theirRename,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($readObject === null || $writeObject === null) {
            return null;
        }

        $targetPath = $ourRename['path'];

        $result = self::tryMergeChangedEntry(
            $targetPath,
            self::joinPath($pathPrefix, $targetPath),
            $baseEntry,
            $ourRename['entry'],
            $theirRename['entry'],
            $readObject,
            $writeObject,
            $conflictStyle,
            [],
            $bigFileThreshold,
        );
        if ($result === null) {
            return null;
        }

        if ($baseEntry->isTree() && $ourRename['entry']->isTree() && $theirRename['entry']->isTree()) {
            array_push(
                $result['conflicts'],
                ...self::sameTargetRenameModeConflicts(
                    $pathPrefix,
                    $targetPath,
                    $baseEntry,
                    $ourRename['entry'],
                    $theirRename['entry'],
                    $readObject,
                ),
            );
        }

        return $result;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<TreeMergeConflict>
     */
    private static function sameTargetRenameModeConflicts(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $ourEntry,
        TreeEntry $theirEntry,
        callable $readObject,
    ): array {
        $baseCount = 0;
        $ourCount = 0;
        $theirCount = 0;
        $baseLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')), $readObject, '', 0, $baseCount);
        $ourLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $ourEntry->oid, 'tree')), $readObject, '', 0, $ourCount);
        $theirLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $theirEntry->oid, 'tree')), $readObject, '', 0, $theirCount);
        if ($baseLeaves === null || $ourLeaves === null || $theirLeaves === null) {
            return [];
        }

        $conflicts = [];
        foreach ($baseLeaves as $relativePath => $baseLeaf) {
            $ourLeaf = $ourLeaves[$relativePath] ?? null;
            $theirLeaf = $theirLeaves[$relativePath] ?? null;
            if ($ourLeaf === null || $theirLeaf === null) {
                continue;
            }
            if (
                $ourLeaf->mode === $theirLeaf->mode
                || $ourLeaf->oid !== $theirLeaf->oid
                || !self::sameContentKind($baseLeaf, $ourLeaf)
                || !self::sameContentKind($ourLeaf, $theirLeaf)
            ) {
                continue;
            }

            $conflicts[] = new TreeMergeConflict(
                self::joinPath($pathPrefix, self::joinPath($targetPath, $relativePath)),
                'mode-change',
                null,
                new TreeEntry($ourLeaf->mode, basename($relativePath), $ourLeaf->oid),
                new TreeEntry($theirLeaf->mode, basename($relativePath), $theirLeaf->oid),
            );
        }

        return $conflicts;
    }

    /**
     * @param array{path:string,entry:TreeEntry} $ourRename
     * @param array{path:string,entry:TreeEntry} $theirRename
     * @return null|array{merged:list<TreeEntry>,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeDivergentSymlinkRenames(
        string $pathPrefix,
        string $sourcePath,
        TreeEntry $baseEntry,
        array $ourRename,
        array $theirRename,
    ): ?array {
        if (!$baseEntry->isLink() || !$ourRename['entry']->isLink() || !$theirRename['entry']->isLink()) {
            return null;
        }

        return [
            'merged' => [
                new TreeEntry($theirRename['entry']->mode, $theirRename['path'], $theirRename['entry']->oid),
                new TreeEntry($ourRename['entry']->mode, $ourRename['path'], $ourRename['entry']->oid),
            ],
            'conflicts' => [
                new TreeMergeConflict(self::joinPath($pathPrefix, $sourcePath), 'rename-rename', $baseEntry, null, null),
                new TreeMergeConflict(self::joinPath($pathPrefix, $ourRename['path']), 'rename-rename', null, $ourRename['entry'], null),
                new TreeMergeConflict(self::joinPath($pathPrefix, $theirRename['path']), 'rename-rename', null, null, $theirRename['entry']),
            ],
        ];
    }

    /**
     * @param null|callable(string): GitObject $readObject
     * @param null|callable(GitObject): string $writeObject
     * @return null|array{entry:?TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeDirectoryRenameModify(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $baseEntry,
        ?TreeEntry $ourEntry,
        ?TreeEntry $theirEntry,
        ?callable $readObject,
        ?callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold = null,
    ): ?array {
        if ($ourEntry === null || $theirEntry === null || $readObject === null || $writeObject === null) {
            return null;
        }
        if (!$baseEntry->isTree() || !$ourEntry->isTree() || !$theirEntry->isTree()) {
            return null;
        }

        $subtreeReplacement = self::tryMergeDirectoryRenameSubtreeReplacement(
            $pathPrefix,
            $targetPath,
            $baseEntry,
            $ourEntry,
            $theirEntry,
            $readObject,
            $writeObject,
        );
        if ($subtreeReplacement !== null) {
            return $subtreeReplacement;
        }

        $merge = self::tryMergeChangedEntry(
            $targetPath,
            self::joinPath($pathPrefix, $targetPath),
            $baseEntry,
            $ourEntry,
            $theirEntry,
            $readObject,
            $writeObject,
            $conflictStyle,
            [],
            $bigFileThreshold,
        );
        if ($merge === null || $merge['entry'] === null) {
            return $merge;
        }

        $sameTargetNested = self::sameTargetNestedRenameConflicts(
            $pathPrefix,
            $targetPath,
            $baseEntry,
            $ourEntry,
            $theirEntry,
            $merge['entry'],
            $readObject,
            $writeObject,
            $conflictStyle,
            $bigFileThreshold,
        );
        $nested = self::nestedDirectoryRenameConflicts(
            $pathPrefix,
            $targetPath,
            $baseEntry,
            $ourEntry,
            $theirEntry,
            $sameTargetNested['entry'],
            $readObject,
            $writeObject,
        );

        return [
            'entry' => $nested['entry'],
            'conflicts' => [...$merge['conflicts'], ...$sameTargetNested['conflicts'], ...$nested['conflicts']],
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function tryMergeDirectoryRenameSubtreeReplacement(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $ourEntry,
        TreeEntry $theirEntry,
        callable $readObject,
        callable $writeObject,
    ): ?array {
        $targetName = basename($targetPath);
        $renamedByOurs = $ourEntry->filename === $targetName && $theirEntry->filename !== $targetName;
        $renamedByTheirs = $theirEntry->filename === $targetName && $ourEntry->filename !== $targetName;
        if (!$renamedByOurs && !$renamedByTheirs) {
            return null;
        }

        $renamedEntry = $renamedByOurs ? $ourEntry : $theirEntry;
        $replacementEntry = $renamedByOurs ? $theirEntry : $ourEntry;
        if (!$renamedEntry->isTree() || !$replacementEntry->isTree()) {
            return null;
        }

        $baseTree = Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree'));
        $renamedTree = Tree::fromObject(self::readTypedObject($readObject, $renamedEntry->oid, 'tree'));
        $replacementTree = Tree::fromObject(self::readTypedObject($readObject, $replacementEntry->oid, 'tree'));
        $match = self::subtreeReplacementMatch($baseTree, $replacementTree, $readObject);
        if ($match === null) {
            return null;
        }

        $baseEntries = self::entriesByName($baseTree);
        $renamedEntries = self::entriesByName($renamedTree);
        $replacementEntries = self::entriesByName($replacementTree);
        $mergedEntries = $renamedEntries;
        foreach ($replacementEntries as $name => $entry) {
            if (isset($mergedEntries[$name])) {
                return null;
            }
            $mergedEntries[$name] = $entry;
        }

        $stageEntries = $renamedEntries;
        foreach (self::strictBestSubtreeReplacementRootCopies($baseEntries, $renamedEntries, $match['replacementLeaves'], $match['sourcePath'], $readObject) as $rootPath => $replacementLeaf) {
            $replacementAtRoot = new TreeEntry($replacementLeaf->mode, $rootPath, $replacementLeaf->oid);
            $mergedEntries[$rootPath] = $replacementAtRoot;
            $stageEntries[$rootPath] = $replacementAtRoot;
        }

        $stageValues = array_values($stageEntries);
        self::sortEntries($stageValues);
        $stageTreeEntry = new TreeEntry($renamedEntry->mode, $targetName, $writeObject((new Tree($stageValues))->toObject()));

        $conflicts = [
            new TreeMergeConflict(
                self::joinPath($pathPrefix, $targetPath),
                'directory-rename-subtree-replacement',
                null,
                $renamedByOurs ? $stageTreeEntry : null,
                $renamedByOurs ? null : $stageTreeEntry,
            ),
        ];

        foreach ($baseEntries as $rootPath => $rootBaseEntry) {
            if (
                $rootPath === $match['sourcePath']
                || !$rootBaseEntry->isBlob()
                || !isset($stageEntries[$rootPath])
                || self::sameEntry($rootBaseEntry, $renamedEntries[$rootPath] ?? null)
            ) {
                continue;
            }
            $conflicts[] = new TreeMergeConflict(
                self::joinPath($pathPrefix, self::joinPath($baseEntry->filename, $rootPath)),
                'rename-delete',
                new TreeEntry($rootBaseEntry->mode, $rootPath, $rootBaseEntry->oid),
                null,
                null,
            );
        }

        foreach ($match['replacementLeaves'] as $replacementPath => $replacementLeaf) {
            $baseLeaf = $match['baseLeaves'][$replacementPath] ?? null;
            if ($baseLeaf === null || self::sameEntry($baseLeaf, $replacementLeaf)) {
                continue;
            }
            $conflicts[] = new TreeMergeConflict(
                self::joinPath($pathPrefix, self::joinPath($baseEntry->filename, $replacementPath)),
                'directory-rename-suggested',
                null,
                $renamedByOurs ? null : $replacementLeaf,
                $renamedByOurs ? $replacementLeaf : null,
            );
        }

        $mergedValues = array_values($mergedEntries);
        self::sortEntries($mergedValues);

        return [
            'entry' => new TreeEntry($renamedEntry->mode, $targetName, $writeObject((new Tree($mergedValues))->toObject())),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return null|array{sourcePath:string,baseLeaves:array<string,TreeEntry>,replacementLeaves:array<string,TreeEntry>}
     */
    private static function subtreeReplacementMatch(Tree $baseTree, Tree $replacementTree, callable $readObject): ?array
    {
        $replacementCount = 0;
        $replacementLeaves = self::flattenTreeLeaves($replacementTree, $readObject, '', 0, $replacementCount);
        if ($replacementLeaves === null || $replacementLeaves === []) {
            return null;
        }

        foreach ($baseTree->entries as $baseEntry) {
            if (!$baseEntry->isTree()) {
                continue;
            }

            $baseCount = 0;
            $baseLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')), $readObject, '', 0, $baseCount);
            if ($baseLeaves === null || array_keys($baseLeaves) !== array_keys($replacementLeaves)) {
                continue;
            }

            foreach ($baseLeaves as $path => $baseLeaf) {
                $replacementLeaf = $replacementLeaves[$path];
                if ($baseLeaf->mode !== $replacementLeaf->mode || !self::sameContentKind($baseLeaf, $replacementLeaf)) {
                    continue 2;
                }
                if (!$baseLeaf->isBlob()) {
                    continue;
                }
                if (!self::sameEntry($baseLeaf, $replacementLeaf) && self::blobSimilarity($baseLeaf, $replacementLeaf, $readObject) < 60) {
                    continue 2;
                }
            }

            return [
                'sourcePath' => $baseEntry->filename,
                'baseLeaves' => $baseLeaves,
                'replacementLeaves' => $replacementLeaves,
            ];
        }

        return null;
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $renamedEntries
     * @param array<string, TreeEntry> $replacementLeaves
     * @param callable(string): GitObject $readObject
     * @return array<string, TreeEntry>
     */
    private static function strictBestSubtreeReplacementRootCopies(
        array $baseEntries,
        array $renamedEntries,
        array $replacementLeaves,
        string $subtreeSourcePath,
        callable $readObject,
    ): array {
        $candidates = [];
        foreach ($baseEntries as $rootPath => $baseEntry) {
            $renamedEntry = $renamedEntries[$rootPath] ?? null;
            if (
                $rootPath === $subtreeSourcePath
                || $renamedEntry === null
                || !$baseEntry->isBlob()
                || !$renamedEntry->isBlob()
                || self::sameEntry($baseEntry, $renamedEntry)
            ) {
                continue;
            }

            foreach ($replacementLeaves as $replacementPath => $replacementLeaf) {
                if (str_contains($replacementPath, '/') || !$replacementLeaf->isBlob() || $baseEntry->mode !== $replacementLeaf->mode) {
                    continue;
                }
                $score = self::blobSimilarity($baseEntry, $replacementLeaf, $readObject);
                if ($score < 60) {
                    continue;
                }
                $candidates[$rootPath][$replacementPath] = ['score' => $score, 'entry' => $replacementLeaf];
            }
        }

        $copies = [];
        foreach (self::strictBestRelocationCandidates($candidates) as $rootPath => $candidate) {
            $copies[$rootPath] = $candidate['entry'];
        }

        return $copies;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function sameTargetNestedRenameConflicts(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $ourEntry,
        TreeEntry $theirEntry,
        TreeEntry $mergedEntry,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold,
    ): array {
        $targetName = basename($targetPath);
        if (
            $ourEntry->filename !== $targetName
            || $theirEntry->filename === $targetName
            || !$baseEntry->isTree()
            || !$ourEntry->isTree()
            || !$theirEntry->isTree()
            || !$mergedEntry->isTree()
        ) {
            return ['entry' => $mergedEntry, 'conflicts' => []];
        }

        $baseEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')));
        $ourEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $ourEntry->oid, 'tree')));
        $theirEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $theirEntry->oid, 'tree')));
        $mergedEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $mergedEntry->oid, 'tree')));
        $ourRenames = self::detectedRenames($baseEntries, $ourEntries, $readObject);
        $theirRenames = self::detectedRenames($baseEntries, $theirEntries, $readObject);

        $conflicts = [];
        foreach ($ourRenames as $sourcePath => $ourRename) {
            $theirRename = $theirRenames[$sourcePath] ?? null;
            if ($theirRename === null || $theirRename['path'] !== $ourRename['path']) {
                continue;
            }

            $nestedTarget = $ourRename['path'];
            $baseSource = $baseEntries[$sourcePath] ?? null;
            $ourTarget = $ourEntries[$nestedTarget] ?? null;
            $theirTarget = $theirEntries[$nestedTarget] ?? null;
            $mergedTarget = $mergedEntries[$nestedTarget] ?? null;
            if (
                $baseSource === null
                || $ourTarget === null
                || $theirTarget === null
                || $mergedTarget === null
                || !$baseSource->isTree()
                || !$ourTarget->isTree()
                || !$theirTarget->isTree()
                || !$mergedTarget->isTree()
            ) {
                continue;
            }

            $rebased = self::mergeSameTargetRenameTreeWithoutBase(
                Tree::fromObject(self::readTypedObject($readObject, $ourTarget->oid, 'tree')),
                Tree::fromObject(self::readTypedObject($readObject, $mergedTarget->oid, 'tree')),
                self::joinPath($pathPrefix, self::joinPath($targetPath, $nestedTarget)),
                $readObject,
                $writeObject,
                $conflictStyle,
                $bigFileThreshold,
            );
            if ($rebased['conflicts'] === []) {
                continue;
            }

            $mergedEntries[$nestedTarget] = new TreeEntry(
                $mergedTarget->mode,
                $nestedTarget,
                $writeObject($rebased['tree']->toObject()),
            );
            array_push($conflicts, ...$rebased['conflicts']);
        }

        if ($conflicts === []) {
            return ['entry' => $mergedEntry, 'conflicts' => []];
        }

        $entries = array_values($mergedEntries);
        self::sortEntries($entries);

        return [
            'entry' => new TreeEntry($mergedEntry->mode, $mergedEntry->filename, $writeObject((new Tree($entries))->toObject())),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return array{tree:Tree,conflicts:list<TreeMergeConflict>}
     */
    private static function mergeSameTargetRenameTreeWithoutBase(
        Tree $ours,
        Tree $theirs,
        string $pathPrefix,
        callable $readObject,
        callable $writeObject,
        string $conflictStyle,
        ?int $bigFileThreshold,
    ): array {
        $ourEntries = self::entriesByName($ours);
        $theirEntries = self::entriesByName($theirs);
        $paths = array_keys($ourEntries + $theirEntries);
        sort($paths, SORT_STRING);

        $merged = [];
        $conflicts = [];
        foreach ($paths as $path) {
            $ourEntry = $ourEntries[$path] ?? null;
            $theirEntry = $theirEntries[$path] ?? null;
            if (self::sameEntry($ourEntry, $theirEntry)) {
                if ($ourEntry !== null) {
                    $merged[] = $ourEntry;
                }
                continue;
            }
            if ($ourEntry === null || $theirEntry === null) {
                $merged[] = $ourEntry ?? $theirEntry;
                continue;
            }

            $fullPath = self::joinPath($pathPrefix, $path);
            if ($ourEntry->isTree() && $theirEntry->isTree()) {
                $nested = self::mergeSameTargetRenameTreeWithoutBase(
                    Tree::fromObject(self::readTypedObject($readObject, $ourEntry->oid, 'tree')),
                    Tree::fromObject(self::readTypedObject($readObject, $theirEntry->oid, 'tree')),
                    $fullPath,
                    $readObject,
                    $writeObject,
                    $conflictStyle,
                    $bigFileThreshold,
                );
                $merged[] = new TreeEntry($theirEntry->mode, $path, $writeObject($nested['tree']->toObject()));
                array_push($conflicts, ...$nested['conflicts']);
                continue;
            }

            if ($ourEntry->isBlob() && $theirEntry->isBlob()) {
                $mergedMode = self::mergeAddedBlobMode($ourEntry, $theirEntry);
                if ($mergedMode !== null) {
                    $ourBlob = self::readTypedObject($readObject, $ourEntry->oid, 'blob');
                    $theirBlob = self::readTypedObject($readObject, $theirEntry->oid, 'blob');
                    $merge = self::shouldUseBinaryMerge('', $ourBlob->body, $theirBlob->body, $bigFileThreshold)
                        ? BlobMerge::mergeBinary('', $ourBlob->body, $theirBlob->body)
                        : BlobMerge::mergeText(
                            '',
                            $ourBlob->body,
                            $theirBlob->body,
                            $conflictStyle,
                            'base/' . $fullPath,
                            'ours/' . $fullPath,
                            'theirs/' . $fullPath,
                        );

                    $merged[] = new TreeEntry($mergedMode, $path, $writeObject(new GitObject('blob', $merge->content)));
                    if (!$merge->isClean()) {
                        $conflicts[] = new TreeMergeConflict(
                            $fullPath,
                            'content-conflict',
                            null,
                            $ourEntry,
                            $theirEntry,
                        );
                    }
                    continue;
                }
            }

            $merged[] = $ourEntry;
            $conflicts[] = new TreeMergeConflict($fullPath, 'add-add', null, $ourEntry, $theirEntry);
        }

        self::sortEntries($merged);

        return ['tree' => new Tree($merged), 'conflicts' => $conflicts];
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function nestedDirectoryRenameConflicts(
        string $pathPrefix,
        string $targetPath,
        TreeEntry $baseEntry,
        TreeEntry $ourEntry,
        TreeEntry $theirEntry,
        TreeEntry $mergedEntry,
        callable $readObject,
        callable $writeObject,
    ): array {
        $baseEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')));
        $ourEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $ourEntry->oid, 'tree')));
        $theirEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $theirEntry->oid, 'tree')));
        $mergedEntries = self::entriesByName(Tree::fromObject(self::readTypedObject($readObject, $mergedEntry->oid, 'tree')));

        $conflicts = [];
        $record = static function (
            array $renamedSideEntries,
            array $keptSideEntries,
            array $renames,
            bool $renamedByOurs,
        ) use (&$mergedEntries, &$conflicts, $baseEntries, $pathPrefix, $targetPath): void {
            foreach ($renames as $sourcePath => $rename) {
                $targetName = $rename['path'];
                $baseSource = $baseEntries[$sourcePath] ?? null;
                $keptSource = $keptSideEntries[$sourcePath] ?? null;
                $renamedTarget = $renamedSideEntries[$targetName] ?? null;
                $mergedTarget = $mergedEntries[$targetName] ?? null;
                if (
                    $baseSource === null
                    || $keptSource === null
                    || $renamedTarget === null
                    || $mergedTarget === null
                    || isset($mergedEntries[$sourcePath])
                    || isset($keptSideEntries[$targetName])
                    || !$baseSource->isTree()
                    || !$keptSource->isTree()
                    || !$renamedTarget->isTree()
                    || !$mergedTarget->isTree()
                    || self::sameEntry($baseSource, $keptSource)
                ) {
                    continue;
                }

                $mergedEntries[$sourcePath] = new TreeEntry($mergedTarget->mode, $sourcePath, $mergedTarget->oid);
                $conflicts[] = new TreeMergeConflict(
                    self::joinPath($pathPrefix, self::joinPath($targetPath, $sourcePath)),
                    'nested-directory-rename',
                    $baseSource,
                    $renamedByOurs ? $renamedTarget : $keptSource,
                    $renamedByOurs ? $keptSource : $renamedTarget,
                );
            }
        };

        $record($ourEntries, $theirEntries, self::detectedRenames($baseEntries, $ourEntries, $readObject), true);
        $record($theirEntries, $ourEntries, self::detectedRenames($baseEntries, $theirEntries, $readObject), false);

        if ($conflicts === []) {
            return ['entry' => $mergedEntry, 'conflicts' => []];
        }

        $entries = array_values($mergedEntries);
        self::sortEntries($entries);

        return [
            'entry' => new TreeEntry($mergedEntry->mode, $mergedEntry->filename, $writeObject((new Tree($entries))->toObject())),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $sideEntries
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function detectedRenames(array $baseEntries, array $sideEntries, ?callable $readObject): array
    {
        $renames = self::exactRenames($baseEntries, $sideEntries);
        if ($readObject === null) {
            return $renames;
        }

        $renames += self::similarBlobRenames($baseEntries, $sideEntries, $renames, $readObject);

        return $renames + self::similarTreeRenames($baseEntries, $sideEntries, $renames, $readObject);
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $sideEntries
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function exactRenames(array $baseEntries, array $sideEntries): array
    {
        $deletedByObject = [];
        foreach ($baseEntries as $path => $entry) {
            if (isset($sideEntries[$path])) {
                continue;
            }
            $key = self::entryIdentity($entry);
            $deletedByObject[$key] ??= [];
            $deletedByObject[$key][$path] = $entry;
        }

        $addedByObject = [];
        foreach ($sideEntries as $path => $entry) {
            if (isset($baseEntries[$path])) {
                continue;
            }
            $key = self::entryIdentity($entry);
            $addedByObject[$key] ??= [];
            $addedByObject[$key][$path] = $entry;
        }

        $renames = [];
        foreach ($deletedByObject as $key => $deletedEntries) {
            if (count($deletedEntries) !== 1) {
                continue;
            }
            $candidates = $addedByObject[$key] ?? [];
            if (count($candidates) === 1) {
                $path = array_key_first($deletedEntries);
                $newPath = array_key_first($candidates);
                $renames[$path] = ['path' => $newPath, 'entry' => $candidates[$newPath]];
            }
        }

        return $renames;
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $sideEntries
     * @param array<string, array{path:string,entry:TreeEntry}> $knownRenames
     * @param callable(string): GitObject $readObject
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function similarBlobRenames(array $baseEntries, array $sideEntries, array $knownRenames, callable $readObject): array
    {
        $usedNewPaths = [];
        foreach ($knownRenames as $rename) {
            $usedNewPaths[$rename['path']] = true;
        }

        $candidatesByDeletedPath = [];
        foreach ($baseEntries as $deletedPath => $baseEntry) {
            if (isset($sideEntries[$deletedPath]) || isset($knownRenames[$deletedPath]) || !$baseEntry->isBlob()) {
                continue;
            }

            foreach ($sideEntries as $addedPath => $sideEntry) {
                if (isset($baseEntries[$addedPath]) || isset($usedNewPaths[$addedPath]) || !$sideEntry->isBlob() || $baseEntry->mode !== $sideEntry->mode) {
                    continue;
                }

                $score = self::blobSimilarity($baseEntry, $sideEntry, $readObject);
                if ($score < 60) {
                    continue;
                }
                $candidatesByDeletedPath[$deletedPath][$addedPath] = ['score' => $score, 'entry' => $sideEntry];
            }
        }

        return self::strictBestSimilarityRenames($candidatesByDeletedPath);
    }

    /**
     * @param array<string, TreeEntry> $baseEntries
     * @param array<string, TreeEntry> $sideEntries
     * @param array<string, array{path:string,entry:TreeEntry}> $knownRenames
     * @param callable(string): GitObject $readObject
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function similarTreeRenames(array $baseEntries, array $sideEntries, array $knownRenames, callable $readObject): array
    {
        $usedNewPaths = [];
        foreach ($knownRenames as $rename) {
            $usedNewPaths[$rename['path']] = true;
        }

        $candidatesByDeletedPath = [];
        foreach ($baseEntries as $deletedPath => $baseEntry) {
            if (isset($sideEntries[$deletedPath]) || isset($knownRenames[$deletedPath]) || !$baseEntry->isTree()) {
                continue;
            }

            foreach ($sideEntries as $addedPath => $sideEntry) {
                if (isset($baseEntries[$addedPath]) || isset($usedNewPaths[$addedPath]) || !$sideEntry->isTree()) {
                    continue;
                }

                $score = self::treeSimilarity($baseEntry, $sideEntry, $readObject);
                if ($score < 60) {
                    continue;
                }
                $candidatesByDeletedPath[$deletedPath][$addedPath] = ['score' => $score, 'entry' => $sideEntry];
            }
        }

        return self::strictBestSimilarityRenames($candidatesByDeletedPath);
    }

    /**
     * @param array<string, array<string, array{score:int,entry:TreeEntry}>> $candidatesByDeletedPath
     * @return array<string, array{path:string,entry:TreeEntry}>
     */
    private static function strictBestSimilarityRenames(array $candidatesByDeletedPath): array
    {
        $selected = [];
        $selectedTargetCounts = [];
        foreach ($candidatesByDeletedPath as $deletedPath => $candidates) {
            uasort(
                $candidates,
                static fn (array $left, array $right): int => $right['score'] <=> $left['score'],
            );
            $candidatePaths = array_keys($candidates);
            $bestPath = $candidatePaths[0] ?? null;
            if ($bestPath === null) {
                continue;
            }
            $secondPath = $candidatePaths[1] ?? null;
            if ($secondPath !== null && $candidates[$secondPath]['score'] === $candidates[$bestPath]['score']) {
                continue;
            }

            $selected[$deletedPath] = ['path' => $bestPath, 'entry' => $candidates[$bestPath]['entry']];
            $selectedTargetCounts[$bestPath] = ($selectedTargetCounts[$bestPath] ?? 0) + 1;
        }

        $renames = [];
        foreach ($selected as $deletedPath => $rename) {
            if (($selectedTargetCounts[$rename['path']] ?? 0) !== 1) {
                continue;
            }
            $renames[$deletedPath] = $rename;
        }

        return $renames;
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    private static function blobSimilarity(TreeEntry $baseEntry, TreeEntry $sideEntry, callable $readObject): int
    {
        $base = self::readTypedObject($readObject, $baseEntry->oid, 'blob')->body;
        $side = self::readTypedObject($readObject, $sideEntry->oid, 'blob')->body;
        if ($base === $side) {
            return 100;
        }
        if (strlen($base) > 262144 || strlen($side) > 262144 || self::containsNul($base . $side)) {
            return 0;
        }

        $baseLines = self::contentLines($base);
        $sideLines = self::contentLines($side);
        $total = count($baseLines) + count($sideLines);
        if ($total === 0) {
            return 0;
        }

        $sideCounts = array_count_values($sideLines);
        $overlap = 0;
        foreach ($baseLines as $line) {
            if (($sideCounts[$line] ?? 0) === 0) {
                continue;
            }
            $sideCounts[$line]--;
            $overlap++;
        }

        return (int) floor(($overlap * 200) / $total);
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    private static function treeSimilarity(TreeEntry $baseEntry, TreeEntry $sideEntry, callable $readObject): int
    {
        $baseCount = 0;
        $sideCount = 0;
        $baseLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $baseEntry->oid, 'tree')), $readObject, '', 0, $baseCount);
        $sideLeaves = self::flattenTreeLeaves(Tree::fromObject(self::readTypedObject($readObject, $sideEntry->oid, 'tree')), $readObject, '', 0, $sideCount);
        if ($baseLeaves === null || $sideLeaves === null || $baseLeaves === [] || $sideLeaves === []) {
            return 0;
        }

        $score = 0;
        $matchedBasePaths = [];
        $matchedSidePaths = [];
        foreach ($baseLeaves as $path => $baseLeaf) {
            $sideLeaf = $sideLeaves[$path] ?? null;
            if ($sideLeaf === null || !self::sameContentKind($baseLeaf, $sideLeaf)) {
                continue;
            }
            $matchedBasePaths[$path] = true;
            $matchedSidePaths[$path] = true;
            if (self::sameEntry($baseLeaf, $sideLeaf) || $baseLeaf->oid === $sideLeaf->oid) {
                $score += 100;
                continue;
            }
            if ($baseLeaf->isBlob() && $sideLeaf->isBlob()) {
                $score += self::blobSimilarity($baseLeaf, $sideLeaf, $readObject);
            }
        }

        $candidateByBasePath = [];
        $candidateUseCounts = [];
        foreach ($baseLeaves as $basePath => $baseLeaf) {
            if (isset($matchedBasePaths[$basePath])) {
                continue;
            }

            foreach ($sideLeaves as $sidePath => $sideLeaf) {
                if (isset($matchedSidePaths[$sidePath]) || $baseLeaf->mode !== $sideLeaf->mode || $baseLeaf->kind() !== $sideLeaf->kind()) {
                    continue;
                }

                $leafScore = self::sameEntry($baseLeaf, $sideLeaf) ? 100 : 0;
                if ($leafScore === 0 && $baseLeaf->isBlob() && $sideLeaf->isBlob()) {
                    $leafScore = self::blobSimilarity($baseLeaf, $sideLeaf, $readObject);
                }
                if ($leafScore < 60) {
                    continue;
                }

                $candidateByBasePath[$basePath][$sidePath] = $leafScore;
                $candidateUseCounts[$sidePath] = ($candidateUseCounts[$sidePath] ?? 0) + 1;
            }
        }

        foreach ($candidateByBasePath as $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }
            $sidePath = array_key_first($candidates);
            if (($candidateUseCounts[$sidePath] ?? 0) !== 1) {
                continue;
            }
            $score += $candidates[$sidePath];
        }

        return (int) floor($score / max(count($baseLeaves), count($sideLeaves)));
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return null|array<string, TreeEntry>
     */
    private static function flattenTreeLeaves(Tree $tree, callable $readObject, string $prefix, int $depth, int &$count): ?array
    {
        if ($depth > 16 || $count > 512) {
            return null;
        }

        $leaves = [];
        foreach ($tree->entries as $entry) {
            $path = self::joinPath($prefix, $entry->filename);
            if ($entry->isTree()) {
                $nested = self::flattenTreeLeaves(
                    Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')),
                    $readObject,
                    $path,
                    $depth + 1,
                    $count,
                );
                if ($nested === null) {
                    return null;
                }
                $leaves += $nested;
                continue;
            }

            $leaves[$path] = $entry;
            $count++;
            if ($count > 512) {
                return null;
            }
        }

        return $leaves;
    }

    /**
     * @return list<string>
     */
    private static function contentLines(string $content): array
    {
        $lines = preg_split('/\R/u', trim($content));
        if ($lines === false) {
            $lines = explode("\n", trim($content));
        }

        return array_slice(array_values(array_filter($lines, static fn (string $line): bool => $line !== '')), 0, 512);
    }

    private static function entryIdentity(TreeEntry $entry): string
    {
        return $entry->mode . "\0" . $entry->oid;
    }

    private static function sameContentKind(TreeEntry $left, TreeEntry $right): bool
    {
        return ($left->isBlob() && $right->isBlob()) || $left->kind() === $right->kind();
    }

    /**
     * @param list<TreeEntry> $entries
     */
    private static function sortEntries(array &$entries): void
    {
        usort($entries, static fn (TreeEntry $left, TreeEntry $right): int => strcmp($left->filename, $right->filename));
    }

    private static function conflictReason(?TreeEntry $base, ?TreeEntry $ours, ?TreeEntry $theirs): string
    {
        if ($ours !== null && $theirs !== null && $ours->isTree() !== $theirs->isTree()) {
            return 'directory-file';
        }
        if ($base === null && $ours !== null && $theirs !== null) {
            return 'add-add';
        }
        if (($ours === null && $base !== null && $theirs !== null) || ($theirs === null && $base !== null && $ours !== null)) {
            return 'delete-modify';
        }
        if ($ours !== null && $theirs !== null && $ours->kind() !== $theirs->kind()) {
            return 'type-change';
        }
        if ($ours !== null && $theirs !== null && $ours->oid === $theirs->oid && $ours->mode !== $theirs->mode) {
            return 'mode-change';
        }

        return 'modify-modify';
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    private static function readTypedObject(callable $readObject, string $oid, string $type): GitObject
    {
        $object = $readObject($oid);
        if (!$object instanceof GitObject) {
            throw new \RuntimeException('Object reader must return GitObject instances');
        }
        if ($object->type !== $type) {
            throw new \RuntimeException("Expected {$type} object for {$oid}, got {$object->type}");
        }

        return $object;
    }

    private static function containsNul(string $bytes): bool
    {
        return str_contains($bytes, "\0");
    }

    private static function shouldUseBinaryMerge(string $base, string $ours, string $theirs, ?int $bigFileThreshold): bool
    {
        if (self::containsNul($base . $ours . $theirs)) {
            return true;
        }

        return $bigFileThreshold !== null && max(strlen($base), strlen($ours), strlen($theirs)) > $bigFileThreshold;
    }

    private static function joinPath(string $prefix, string $path): string
    {
        return $prefix === '' ? $path : $prefix . '/' . $path;
    }
}
