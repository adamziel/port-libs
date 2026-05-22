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

        $paths = array_keys($baseEntries + $ourEntries + $theirEntries);
        sort($paths, SORT_STRING);

        $merged = [];
        $conflicts = [];
        foreach ($paths as $path) {
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

        $paths = array_keys($baseEntries + $ourEntries + $theirEntries);
        sort($paths, SORT_STRING);

        $merged = [];
        $conflicts = [];
        foreach ($paths as $path) {
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
