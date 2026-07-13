<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitDiscover
{
    private const MAX_GITDIR_FILE_BYTES = 65536;

    public const KIND_POSSIBLY_BARE = 'possibly-bare';
    public const KIND_WORK_TREE = 'worktree';
    public const KIND_WORK_TREE_GIT_DIR = 'worktree-git-dir';
    public const KIND_SUBMODULE = 'submodule';
    public const KIND_SUBMODULE_GIT_DIR = 'submodule-git-dir';

    private const INTERNAL_MAYBE_REPO = 'maybe-repo';
    private const INTERNAL_LINKED_WORK_TREE_DIR = 'linked-worktree-dir';
    private const INTERNAL_WORK_TREE_GIT_DIR = 'worktree-git-dir';
    private const INTERNAL_SUBMODULE = 'submodule';

    public static function parseGitdir(string $input): string
    {
        $prefix = 'gitdir: ';
        if (!str_starts_with($input, $prefix)) {
            throw new \InvalidArgumentException("Format should be 'gitdir: <path>'");
        }

        $path = rtrim(substr($input, strlen($prefix)));
        if ($path === '') {
            throw new \InvalidArgumentException("Format should be 'gitdir: <path>'");
        }
        if (preg_match('//u', $path) !== 1) {
            throw new \InvalidArgumentException('Gitdir path is not valid UTF-8');
        }

        return $path;
    }

    public static function gitdirFromFile(string $path): string
    {
        $gitDir = self::parseGitdir(self::readLimitedRegularFile($path));
        if (!self::isAbsolutePath($gitDir) && ($parent = dirname($path)) !== '.') {
            return self::joinPath($parent, $gitDir);
        }

        return $gitDir;
    }

    /**
     * @return array{kind:string,linkedGitDir?:?string,gitDir?:string,workDir?:string}
     */
    public static function isGit(string $gitDir): array
    {
        if (!file_exists($gitDir)) {
            throw new \RuntimeException("Git directory candidate does not exist: {$gitDir}");
        }

        $isGitFile = is_file($gitDir);
        $dotGit = $isGitFile ? self::gitdirFromFile($gitDir) : $gitDir;

        if (!file_exists(self::joinPath($dotGit, 'HEAD'))) {
            throw new \RuntimeException("Missing HEAD in git directory: {$dotGit}");
        }

        if ($isGitFile) {
            $commonDirPath = self::joinPath($dotGit, 'commondir');
            $commonDir = self::plainFilePath($commonDirPath);
            if ($commonDir !== null) {
                $commonDir = self::joinPath($dotGit, $commonDir);
                $internalKind = self::INTERNAL_LINKED_WORK_TREE_DIR;
            } else {
                $commonDir = $dotGit;
                $internalKind = self::INTERNAL_SUBMODULE;
            }
        } else {
            $commonDir = null;
            $worktreeGitFile = null;
            $commonDirPath = self::joinPath($dotGit, 'commondir');
            $gitdirPath = self::joinPath($dotGit, 'gitdir');

            $commonDirContents = self::plainFilePath($commonDirPath);
            if ($commonDirContents !== null) {
                $worktreeGitFile = self::plainFilePath($gitdirPath);
            }

            if ($commonDirContents !== null && $worktreeGitFile !== null) {
                $commonDir = self::joinPath($dotGit, $commonDirContents);
                $workDir = self::withoutDotGitDir($worktreeGitFile);
                $internalKind = self::INTERNAL_WORK_TREE_GIT_DIR;
            } else {
                $commonDir = $dotGit;
                $workDir = null;
                $internalKind = self::INTERNAL_MAYBE_REPO;
            }
        }

        $objectsPath = self::joinPath($commonDir, 'objects');
        if (!is_dir($objectsPath)) {
            throw new \RuntimeException("Missing objects directory: {$objectsPath}");
        }

        $refsPath = self::joinPath($commonDir, 'refs');
        if (!is_dir($refsPath)) {
            throw new \RuntimeException("Missing refs directory: {$refsPath}");
        }

        if ($internalKind === self::INTERNAL_LINKED_WORK_TREE_DIR) {
            return ['kind' => self::KIND_WORK_TREE, 'linkedGitDir' => $dotGit];
        }

        if ($internalKind === self::INTERNAL_WORK_TREE_GIT_DIR) {
            return ['kind' => self::KIND_WORK_TREE_GIT_DIR, 'workDir' => $workDir];
        }

        if ($internalKind === self::INTERNAL_SUBMODULE) {
            return ['kind' => self::KIND_SUBMODULE, 'gitDir' => $dotGit];
        }

        if (self::bare($dotGit) || self::hasGitExtension($dotGit)) {
            return ['kind' => self::KIND_POSSIBLY_BARE];
        }

        if (self::repositoryKind($dotGit) === self::KIND_SUBMODULE_GIT_DIR) {
            return ['kind' => self::KIND_SUBMODULE_GIT_DIR];
        }

        if (self::pathBaseName($dotGit) === '.git') {
            return ['kind' => self::KIND_WORK_TREE, 'linkedGitDir' => null];
        }

        return ['kind' => self::KIND_POSSIBLY_BARE];
    }

    private static function bare(string $gitDir): bool
    {
        return !file_exists(self::joinPath($gitDir, 'index')) && self::pathBaseName($gitDir) !== '.git';
    }

    private static function plainFilePath(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        return rtrim(self::readLimitedRegularFile($path));
    }

    private static function readLimitedRegularFile(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Expected a regular file: {$path}");
        }

        $size = filesize($path);
        if ($size !== false && $size > self::MAX_GITDIR_FILE_BYTES) {
            throw new \RuntimeException("Refusing to open files larger than " . self::MAX_GITDIR_FILE_BYTES . " bytes: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$path}");
        }

        return $contents;
    }

    private static function joinPath(string $base, string $path): string
    {
        if ($path === '') {
            return $base;
        }
        if (self::isAbsolutePath($path)) {
            return $path;
        }

        $base = rtrim($base, '/\\');
        return $base === '' ? $path : $base . '/' . $path;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function pathBaseName(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }

        $slash = strrpos($path, '/');
        return $slash === false ? $path : substr($path, $slash + 1);
    }

    private static function hasGitExtension(string $path): bool
    {
        $base = self::pathBaseName($path);
        return $base !== '.git' && str_ends_with($base, '.git');
    }

    private static function withoutDotGitDir(string $path): string
    {
        if (self::pathBaseName($path) !== '.git') {
            return $path;
        }

        $path = rtrim(str_replace('\\', '/', $path), '/');
        $slash = strrpos($path, '/');
        return $slash === false ? '' : substr($path, 0, $slash);
    }

    private static function repositoryKind(string $gitDir): ?string
    {
        $normalized = trim(str_replace('\\', '/', $gitDir), '/');
        if ($normalized === '') {
            return null;
        }

        $parts = explode('/', $normalized);
        if (end($parts) === '.git') {
            return 'common';
        }

        $lastComponent = null;
        for ($i = count($parts) - 2; $i >= 0; $i--) {
            if ($parts[$i] === '.git') {
                return match ($lastComponent) {
                    'modules' => self::KIND_SUBMODULE_GIT_DIR,
                    'worktrees' => 'linked-worktree-git-dir',
                    default => null,
                };
            }
            $lastComponent = $parts[$i];
        }

        return null;
    }
}
