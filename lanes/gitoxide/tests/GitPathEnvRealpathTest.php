<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPath;

$pathComponentCount = static function (string $path): int {
    return count(array_values(array_filter(explode('/', $path), static fn (string $component): bool => $component !== '')));
};

$tmpCounter = 0;
$tmpDir = static function (string $name) use (&$tmpCounter): string {
    $tmpCounter++;
    $dir = sys_get_temp_dir() . '/port-libs-gitpath-' . $name . '-' . getmypid() . '-' . $tmpCounter;
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create temp dir: {$dir}");
    }

    $real = realpath($dir);
    if (!is_string($real)) {
        throw new RuntimeException("Unable to canonicalize temp dir: {$dir}");
    }

    return str_replace('\\', '/', $real);
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $removeTree($path . '/' . $entry);
    }

    @rmdir($path);
};

$createSymlink = static function (string $from, string $to): void {
    $parent = dirname($from);
    if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
        throw new RuntimeException("Unable to create symlink parent: {$parent}");
    }

    if (!symlink($to, $from)) {
        throw new RuntimeException("Unable to create symlink: {$from} -> {$to}");
    }
};

$assertRealpathOk = static function (TestRunner $t, string $expected, string $path, string $cwd, int $maxSymlinks): void {
    $t->same(['ok' => true, 'path' => $expected], GitPath::realpathOpts($path, $cwd, $maxSymlinks));
};

$assertRealpathError = static function (TestRunner $t, array $expected, string $path, string $cwd, int $maxSymlinks): void {
    $t->same($expected, GitPath::realpathOpts($path, $cwd, $maxSymlinks));
};

