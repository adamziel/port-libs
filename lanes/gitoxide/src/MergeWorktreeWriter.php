<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeWorktreeWriter
{
    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeWorktreeFile>
     */
    public static function writeMergedTree(TreeMergeResult $result, string $directory, callable $readObject): array
    {
        $root = rtrim($directory, '/');
        if ($root === '') {
            throw new \InvalidArgumentException('Worktree directory must be non-empty');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new \RuntimeException("Unable to create worktree directory: {$root}");
        }

        $files = [];
        self::writeTree($result->tree, $root, '', $readObject, $files);

        return $files;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeWorktreeFile>
     */
    public static function checkoutMergedTree(TreeMergeResult $result, string $directory, callable $readObject): array
    {
        $root = rtrim($directory, '/');
        if ($root === '') {
            throw new \InvalidArgumentException('Worktree directory must be non-empty');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new \RuntimeException("Unable to create worktree directory: {$root}");
        }

        $expectedFiles = [];
        $expectedDirectories = [];
        self::collectTreePaths($result->tree, '', $readObject, $expectedFiles, $expectedDirectories);
        self::cleanDirectory($root, '', $expectedFiles, $expectedDirectories);

        return self::writeMergedTree($result, $root, $readObject);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeWorktreeFile>
     */
    public static function writeConflictFiles(TreeMergeResult $result, string $directory, callable $readObject): array
    {
        $root = rtrim($directory, '/');
        if ($root === '') {
            throw new \InvalidArgumentException('Worktree directory must be non-empty');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new \RuntimeException("Unable to create worktree directory: {$root}");
        }

        $files = $result->worktreeConflictFiles($readObject);
        foreach ($files as $file) {
            self::writeFile($root, $file->path, $file->content, $file->mode);
        }

        return $files;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param list<MergeWorktreeFile> $files
     */
    private static function writeTree(Tree $tree, string $root, string $prefix, callable $readObject, array &$files): void
    {
        foreach ($tree->entries as $entry) {
            $path = $prefix === '' ? $entry->filename : $prefix . '/' . $entry->filename;
            if ($entry->isTree()) {
                $object = self::readTypedObject($readObject, $entry->oid, 'tree');
                self::writeTree(Tree::fromObject($object), $root, $path, $readObject, $files);
                continue;
            }
            if (!$entry->isBlob()) {
                throw new \RuntimeException("Cannot write {$entry->kind()} entry to worktree: {$path}");
            }

            $object = self::readTypedObject($readObject, $entry->oid, 'blob');
            self::writeFile($root, $path, $object->body, $entry->mode);
            $files[] = new MergeWorktreeFile($path, $entry->mode, $entry->oid, $object->body);
        }
    }

    private static function writeFile(string $root, string $path, string $content, string $mode): void
    {
        $target = self::targetPath($root, $path);
        $directory = dirname($target);
        if (is_file($directory)) {
            throw new \RuntimeException("Cannot create worktree directory over file: {$directory}");
        }
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create worktree directory: {$directory}");
        }
        if (file_put_contents($target, $content) === false) {
            throw new \RuntimeException("Unable to write worktree file: {$target}");
        }
        chmod($target, (new TreeEntry($mode, basename($path), str_repeat('0', 40)))->isExecutable() ? 0755 : 0644);
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param array<string,true> $expectedFiles
     * @param array<string,true> $expectedDirectories
     */
    private static function collectTreePaths(Tree $tree, string $prefix, callable $readObject, array &$expectedFiles, array &$expectedDirectories): void
    {
        foreach ($tree->entries as $entry) {
            $path = $prefix === '' ? $entry->filename : $prefix . '/' . $entry->filename;
            if ($entry->isTree()) {
                $expectedDirectories[$path] = true;
                self::collectTreePaths(Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')), $path, $readObject, $expectedFiles, $expectedDirectories);
                continue;
            }
            if (!$entry->isBlob()) {
                throw new \RuntimeException("Cannot checkout {$entry->kind()} entry to worktree: {$path}");
            }
            self::targetPath('/tmp', $path);
            $expectedFiles[$path] = true;
        }
    }

    /**
     * @param array<string,true> $expectedFiles
     * @param array<string,true> $expectedDirectories
     */
    private static function cleanDirectory(string $root, string $prefix, array $expectedFiles, array $expectedDirectories): void
    {
        $directory = $prefix === '' ? $root : self::targetPath($root, $prefix);
        if (!is_dir($directory)) {
            return;
        }

        $children = scandir($directory);
        if ($children === false) {
            throw new \RuntimeException("Unable to scan worktree directory: {$directory}");
        }
        foreach ($children as $child) {
            if ($child === '.' || $child === '..' || ($prefix === '' && $child === '.git')) {
                continue;
            }

            $path = $prefix === '' ? $child : $prefix . '/' . $child;
            $target = self::targetPath($root, $path);
            if (isset($expectedFiles[$path])) {
                if (is_dir($target) && !is_link($target)) {
                    self::removePath($target);
                }
                continue;
            }
            if (isset($expectedDirectories[$path])) {
                if (is_dir($target) && !is_link($target)) {
                    self::cleanDirectory($root, $path, $expectedFiles, $expectedDirectories);
                } elseif (file_exists($target) || is_link($target)) {
                    self::removePath($target);
                }
                continue;
            }

            self::removePath($target);
        }
    }

    private static function removePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $children = scandir($path);
            if ($children === false) {
                throw new \RuntimeException("Unable to scan worktree path for removal: {$path}");
            }
            foreach ($children as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                self::removePath($path . '/' . $child);
            }
            if (!rmdir($path)) {
                throw new \RuntimeException("Unable to remove worktree directory: {$path}");
            }
            return;
        }

        if ((file_exists($path) || is_link($path)) && !unlink($path)) {
            throw new \RuntimeException("Unable to remove worktree path: {$path}");
        }
    }

    private static function targetPath(string $root, string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException("Unsafe worktree path: {$path}");
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new \InvalidArgumentException("Unsafe worktree path: {$path}");
            }
        }

        return $root . '/' . $path;
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
