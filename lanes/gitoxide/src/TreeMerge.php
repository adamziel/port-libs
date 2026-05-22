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
}
