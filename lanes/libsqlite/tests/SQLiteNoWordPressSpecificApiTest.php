<?php

declare(strict_types=1);

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$phpFiles = static function (string $root): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $files[] = $fileInfo->getPathname();
    }

    sort($files);

    return $files;
};

$relativePath = static fn (string $path): string => str_replace($libsqliteRoot . '/', '', $path);

$sourceFiles = $phpFiles($sourceRoot);
$libsqlitePhpFiles = $phpFiles($libsqliteRoot);

$sourceTextMatches = static function () use ($sourceFiles, $relativePath): array {
    $matches = [];

    foreach ($sourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match('/WordPress|wordpress/', $contents) === 1) {
            $matches[] = $relativePath($file);
        }
    }

    return $matches;
};

$sourceFilenameMatches = static function () use ($sourceFiles, $relativePath): array {
    $matches = [];

    foreach ($sourceFiles as $file) {
        $relative = $relativePath($file);
        if (preg_match('/WordPress|wordpress|WP|Wp|wp_|OptionRow|Multisite|Network/', $relative) === 1) {
            $matches[] = $relative;
        }
    }

    return $matches;
};

$wordpressDeclarationMatches = static function () use ($libsqlitePhpFiles, $relativePath): array {
    $matches = [];
    $specificName = 'WordPress|wordpress|WP|Wp|wp_|OptionRow|Multisite|Network|Autoload';
    $pattern = '/^\s*(?:(?:final|abstract)\s+)?(?:class|interface|trait)\s+\w*(?:' . $specificName . ')\w*|^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+\w*(?:' . $specificName . ')\w*\s*\(/m';

    foreach ($libsqlitePhpFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all($pattern, $contents, $fileMatches) > 0) {
            foreach ($fileMatches[0] as $match) {
                $matches[] = $relativePath($file) . ': ' . trim($match);
            }
        }
    }

    return $matches;
};

return [
    'libsqlite source has no WordPress-named text' => static fn (TestRunner $t) => $t->same([], $sourceTextMatches()),
    'libsqlite source filenames have no WordPress-specific names' => static fn (TestRunner $t) => $t->same([], $sourceFilenameMatches()),
    'libsqlite php declarations have no WordPress-specific class or method names' => static fn (TestRunner $t) => $t->same([], $wordpressDeclarationMatches()),
];
