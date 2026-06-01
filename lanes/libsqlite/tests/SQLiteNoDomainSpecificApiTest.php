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
    $sourceRoot . '/SQLiteCurrentSmokePlan.php',
    $sourceRoot . '/SQLiteImportTransactionErrorYieldPlan.php',
    $sourceRoot . '/SQLiteJsonUpsertMigrationPlan.php',
    $sourceRoot . '/SQLiteKeyValueRow.php',
    $sourceRoot . '/SQLiteKeyValueRowInsertOrReplacePlan.php',
    $sourceRoot . '/SQLiteKeyValueRowReplacementPlan.php',
    $sourceRoot . '/SQLiteKeyValueRowsWalImportPlan.php',
    $sourceRoot . '/SQLiteKeyValueRowWritePlan.php',
    $sourceRoot . '/SQLiteMalformedTextCurrentNextCursor.php',
    $sourceRoot . '/SQLiteTenantImportSavepointPlan.php',
    $sourceRoot . '/SQLiteTenantKeyValueWalPlan.php',
    $sourceRoot . '/SQLiteUtf16CollationAffinityCursor.php',
    $sourceRoot . '/SQLiteUtf16CollationAffinitySourceSwitchPlan.php',
];
$jsonbCurrentSourceFiles = [
    $sourceRoot . '/SQLiteGeneratedJsonPathIndexPlan.php',
    $sourceRoot . '/SQLiteJsonB.php',
    $sourceRoot . '/SQLiteJsonbCheckCurrentNextPlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedCascadePlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedCheckIndexPlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteJsonbGeneratedPartialUpsertPlan.php',
    $sourceRoot . '/SQLiteJsonbPatchGeneratedIndexPlan.php',
    $sourceRoot . '/SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteJsonTablePlan.php',
];
$keyValueFixtureFiles = [
    $libsqliteRoot . '/examples/application-current-smoke-key-value-import.php',
    $libsqliteRoot . '/examples/application-composite-indexed-generated-setting-insert-plan.php',
    $libsqliteRoot . '/examples/application-index-split-setting-replacement-plan.php',
    $libsqliteRoot . '/examples/application-json-upsert-migration-current-next27.php',
    $libsqliteRoot . '/examples/application-json-path-validation-preflight.php',
    $libsqliteRoot . '/examples/application-malformed-text-current-next70.php',
    $libsqliteRoot . '/examples/application-savepoint-key-value-import-diagnostics.php',
    $libsqliteRoot . '/examples/application-settings-import-wal-current-next.php',
    $libsqliteRoot . '/examples/application-tenant-keyvalue-import-savepoint-current-next37.php',
    $libsqliteRoot . '/examples/application-utf16-affinity-source-switch-current-source-next100.php',
    $libsqliteRoot . '/examples/application-utf16-collation-affinity-current-source-next85.php',
    $libsqliteRoot . '/tests/SQLiteApplicationCurrentSmokePlanTest.php',
    $libsqliteRoot . '/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php',
    $libsqliteRoot . '/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php',
    $libsqliteRoot . '/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php',
    $libsqliteRoot . '/tests/SQLiteMalformedTextCurrentNext70Test.php',
    $libsqliteRoot . '/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php',
    $libsqliteRoot . '/tests/SQLiteUtf16CollationAffinityCurrentSourceNext85Test.php',
    $libsqliteRoot . '/tests/SQLiteUtf16CollationAffinitySourceSwitchCurrentSourceNext100Test.php',
];
$forbiddenNamePattern = 'WordPress|wordpress|wordPress|WP|Wp|wp_|wpError|wp_error|OptionRow|optionRow|optionImport|optionsWalRows|upsertRecursiveViewSourceOption|Multisite|Network|Autoload|autoload|BlogId|blogId|OptionsTable|optionsTable|(?<!Compile)OptionName|(?<!compile)optionName|(?<!Compile)OptionValue|(?<!compile)optionValue|(?<!Compile)OptionId|(?<!compile)optionId';

$sourceTextMatches = static function () use ($sourceFiles, $relativePath): array {
    $matches = [];

    foreach ($sourceFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match('/WordPress|wordpress|wordPress/', $contents) === 1) {
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
    $pattern = '/WordPress|wordpress|wordPress|wp_|wp_options|wp_sitemeta|blog_id|blogId|BlogId|option_id|option_name|option_value|OptionRow|optionRow|optionName|optionValue|optionId|Autoload|autoload|continue_on_site_error|\$option\b|\$sites\b|\$site\b|sitePlans|sitePlan/';

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

$jsonbCurrentSourceTermMatches = static function () use ($jsonbCurrentSourceFiles, $relativePath): array {
    $matches = [];
    $pattern = '/WordPress|wordpress|wordPress|wp_|wp_options|wp_sitemeta|blog_id|blogId|BlogId|option_id|option_name|option_value|OptionRow|optionRow|optionName|optionValue|optionId|Autoload|autoload/';

    foreach ($jsonbCurrentSourceFiles as $file) {
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

$keyValueFixtureTermMatches = static function () use ($keyValueFixtureFiles, $relativePath): array {
    $matches = [];
    $pattern = '/wp_|wp_options|wp_sitemeta|blog_id|blogId|BlogId|option_id|option_name|option_value|OptionRow|optionRow|optionName|optionValue|optionId|Autoload|autoload|continue_on_site_error|siteurl|blogname|blogdescription|blog_public|site_name|site_admins|network_meta|rewrite_rules|stylesheet|active_plugins|active_sitewide_plugins|plugin|Plugin|\bhome\b/';

    foreach ($keyValueFixtureFiles as $file) {
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

$keyValueFixtureFilenameMatches = static function () use ($keyValueFixtureFiles, $relativePath): array {
    $matches = [];
    $pattern = '/wp_|wp_options|wp_sitemeta|blog_id|blogId|BlogId|option_id|option_name|option_value|option|OptionRow|optionRow|optionName|optionValue|optionId|Autoload|autoload/';

    foreach ($keyValueFixtureFiles as $file) {
        $relative = $relativePath($file);
        if (preg_match_all($pattern, basename($relative), $fileMatches) > 0) {
            foreach ($fileMatches[0] as $match) {
                $matches[] = $relative . ': ' . $match;
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
    'libsqlite jsonb current-source API uses neutral setting names' => static fn (TestRunner $t) => $t->same([], $jsonbCurrentSourceTermMatches()),
    'libsqlite key-value test and example filenames use neutral setting names' => static fn (TestRunner $t) => $t->same([], $keyValueFixtureFilenameMatches()),
    'libsqlite key-value tests and examples use neutral fixtures' => static fn (TestRunner $t) => $t->same([], $keyValueFixtureTermMatches()),
];
