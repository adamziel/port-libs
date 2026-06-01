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
$keyValueSourceFiles = [
    $sourceRoot . '/SQLiteDatabase.php',
    $sourceRoot . '/SQLiteKeyValueRow.php',
    $sourceRoot . '/SQLiteKeyValueRowInsertOrReplacePlan.php',
    $sourceRoot . '/SQLiteKeyValueRowReplacementPlan.php',
    $sourceRoot . '/SQLiteKeyValueRowsWalImportPlan.php',
    $sourceRoot . '/SQLiteKeyValueRowWritePlan.php',
    $sourceRoot . '/SQLiteTenantKeyValueWalPlan.php',
];
$forbiddenNamePattern = 'WordPress|wordpress|WP|Wp|wp_|wpError|wp_error|OptionRow|optionRow|optionImport|optionsWalRows|upsertRecursiveViewSourceOption|Multisite|Network|Autoload|autoload|BlogId|blogId|OptionsTable|optionsTable|(?<!Compile)OptionName|(?<!compile)optionName|(?<!Compile)OptionValue|(?<!compile)optionValue|(?<!Compile)OptionId|(?<!compile)optionId';

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

$filenameMatches = static function () use ($libsqlitePhpFiles, $relativePath, $forbiddenNamePattern): array {
    $matches = [];

    foreach ($libsqlitePhpFiles as $file) {
        $relative = $relativePath($file);
        if (preg_match('/' . $forbiddenNamePattern . '/', $relative) === 1) {
            $matches[] = $relative;
        }
    }

    return $matches;
};

$domainSpecificDeclarationMatches = static function () use ($libsqlitePhpFiles, $relativePath, $forbiddenNamePattern): array {
    $matches = [];
    $pattern = '/^\s*(?:(?:final|abstract|readonly)\s+)?(?:class|interface|trait|enum)\s+\w*(?:' . $forbiddenNamePattern . ')\w*|^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+\w*(?:' . $forbiddenNamePattern . ')\w*\s*\(/m';

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

$keyValueSourceTermMatches = static function () use ($keyValueSourceFiles, $relativePath): array {
    $matches = [];
    $pattern = '/wp_|wp_options|wp_sitemeta|blog_id|blogId|BlogId|option_id|option_name|option_value|OptionRow|optionRow|optionName|optionValue|optionId|Autoload|autoload|\$option\b/';

    foreach ($keyValueSourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all($pattern, $contents, $fileMatches) > 0) {
            foreach ($fileMatches[0] as $match) {
                $matches[] = $relativePath($file) . ': ' . $match;
            }
        }
    }

    return $matches;
};

return [
    'libsqlite source has no WordPress-named text' => static fn (TestRunner $t) => $t->same([], $sourceTextMatches()),
    'libsqlite filenames have no WordPress-specific names' => static fn (TestRunner $t) => $t->same([], $filenameMatches()),
    'libsqlite php declarations have no WordPress-specific class or method names' => static fn (TestRunner $t) => $t->same([], $domainSpecificDeclarationMatches()),
    'libsqlite key-value source API uses neutral setting names' => static fn (TestRunner $t) => $t->same([], $keyValueSourceTermMatches()),
];
