<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreePathspecWalk
{
    /**
     * @param callable(TreeEntry,string): Tree|GitObject $readTree
     * @return list<TreeWalkEntry>
     */
    public static function breadthFirst(
        Tree $root,
        PathspecSearch $pathspecs,
        callable $readTree,
        bool $includeTrees = true,
        ?GitAttributes $attributes = null,
    ): array {
        $visited = [];
        $directIncludedPaths = [];
        $queue = [['', $root]];

        while ($queue !== []) {
            [$directory, $tree] = array_shift($queue);
            foreach ($tree->entries as $entry) {
                $path = $directory === '' ? $entry->filename : $directory . '/' . $entry->filename;
                $isTree = $entry->isTree();
                $matchesAsDirectory = $isTree || $entry->isCommit();
                $match = $pathspecs->match($path, $matchesAsDirectory, $attributes);
                $canDescend = $isTree && $pathspecs->canMatch($path, true);
                if ($match !== null && $match->isExcluded()) {
                    if ($match->kind === PathspecMatch::KIND_WILDCARD && $canDescend) {
                        $queue[] = [$path, self::readSubtree($readTree, $entry, $path)];
                    }
                    continue;
                }
                if ($match !== null && ($includeTrees || !$isTree)) {
                    $visited[] = [new TreeWalkEntry($path, $entry, $match->kind, $match->sequenceNumber), true, $isTree];
                    $directIncludedPaths[$path] = true;
                } elseif ($isTree && $includeTrees && $pathspecs->directoryMatchesPrefix($path, true)) {
                    $visited[] = [new TreeWalkEntry($path, $entry, PathspecMatch::KIND_PREFIX, 0), false, true];
                }
                if (!$canDescend) {
                    continue;
                }

                $queue[] = [$path, self::readSubtree($readTree, $entry, $path)];
            }
        }

        $ancestorPaths = [];
        foreach (array_keys($directIncludedPaths) as $path) {
            while (str_contains($path, '/')) {
                $path = substr($path, 0, strrpos($path, '/'));
                $ancestorPaths[$path] = true;
            }
        }

        $records = [];
        foreach ($visited as [$record, $direct, $isTree]) {
            if ($direct || ($isTree && isset($ancestorPaths[$record->path]))) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param callable(TreeEntry,string): Tree|GitObject $readTree
     */
    private static function readSubtree(callable $readTree, TreeEntry $entry, string $path): Tree
    {
        $tree = $readTree($entry, $path);
        if ($tree instanceof Tree) {
            return $tree;
        }
        if ($tree instanceof GitObject) {
            return Tree::fromObject($tree);
        }

        throw new \UnexpectedValueException("Tree reader for {$path} must return a Tree or GitObject");
    }
}
