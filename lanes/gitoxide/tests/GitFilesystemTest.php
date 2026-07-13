<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitFilesystem;

$tmpDir = static function (string $name): string {
    $dir = sys_get_temp_dir() . '/port-libs-gitoxide-fs-' . $name . '-' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create temporary directory {$dir}");
    }

    return $dir;
};

$removeTree = null;
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return;
    }
    foreach (array_diff($entries, ['.', '..']) as $entry) {
        $removeTree($path . '/' . $entry);
    }
    @rmdir($path);
};

$charCount = static function (string $value): int {
    $count = preg_match_all('/./us', $value);
    if ($count === false) {
        throw new RuntimeException('Unable to count UTF-8 characters');
    }

    return $count;
};

$write = static function (string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create directory {$dir}");
    }
    file_put_contents($path, $contents);
};

return [
    'upstream gix-fs current_dir.rs precompose_unicode' => static function (TestRunner $t) use ($tmpDir, $removeTree, $charCount): void {
        $originalCwd = getcwd();
        if ($originalCwd === false) {
            throw new RuntimeException('Unable to determine original current directory');
        }

        $root = $tmpDir('current-dir');
        try {
            $decomposed = "a\u{0308}";
            $cwd = $root . '/' . $decomposed;
            if (!mkdir($cwd) && !is_dir($cwd)) {
                throw new RuntimeException("Unable to create decomposed cwd {$cwd}");
            }
            chdir($cwd);

            $dirname = basename(GitFilesystem::currentDir(false));
            $t->same($charCount($decomposed), $charCount($dirname));

            $precomposed = "\u{00E4}";
            $dirname = basename(GitFilesystem::currentDir(true));
            $t->same($charCount($precomposed), $charCount($dirname));
        } finally {
            chdir($originalCwd);
            $removeTree($root);
        }
    },

    'upstream gix-dir dir_cwd.rs prefixes_work_as_expected' => static function (TestRunner $t) use ($tmpDir, $removeTree, $write): void {
        $originalCwd = getcwd();
        if ($originalCwd === false) {
            throw new RuntimeException('Unable to determine original current directory');
        }

        $root = $tmpDir('dir-cwd') . '/only-untracked';
        try {
            $write($root . '/.git/HEAD', "ref: refs/heads/main\n");
            $write($root . '/a', '');
            $write($root . '/b', '');
            $write($root . '/d/a', '');
            $write($root . '/d/b', '');
            $write($root . '/d/d/a', '');
            $write($root . '/c', '');

            chdir($root . '/d');
            $walk = GitFilesystem::walkUntrackedPrefix('..', '../d');

            $t->same(
                [
                    'read_dir_calls' => 2,
                    'returned_entries' => 3,
                    'seen_entries' => 3,
                ],
                $walk['outcome'],
            );
            $t->same(
                [
                    ['path' => 'd/a', 'status' => GitFilesystem::STATUS_UNTRACKED, 'kind' => GitFilesystem::KIND_FILE, 'pathspecMatch' => GitFilesystem::PATHSPEC_MATCH_PREFIX],
                    ['path' => 'd/b', 'status' => GitFilesystem::STATUS_UNTRACKED, 'kind' => GitFilesystem::KIND_FILE, 'pathspecMatch' => GitFilesystem::PATHSPEC_MATCH_PREFIX],
                    ['path' => 'd/d/a', 'status' => GitFilesystem::STATUS_UNTRACKED, 'kind' => GitFilesystem::KIND_FILE, 'pathspecMatch' => GitFilesystem::PATHSPEC_MATCH_PREFIX],
                ],
                $walk['entries'],
            );
        } finally {
            chdir($originalCwd);
            $removeTree(dirname($root));
        }
    },
];
