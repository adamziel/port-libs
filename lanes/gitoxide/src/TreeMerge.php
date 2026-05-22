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
    ): TreeMergeResult {
        return self::mergeRecursiveAt($base, $ours, $theirs, $readObject, $writeObject, $conflictStyle, '');
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
    ): TreeMergeResult {
        $baseEntries = self::entriesByName($base);
        $ourEntries = self::entriesByName($ours);
        $theirEntries = self::entriesByName($theirs);

        [$renameConflicts, $consumedPaths, $renameMerged] = self::renameConflicts($baseEntries, $ourEntries, $theirEntries, $pathPrefix);
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

            $contentMerge = self::tryMergeChangedEntry($path, $fullPath, $baseEntry, $ourEntry, $theirEntry, $readObject, $writeObject, $conflictStyle);
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
            );

            return [
                'entry' => new TreeEntry($ourEntry->mode, $path, $writeObject($result->tree->toObject())),
                'conflicts' => $result->conflicts,
            ];
        }

        if (!$baseEntry->isBlob() || !$ourEntry->isBlob() || !$theirEntry->isBlob()) {
            return null;
        }
        if ($baseEntry->mode !== $ourEntry->mode || $baseEntry->mode !== $theirEntry->mode) {
            return null;
        }

        $baseBlob = self::readTypedObject($readObject, $baseEntry->oid, 'blob');
        $ourBlob = self::readTypedObject($readObject, $ourEntry->oid, 'blob');
        $theirBlob = self::readTypedObject($readObject, $theirEntry->oid, 'blob');
        $merge = self::containsNul($baseBlob->body . $ourBlob->body . $theirBlob->body)
            ? BlobMerge::mergeBinary($baseBlob->body, $ourBlob->body, $theirBlob->body)
            : BlobMerge::mergeText(
                $baseBlob->body,
                $ourBlob->body,
                $theirBlob->body,
                $conflictStyle,
                'base/' . $fullPath,
                'ours/' . $fullPath,
                'theirs/' . $fullPath,
            );

        $conflicts = [];
        if (!$merge->isClean()) {
            $conflicts[] = new TreeMergeConflict($fullPath, 'content-conflict', $baseEntry, $ourEntry, $theirEntry);
        }

        return [
            'entry' => new TreeEntry($ourEntry->mode, $path, $writeObject(new GitObject('blob', $merge->content))),
            'conflicts' => $conflicts,
        ];
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
     * @return array{0:list<TreeMergeConflict>,1:array<string,true>,2:list<TreeEntry>}
     */
    private static function renameConflicts(array $baseEntries, array $ourEntries, array $theirEntries, string $pathPrefix): array
    {
        $ourRenames = self::exactRenames($baseEntries, $ourEntries);
        $theirRenames = self::exactRenames($baseEntries, $theirEntries);
        $conflicts = [];
        $consumed = [];
        $merged = [];

        $paths = array_keys($baseEntries);
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $baseEntry = $baseEntries[$path];
            $ourRename = $ourRenames[$path] ?? null;
            $theirRename = $theirRenames[$path] ?? null;
            if ($ourRename !== null && $theirRename !== null) {
                if ($ourRename['path'] !== $theirRename['path']) {
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

    private static function entryIdentity(TreeEntry $entry): string
    {
        return $entry->mode . "\0" . $entry->oid;
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

    private static function joinPath(string $prefix, string $path): string
    {
        return $prefix === '' ? $path : $prefix . '/' . $path;
    }
}
