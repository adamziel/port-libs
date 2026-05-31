<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeMergeResult
{
    public const RESOLVE_ANCESTOR = 'ancestor';
    public const RESOLVE_OURS = 'ours';
    public const RESOLVE_THEIRS = 'theirs';

    private const CONTENT_CONFLICT_REASONS = [
        'add-add' => true,
        'content-conflict' => true,
    ];

    private const TREE_CONFLICT_REASONS = [
        'delete-modify' => true,
        'directory-file' => true,
        'directory-rename-subtree-replacement' => true,
        'directory-rename-suggested' => true,
        'mode-change' => true,
        'nested-directory-rename' => true,
        'rename-delete' => true,
        'rename-modify' => true,
        'rename-rename' => true,
        'rename-target-add' => true,
        'type-change' => true,
    ];

    /**
     * @param list<TreeMergeConflict> $conflicts
     */
    public function __construct(
        public readonly Tree $tree,
        public readonly array $conflicts,
    ) {
        foreach ($conflicts as $conflict) {
            if (!$conflict instanceof TreeMergeConflict) {
                throw new \InvalidArgumentException('Tree merge conflicts must be TreeMergeConflict instances');
            }
        }
    }

    public function isClean(): bool
    {
        return $this->conflicts === [];
    }

    /**
     * @return list<MergeIndexEntry>
     */
    public function indexEntries(): array
    {
        $entries = [];
        foreach ($this->conflicts as $conflict) {
            foreach ([
                MergeIndexEntry::STAGE_ANCESTOR => $conflict->base,
                MergeIndexEntry::STAGE_OURS => $conflict->ours,
                MergeIndexEntry::STAGE_THEIRS => $conflict->theirs,
            ] as $stage => $entry) {
                if ($entry !== null) {
                    $entries[] = new MergeIndexEntry($conflict->path, $stage, $entry->mode, $entry->oid);
                }
            }
        }

        usort($entries, static fn (MergeIndexEntry $left, MergeIndexEntry $right): int => strcmp($left->path, $right->path) ?: $left->stage <=> $right->stage);

        return $entries;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    public function resolveTreeConflicts(
        callable $readObject,
        callable $writeObject,
        string $resolution,
        ?string $contentResolution = null,
    ): self {
        self::assertResolution($resolution);
        if ($contentResolution !== null) {
            self::assertResolution($contentResolution);
        }

        $tree = $this->tree;
        $remaining = [];
        foreach ($this->conflicts as $conflict) {
            $isContentConflict = isset(self::CONTENT_CONFLICT_REASONS[$conflict->reason]);
            if ($isContentConflict && $contentResolution === null) {
                $remaining[] = $conflict;
                continue;
            }
            if (!$isContentConflict && !isset(self::TREE_CONFLICT_REASONS[$conflict->reason])) {
                $remaining[] = $conflict;
                continue;
            }

            $resolutionForConflict = $isContentConflict ? $contentResolution : $resolution;
            if ($resolutionForConflict === null) {
                $remaining[] = $conflict;
                continue;
            }

            $entry = self::entryForResolution($conflict, $resolutionForConflict);
            $tree = self::removeEntryAtPath($tree, $conflict->path, $readObject, $writeObject);
            if ($entry !== null) {
                $targetPath = self::resolvedEntryPath($conflict, $entry);
                if ($targetPath !== $conflict->path) {
                    $tree = self::removeEntryAtPath($tree, $targetPath, $readObject, $writeObject);
                }
                $tree = self::setEntryAtPath($tree, $targetPath, $entry, $readObject, $writeObject);
            }
        }

        return new self($tree, $remaining);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeWorktreeFile>
     */
    public function worktreeConflictFiles(callable $readObject): array
    {
        $files = [];
        foreach ($this->conflicts as $conflict) {
            if (!in_array($conflict->reason, ['content-conflict', 'add-add'], true)) {
                continue;
            }

            $entry = $this->entryAtPath($this->tree, $conflict->path, $readObject);
            if ($entry === null || !$entry->isBlob()) {
                continue;
            }
            $object = self::readTypedObject($readObject, $entry->oid, 'blob');
            $files[] = new MergeWorktreeFile($conflict->path, $entry->mode, $entry->oid, $object->body);
        }

        return $files;
    }

    private static function assertResolution(string $resolution): void
    {
        if (!in_array($resolution, [self::RESOLVE_ANCESTOR, self::RESOLVE_OURS, self::RESOLVE_THEIRS], true)) {
            throw new \InvalidArgumentException("Unsupported tree conflict resolution: {$resolution}");
        }
    }

    private static function entryForResolution(TreeMergeConflict $conflict, string $resolution): ?TreeEntry
    {
        return match ($resolution) {
            self::RESOLVE_ANCESTOR => $conflict->base,
            self::RESOLVE_OURS => $conflict->ours,
            self::RESOLVE_THEIRS => $conflict->theirs,
        };
    }

    private static function resolvedEntryPath(TreeMergeConflict $conflict, TreeEntry $entry): string
    {
        $baseName = basename($conflict->path);
        if ($entry->filename === $baseName) {
            return $conflict->path;
        }

        $directory = dirname($conflict->path);

        return ($directory === '.' ? '' : $directory . '/') . $entry->filename;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function removeEntryAtPath(Tree $tree, string $path, callable $readObject, callable $writeObject): Tree
    {
        $parts = explode('/', $path);
        $name = array_shift($parts);
        if ($name === null || $name === '') {
            return $tree;
        }

        $entries = self::entriesByName($tree);
        if ($parts === []) {
            unset($entries[$name]);
        } else {
            $entry = $entries[$name] ?? null;
            if ($entry === null || !$entry->isTree()) {
                return $tree;
            }
            $nested = self::removeEntryAtPath(
                Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')),
                implode('/', $parts),
                $readObject,
                $writeObject,
            );
            $entries[$name] = new TreeEntry($entry->mode, $entry->filename, $writeObject($nested->toObject()));
        }

        return self::treeFromEntries($entries);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function setEntryAtPath(Tree $tree, string $path, TreeEntry $entry, callable $readObject, callable $writeObject): Tree
    {
        $parts = explode('/', $path);
        $name = array_shift($parts);
        if ($name === null || $name === '') {
            return $tree;
        }

        $entries = self::entriesByName($tree);
        if ($parts === []) {
            $entries[$name] = new TreeEntry($entry->mode, $name, $entry->oid);
        } else {
            $parent = $entries[$name] ?? null;
            if ($parent === null || !$parent->isTree()) {
                return $tree;
            }
            $nested = self::setEntryAtPath(
                Tree::fromObject(self::readTypedObject($readObject, $parent->oid, 'tree')),
                implode('/', $parts),
                $entry,
                $readObject,
                $writeObject,
            );
            $entries[$name] = new TreeEntry($parent->mode, $parent->filename, $writeObject($nested->toObject()));
        }

        return self::treeFromEntries($entries);
    }

    /**
     * @return array<string, TreeEntry>
     */
    private static function entriesByName(Tree $tree): array
    {
        $entries = [];
        foreach ($tree->entries as $entry) {
            $entries[$entry->filename] = $entry;
        }

        return $entries;
    }

    /**
     * @param array<string, TreeEntry> $entries
     */
    private static function treeFromEntries(array $entries): Tree
    {
        $values = array_values($entries);
        usort($values, static fn (TreeEntry $left, TreeEntry $right): int => strcmp($left->filename, $right->filename));

        return new Tree($values);
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    private function entryAtPath(Tree $tree, string $path, callable $readObject): ?TreeEntry
    {
        $parts = explode('/', $path);
        $current = $tree;
        $lastIndex = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($part === '') {
                return null;
            }
            $entry = $current->entryNamed($part);
            if ($entry === null) {
                return null;
            }
            if ($index === $lastIndex) {
                return $entry;
            }
            if (!$entry->isTree()) {
                return null;
            }
            $current = Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree'));
        }

        return null;
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
}
