<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitFilesystem
{
    public const STATUS_UNTRACKED = 'untracked';
    public const KIND_FILE = 'file';
    public const PATHSPEC_MATCH_PREFIX = 'prefix';

    public static function currentDir(bool $precomposeUnicode): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current directory');
        }

        return $precomposeUnicode ? self::precomposeUnicode($cwd) : $cwd;
    }

    /**
     * @return array{
     *     outcome:array{read_dir_calls:int,returned_entries:int,seen_entries:int},
     *     root:string,
     *     entries:list<array{path:string,status:string,kind:string,pathspecMatch:string}>
     * }
     */
    public static function walkUntrackedPrefix(string $worktreeRoot, ?string $prefixRoot = null): array
    {
        $worktree = self::realDirectory($worktreeRoot);
        $root = $prefixRoot === null ? $worktree : self::realDirectory($prefixRoot);
        $relativeRoot = self::relativePath($worktree, $root);

        $entries = [];
        $outcome = [
            'read_dir_calls' => 0,
            'returned_entries' => 0,
            'seen_entries' => 0,
        ];

        self::walkUntrackedFiles($root, $relativeRoot, $entries, $outcome);
        usort($entries, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);
        $outcome['returned_entries'] = count($entries);

        return [
            'outcome' => $outcome,
            'root' => $relativeRoot,
            'entries' => $entries,
        ];
    }

    private static function precomposeUnicode(string $path): string
    {
        if (preg_match('//u', $path) !== 1) {
            return $path;
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($path, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return strtr($path, [
            "A\u{0308}" => "\u{00C4}",
            "O\u{0308}" => "\u{00D6}",
            "U\u{0308}" => "\u{00DC}",
            "a\u{0308}" => "\u{00E4}",
            "o\u{0308}" => "\u{00F6}",
            "u\u{0308}" => "\u{00FC}",
        ]);
    }

    /**
     * @param list<array{path:string,status:string,kind:string,pathspecMatch:string}> $entries
     * @param array{read_dir_calls:int,returned_entries:int,seen_entries:int} $outcome
     */
    private static function walkUntrackedFiles(string $directory, string $relativePrefix, array &$entries, array &$outcome): void
    {
        $names = scandir($directory);
        if ($names === false) {
            throw new \RuntimeException("Unable to read directory: {$directory}");
        }
        $outcome['read_dir_calls']++;

        foreach ($names as $name) {
            if ($name === '.' || $name === '..' || $name === '.git') {
                continue;
            }

            $path = $directory . '/' . $name;
            $relativePath = $relativePrefix === '' ? $name : $relativePrefix . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                self::walkUntrackedFiles($path, $relativePath, $entries, $outcome);
                continue;
            }

            if (!is_file($path)) {
                continue;
            }

            $outcome['seen_entries']++;
            $entries[] = [
                'path' => $relativePath,
                'status' => self::STATUS_UNTRACKED,
                'kind' => self::KIND_FILE,
                'pathspecMatch' => self::PATHSPEC_MATCH_PREFIX,
            ];
        }
    }

    private static function realDirectory(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException("Directory does not exist: {$path}");
        }

        return self::stripTrailingSlash(str_replace('\\', '/', $real));
    }

    private static function relativePath(string $base, string $path): string
    {
        if ($path === $base) {
            return '';
        }
        if (!str_starts_with($path, $base . '/')) {
            throw new \InvalidArgumentException("Directory {$path} is outside worktree root {$base}");
        }

        return substr($path, strlen($base) + 1);
    }

    private static function stripTrailingSlash(string $path): string
    {
        if ($path === '/') {
            return $path;
        }
        if (preg_match('/^[A-Za-z]:\/$/', $path) === 1) {
            return $path;
        }

        return rtrim($path, '/');
    }
}
