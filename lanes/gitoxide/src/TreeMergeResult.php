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
        $sourceConflictsResolvedByTargetAdd = self::sourceConflictsResolvedByTargetAdd($this->conflicts);
        $subtreeReplacementFollowers = self::directorySubtreeReplacementFollowerIndexes($this->conflicts);
        foreach ($this->conflicts as $index => $conflict) {
            if (isset($sourceConflictsResolvedByTargetAdd[$index])) {
                $tree = self::removeEntryAtPath($tree, $conflict->path, $readObject, $writeObject);
                continue;
            }
            if (isset($subtreeReplacementFollowers[$index])) {
                continue;
            }

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
            $subtreeReplacement = self::resolveDirectorySubtreeReplacement(
                $tree,
                $conflict,
                $resolutionForConflict,
                $readObject,
                $writeObject,
            );
            if ($subtreeReplacement !== null) {
                $tree = $subtreeReplacement;
                continue;
            }
            if ($isContentConflict && $resolutionForConflict === self::RESOLVE_ANCESTOR) {
                $ancestorContentResolution = self::resolveContentConflictAncestorEntries(
                    $tree,
                    $conflict,
                    $readObject,
                    $writeObject,
                );
                if ($ancestorContentResolution !== null) {
                    $tree = $ancestorContentResolution;
                    continue;
                }
            }

            $resolvedTree = !$isContentConflict
                ? self::mergedTreeEntryForResolvedConflict(
                    $conflict,
                    $resolutionForConflict,
                    $contentResolution,
                    $readObject,
                    $writeObject,
                )
                : null;
            $entry = $resolvedTree['entry'] ?? self::entryForResolution($conflict, $resolutionForConflict);
            $tree = self::removeEntryAtPath($tree, $conflict->path, $readObject, $writeObject);
            if ($entry !== null) {
                $targetPath = self::resolvedEntryPath($conflict, $entry);
                if ($targetPath !== $conflict->path) {
                    $tree = self::removeEntryAtPath($tree, $targetPath, $readObject, $writeObject);
                }
                $sourcePath = self::sourcePathForResolvedRename($conflict, $targetPath);
                if ($sourcePath !== null) {
                    $tree = self::removeEntryAtPath($tree, $sourcePath, $readObject, $writeObject);
                }
                $tree = self::setEntryAtPath($tree, $targetPath, $entry, $readObject, $writeObject);
            }
            if ($resolvedTree !== null) {
                array_push($remaining, ...$resolvedTree['conflicts']);
            }
        }

        return new self($tree, $remaining);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     * @return null|array{entry:TreeEntry,conflicts:list<TreeMergeConflict>}
     */
    private static function mergedTreeEntryForResolvedConflict(
        TreeMergeConflict $conflict,
        string $resolution,
        ?string $contentResolution,
        callable $readObject,
        callable $writeObject,
    ): ?array {
        if (!in_array($conflict->reason, ['rename-rename', 'nested-directory-rename'], true)) {
            return null;
        }
        if ($resolution === self::RESOLVE_ANCESTOR || $contentResolution === self::RESOLVE_ANCESTOR) {
            return null;
        }

        $selected = self::entryForResolution($conflict, $resolution);
        if (
            $conflict->base === null
            || $conflict->ours === null
            || $conflict->theirs === null
            || $selected === null
            || !$conflict->base->isTree()
            || !$conflict->ours->isTree()
            || !$conflict->theirs->isTree()
            || !$selected->isTree()
        ) {
            return null;
        }

        $merge = TreeMerge::mergeRecursive(
            Tree::fromObject(self::readTypedObject($readObject, $conflict->base->oid, 'tree')),
            Tree::fromObject(self::readTypedObject($readObject, $conflict->ours->oid, 'tree')),
            Tree::fromObject(self::readTypedObject($readObject, $conflict->theirs->oid, 'tree')),
            $readObject,
            $writeObject,
            self::blobMergeStyleForContentResolution($contentResolution),
        );
        $targetPath = self::resolvedEntryPath($conflict, $selected);

        return [
            'entry' => new TreeEntry($selected->mode, $selected->filename, $writeObject($merge->tree->toObject())),
            'conflicts' => self::rebaseConflicts($merge->conflicts, $targetPath),
        ];
    }

    private static function blobMergeStyleForContentResolution(?string $contentResolution): string
    {
        return match ($contentResolution) {
            self::RESOLVE_OURS => BlobMerge::STYLE_OURS,
            self::RESOLVE_THEIRS => BlobMerge::STYLE_THEIRS,
            default => BlobMerge::STYLE_MERGE,
        };
    }

    /**
     * @param list<TreeMergeConflict> $conflicts
     * @return list<TreeMergeConflict>
     */
    private static function rebaseConflicts(array $conflicts, string $prefix): array
    {
        $rebased = [];
        foreach ($conflicts as $conflict) {
            $rebased[] = new TreeMergeConflict(
                self::joinPath($prefix, $conflict->path),
                $conflict->reason,
                $conflict->base,
                $conflict->ours,
                $conflict->theirs,
                self::rebaseContextPaths($conflict->context, $prefix),
            );
        }

        return $rebased;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function rebaseContextPaths(array $context, string $prefix): array
    {
        foreach (['sourcePath'] as $key) {
            if (isset($context[$key]) && is_string($context[$key]) && $context[$key] !== '') {
                $context[$key] = self::joinPath($prefix, $context[$key]);
            }
        }

        $ancestorEntries = $context['ancestorEntries'] ?? null;
        if (is_array($ancestorEntries)) {
            $rebased = [];
            foreach ($ancestorEntries as $path => $entry) {
                if (is_string($path) && $path !== '' && $entry instanceof TreeEntry) {
                    $rebased[self::joinPath($prefix, $path)] = $entry;
                }
            }
            $context['ancestorEntries'] = $rebased;
        }

        return $context;
    }

    private static function joinPath(string $prefix, string $path): string
    {
        if ($prefix === '') {
            return $path;
        }
        if ($path === '') {
            return $prefix;
        }

        return $prefix . '/' . $path;
    }

    /**
     * @param list<TreeMergeConflict> $conflicts
     * @return array<int, true>
     */
    private static function directorySubtreeReplacementFollowerIndexes(array $conflicts): array
    {
        $relatedPaths = [];
        foreach ($conflicts as $conflict) {
            if ($conflict->reason !== 'directory-rename-subtree-replacement') {
                continue;
            }
            foreach (self::stringList($conflict->context['relatedConflictPaths'] ?? []) as $path) {
                $relatedPaths[$path] = true;
            }
        }

        if ($relatedPaths === []) {
            return [];
        }

        $indexes = [];
        foreach ($conflicts as $index => $conflict) {
            if (
                isset($relatedPaths[$conflict->path])
                && in_array($conflict->reason, ['rename-delete', 'directory-rename-suggested'], true)
            ) {
                $indexes[$index] = true;
            }
        }

        return $indexes;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function resolveDirectorySubtreeReplacement(
        Tree $tree,
        TreeMergeConflict $conflict,
        string $resolution,
        callable $readObject,
        callable $writeObject,
    ): ?Tree {
        if ($conflict->reason !== 'directory-rename-subtree-replacement') {
            return null;
        }

        $baseEntry = $conflict->context['baseEntry'] ?? null;
        $sourcePath = $conflict->context['sourcePath'] ?? null;
        $renamedByOurs = $conflict->context['renamedByOurs'] ?? null;
        if (!$baseEntry instanceof TreeEntry || !is_string($sourcePath) || !is_bool($renamedByOurs)) {
            return null;
        }

        $replacementPaths = self::stringList($conflict->context['replacementPaths'] ?? []);
        $suggestedPaths = array_fill_keys(self::stringList($conflict->context['suggestedPaths'] ?? []), true);
        $cleanReplacementPaths = array_values(array_filter(
            $replacementPaths,
            static fn (string $path): bool => !isset($suggestedPaths[$path]),
        ));

        $targetEntry = self::entryAtPathInTree($tree, $conflict->path, $readObject);
        $targetTree = $targetEntry !== null && $targetEntry->isTree()
            ? Tree::fromObject(self::readTypedObject($readObject, $targetEntry->oid, 'tree'))
            : new Tree([]);

        $resolved = self::removeEntryAtPath($tree, $conflict->path, $readObject, $writeObject);
        $resolved = self::removeEntryAtPath($resolved, $sourcePath, $readObject, $writeObject);

        if ($resolution === self::RESOLVE_ANCESTOR) {
            $resolved = self::setEntryAtPath($resolved, $sourcePath, $baseEntry, $readObject, $writeObject);
            $cleanTree = self::filterTreePaths($targetTree, $cleanReplacementPaths, $readObject, $writeObject);
            if ($targetEntry !== null && $cleanTree->entries !== []) {
                $resolved = self::setEntryAtPath(
                    $resolved,
                    $conflict->path,
                    new TreeEntry($targetEntry->mode, basename($conflict->path), $writeObject($cleanTree->toObject())),
                    $readObject,
                    $writeObject,
                );
            }

            return $resolved;
        }

        $choosesRenamedSide = $resolution === self::RESOLVE_OURS ? $renamedByOurs : !$renamedByOurs;
        if ($choosesRenamedSide) {
            $selectedEntry = self::entryForResolution($conflict, $resolution);
            if ($selectedEntry === null || !$selectedEntry->isTree()) {
                return null;
            }
            $selectedTree = Tree::fromObject(self::readTypedObject($readObject, $selectedEntry->oid, 'tree'));
            $selectedTree = self::overlayTreePaths($selectedTree, $targetTree, $cleanReplacementPaths, $readObject, $writeObject);

            return self::setEntryAtPath(
                $resolved,
                $conflict->path,
                new TreeEntry($selectedEntry->mode, basename($conflict->path), $writeObject($selectedTree->toObject())),
                $readObject,
                $writeObject,
            );
        }

        $replacementTree = self::filterTreePaths($targetTree, $replacementPaths, $readObject, $writeObject);
        if ($targetEntry === null || $replacementTree->entries === []) {
            return $resolved;
        }

        return self::setEntryAtPath(
            $resolved,
            $conflict->path,
            new TreeEntry($targetEntry->mode, basename($conflict->path), $writeObject($replacementTree->toObject())),
            $readObject,
            $writeObject,
        );
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function resolveContentConflictAncestorEntries(
        Tree $tree,
        TreeMergeConflict $conflict,
        callable $readObject,
        callable $writeObject,
    ): ?Tree {
        $ancestorEntries = $conflict->context['ancestorEntries'] ?? null;
        if (!is_array($ancestorEntries) || $ancestorEntries === []) {
            return null;
        }

        $resolved = self::removeEntryAtPath($tree, $conflict->path, $readObject, $writeObject);
        $paths = array_keys($ancestorEntries);
        sort($paths, SORT_STRING);
        $applied = false;
        foreach ($paths as $path) {
            $entry = $ancestorEntries[$path] ?? null;
            if (!is_string($path) || $path === '' || !$entry instanceof TreeEntry) {
                continue;
            }
            $resolved = self::setEntryAtPath($resolved, $path, $entry, $readObject, $writeObject);
            $applied = true;
        }

        return $applied ? $resolved : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @param list<TreeMergeConflict> $conflicts
     * @return array<int, true>
     */
    private static function sourceConflictsResolvedByTargetAdd(array $conflicts): array
    {
        $sourceIndexes = [];
        foreach ($conflicts as $targetIndex => $targetConflict) {
            if (
                $targetConflict->reason !== 'rename-target-add'
                || $targetConflict->base !== null
                || !self::hasTargetTypeClash($targetConflict)
            ) {
                continue;
            }

            foreach ($conflicts as $sourceIndex => $sourceConflict) {
                if (
                    $sourceIndex === $targetIndex
                    || !in_array($sourceConflict->reason, ['rename-modify', 'rename-delete'], true)
                    || !self::conflictsShareSideEntry($sourceConflict, $targetConflict)
                ) {
                    continue;
                }

                $sourceIndexes[$sourceIndex] = true;
            }
        }

        return $sourceIndexes;
    }

    private static function hasTargetTypeClash(TreeMergeConflict $conflict): bool
    {
        return $conflict->ours !== null
            && $conflict->theirs !== null
            && $conflict->ours->kind() !== $conflict->theirs->kind();
    }

    private static function conflictsShareSideEntry(TreeMergeConflict $left, TreeMergeConflict $right): bool
    {
        foreach ([$left->ours, $left->theirs] as $leftEntry) {
            if ($leftEntry === null) {
                continue;
            }

            foreach ([$right->ours, $right->theirs] as $rightEntry) {
                if ($rightEntry !== null && self::sameEntry($leftEntry, $rightEntry)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function sameEntry(TreeEntry $left, TreeEntry $right): bool
    {
        return $left->filename === $right->filename
            && $left->mode === $right->mode
            && $left->oid === $right->oid;
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

            $entry = self::entryAtPathInTree($this->tree, $conflict->path, $readObject);
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
        $resolvedPath = $conflict->context['resolvedPath'] ?? null;
        if (is_string($resolvedPath) && $resolvedPath !== '') {
            return $resolvedPath;
        }

        $baseName = basename($conflict->path);
        if ($entry->filename === $baseName) {
            return $conflict->path;
        }

        $directory = dirname($conflict->path);

        return ($directory === '.' ? '' : $directory . '/') . $entry->filename;
    }

    private static function sourcePathForResolvedRename(TreeMergeConflict $conflict, string $targetPath): ?string
    {
        $sourcePath = $conflict->context['sourcePath'] ?? null;
        if (!is_string($sourcePath) || $sourcePath === '' || $sourcePath === $targetPath || $sourcePath === $conflict->path) {
            return null;
        }

        return $sourcePath;
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
     * @param list<string> $paths
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function filterTreePaths(Tree $source, array $paths, callable $readObject, callable $writeObject): Tree
    {
        $filtered = new Tree([]);
        foreach ($paths as $path) {
            $entry = self::entryAtPathInTree($source, $path, $readObject);
            if ($entry === null) {
                continue;
            }
            $filtered = self::addEntryAtPath($filtered, $path, $entry, $readObject, $writeObject);
        }

        return $filtered;
    }

    /**
     * @param list<string> $paths
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function overlayTreePaths(
        Tree $target,
        Tree $source,
        array $paths,
        callable $readObject,
        callable $writeObject,
    ): Tree {
        foreach ($paths as $path) {
            $entry = self::entryAtPathInTree($source, $path, $readObject);
            if ($entry === null) {
                continue;
            }
            $target = self::addEntryAtPath($target, $path, $entry, $readObject, $writeObject);
        }

        return $target;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param callable(GitObject): string $writeObject
     */
    private static function addEntryAtPath(Tree $tree, string $path, TreeEntry $entry, callable $readObject, callable $writeObject): Tree
    {
        $parts = explode('/', $path);
        $name = array_shift($parts);
        if ($name === null || $name === '') {
            return $tree;
        }

        $entries = self::entriesByName($tree);
        if ($parts === []) {
            $entries[$name] = new TreeEntry($entry->mode, $name, $entry->oid);

            return self::treeFromEntries($entries);
        }

        $parent = $entries[$name] ?? new TreeEntry('40000', $name, $writeObject((new Tree([]))->toObject()));
        if (!$parent->isTree()) {
            return $tree;
        }

        $nested = self::addEntryAtPath(
            Tree::fromObject(self::readTypedObject($readObject, $parent->oid, 'tree')),
            implode('/', $parts),
            $entry,
            $readObject,
            $writeObject,
        );
        $entries[$name] = new TreeEntry($parent->mode, $parent->filename, $writeObject($nested->toObject()));

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
    private static function entryAtPathInTree(Tree $tree, string $path, callable $readObject): ?TreeEntry
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