return [
    'upstream gix-path path/env.rs exe_invocation' => static function (TestRunner $t): void {
        $actual = GitPath::exeInvocation();
        $t->true($actual !== '', 'it finds something as long as git is installed somewhere on the system');
        if (DIRECTORY_SEPARATOR !== '\\') {
            $t->same('git', $actual);
        }
    },

    'upstream gix-path path/env.rs shell' => static function (TestRunner $t): void {
        $shell = GitPath::shell();
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true($shell !== '');
            return;
        }

        $t->true(file_exists($shell), 'On CI and on Unix we expect a usable path to the shell that exists on disk');
    },

    'upstream gix-path path/env.rs shell_absolute' => static function (TestRunner $t): void {
        $shell = GitPath::shell();
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true($shell !== '');
            return;
        }

        $t->true(str_starts_with($shell, '/'), 'On CI and on Unix we expect the shell path to be absolute');
    },

    'upstream gix-path path/env.rs shell_unix_path' => static function (TestRunner $t): void {
        $t->same(false, str_contains(GitPath::shell(), '\\'), 'The path to the shell should have no backslashes');
    },

    'upstream gix-path path/env.rs installation_config' => static function (TestRunner $t) use ($pathComponentCount): void {
        $config = GitPath::installationConfigFromGitConfigOrigin("file:/opt/git/etc/gitconfig\0core.editor");
        $prefix = GitPath::installationConfigPrefix((string) $config);

        $t->same('/opt/git/etc/gitconfig', $config);
        $t->same('/opt/git/etc', $prefix);
        $t->true($pathComponentCount((string) $config) !== $pathComponentCount($prefix), 'the prefix is shorter than the installation config path itself');
    },

    'upstream gix-path path/env.rs core_dir' => static function (TestRunner $t) use ($tmpDir, $removeTree): void {
        $dir = $tmpDir('core-dir');
        try {
            $actual = GitPath::coreDirFromExecPathOutput($dir . "\n");
            $t->same($dir, $actual);
            $t->true(is_dir((string) $actual), 'The core directory is a valid directory');
        } finally {
            $removeTree($dir);
        }
    },

    'upstream gix-path path/env.rs system_prefix' => static function (TestRunner $t): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->same(null, GitPath::systemPrefix());
            return;
        }

        $t->same('/', GitPath::systemPrefix(), 'git should be present when running tests');
    },

    'upstream gix-path path/env.rs home_dir' => static function (TestRunner $t) use ($tmpDir, $removeTree): void {
        $home = $tmpDir('home-dir-env');
        try {
            $t->true(GitPath::homeDirFromEnvironment($home) !== null, 'we find a home on every system these tests execute');
        } finally {
            $removeTree($home);
        }
    },

    'upstream gix-path path/env.rs xdg_config::prefers_xdg_config_bases' => static function (TestRunner $t): void {
        $actual = GitPath::xdgConfig('test', static fn (string $name): ?string => $name === 'XDG_CONFIG_HOME' ? 'marker' : null);
        $t->same('marker/git/test', $actual);
    },

    'upstream gix-path path/env.rs xdg_config::falls_back_to_home' => static function (TestRunner $t): void {
        $actual = GitPath::xdgConfig('test', static fn (string $name): ?string => $name === 'HOME' ? 'marker' : null);
        $t->same('marker/.config/git/test', $actual);
    },

    'upstream gix-path path/main.rs home_dir::returns_existing_directory' => static function (TestRunner $t) use ($tmpDir, $removeTree): void {
        $home = $tmpDir('home-dir-main');
        try {
            $actual = GitPath::homeDirFromEnvironment($home);
            if ($actual !== null) {
                $t->true(is_dir($actual), 'the home directory would typically exist');
            }
        } finally {
            $removeTree($home);
        }
    },

    'upstream gix-path path/realpath.rs assorted' => static function (TestRunner $t) use ($tmpDir, $removeTree, $assertRealpathOk, $assertRealpathError): void {
        $cwd = $tmpDir('realpath-assorted');
        try {
            $symlinksDisabled = 0;
            $assertRealpathError($t, ['ok' => false, 'error' => GitPath::REALPATH_EMPTY_PATH], '', $cwd, $symlinksDisabled);
            $assertRealpathOk($t, $cwd . '/b/.git', 'b/.git', $cwd, $symlinksDisabled);
            $assertRealpathOk($t, $cwd . '/b/.git', 'b//.git', $cwd, $symlinksDisabled);
            $assertRealpathOk($t, $cwd . '/tmp/.git', './tmp/.git', $cwd, $symlinksDisabled);
            $assertRealpathOk($t, $cwd . '/tmp/a/.git', './tmp/a/./.git', $cwd, $symlinksDisabled);
            $assertRealpathOk($t, $cwd . '/tmp/.git', './b/../tmp/.git', $cwd, $symlinksDisabled);

            $absolutePath = DIRECTORY_SEPARATOR === '\\' ? 'C:/c/d/.git' : '/c/d/.git';
            $assertRealpathOk($t, $absolutePath, $absolutePath, $cwd, $symlinksDisabled);
        } finally {
            $removeTree($cwd);
        }
    },

    'upstream gix-path path/realpath.rs link_cycle_is_detected' => static function (TestRunner $t) use ($tmpDir, $removeTree, $createSymlink, $assertRealpathError): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true(true);
            return;
        }

        $dir = $tmpDir('realpath-cycle');
        try {
            $linkPath = $dir . '/link';
            $createSymlink($linkPath, $linkPath);
            $assertRealpathError(
                $t,
                ['ok' => false, 'error' => GitPath::REALPATH_MAX_SYMLINKS_EXCEEDED, 'maxSymlinks' => 8],
                $linkPath . '/.git',
                '',
                8
            );
        } finally {
            $removeTree($dir);
        }
    },

    'upstream gix-path path/realpath.rs symlink_with_absolute_path_gets_expanded' => static function (TestRunner $t) use ($tmpDir, $removeTree, $createSymlink, $assertRealpathOk): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true(true);
            return;
        }

        $dir = $tmpDir('realpath-absolute-link');
        try {
            $linkFrom = $dir . '/a/b/tmp_p_q_link';
            $linkTo = $dir . '/p/q';
            $createSymlink($linkFrom, $linkTo);
            $assertRealpathOk($t, $linkTo . '/.git', $linkFrom . '/.git', $dir, 8);
        } finally {
            $removeTree($dir);
        }
    },

    'upstream gix-path path/realpath.rs symlink_to_relative_path_gets_expanded_into_absolute_path' => static function (TestRunner $t) use ($tmpDir, $removeTree, $createSymlink, $assertRealpathOk): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true(true);
            return;
        }

        $dir = $tmpDir('realpath-relative-link');
        try {
            $linkName = 'pq_link';
            $createSymlink($dir . '/r/' . $linkName, 'p/q');
            $assertRealpathOk($t, $dir . '/r/p/q/.git', $linkName . '/.git', $dir . '/r', 8);
        } finally {
            $removeTree($dir);
        }
    },

    'upstream gix-path path/realpath.rs symlink_processing_is_disabled_if_the_value_is_zero' => static function (TestRunner $t) use ($tmpDir, $removeTree, $createSymlink, $assertRealpathError): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->true(true);
            return;
        }

        $cwd = $tmpDir('realpath-disabled-link');
        try {
            $linkName = 'x_link';
            $createSymlink($cwd . '/' . $linkName, 'link destination does not exist');
            $assertRealpathError(
                $t,
                ['ok' => false, 'error' => GitPath::REALPATH_MAX_SYMLINKS_EXCEEDED, 'maxSymlinks' => 0],
                $linkName . '/.git',
                $cwd,
                0
            );
        } finally {
            $removeTree($cwd);
        }
    },
];
