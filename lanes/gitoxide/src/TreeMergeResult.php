<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeMergeResult
{
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
     * @return list<MergeWorktreeFile>
     */
    public function worktreeConflictFiles(callable $readObject): array
    {
        $files = [];
        foreach ($this->conflicts as $conflict) {
            if ($conflict->reason !== 'content-conflict') {
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
