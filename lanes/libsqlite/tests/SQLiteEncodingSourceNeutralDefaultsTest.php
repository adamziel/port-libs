<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$encodingSourceFiles = static function () use ($sourceRoot): array {
    $files = [
        $sourceRoot . '/SQLiteDynamicTriggerForeignKeyPlan.php',
        $sourceRoot . '/SQLiteInsertDefaultValuesSql.php',
        $sourceRoot . '/SQLiteTriggerForeignKeyDynamicPlan.php',
        $sourceRoot . '/SQLiteUpsertReturningDynamicCorpusPlan.php',
    ];

    foreach (glob($sourceRoot . '/SQLite*.php') ?: [] as $file) {
        $base = basename($file);
        if (preg_match('/(?:Affinity|Blob|Cast|Collation|Encoding|Glob|Like|NoCase|Nocase|Rtrim|Utf16)/', $base) === 1) {
            $files[] = $file;
        }
    }

    sort($files, SORT_STRING);

    return array_values(array_unique($files));
};

$encodingFixtureFiles = static function () use ($libsqliteRoot): array {
    return [
        $libsqliteRoot . '/tests/SQLiteEncodingCollationSourceCursorNext82Test.php',
        $libsqliteRoot . '/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php',
        $libsqliteRoot . '/tests/SQLiteEncodingCollationAffinityGlobCurrentSourceNext239Test.php',
        $libsqliteRoot . '/tests/SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNext124Test.php',
        $libsqliteRoot . '/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php',
    ];
};

$legacyEncodingDefaultSourceMatches = static function () use ($encodingSourceFiles, $libsqliteRoot): array {
    $matches = [];
    $legacyTerms = [
        'wp' . '_options',
        'wp' . '_option',
        'wp' . '_',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_name_bytes',
        'opt' . 'ion_value',
        'opt' . 'ion_value_bytes',
        'opt' . 'ionName',
        'opt' . 'ionValue',
        'opt' . 'ionId',
        'Opt' . 'ionName',
        'Opt' . 'ionValue',
        'Opt' . 'ionId',
        'Opt' . 'ionRow',
        'opt' . 'ionRow',
        'opt' . 'ionRowName',
        'opt' . 'ionRowValue',
        'auto' . 'load',
        'blog' . '_id',
        'plug' . 'in',
        'Plug' . 'in',
        'PLUG' . 'IN',
        'theme',
        'Theme',
        'THEME',
        'site' . 'url',
        'blog' . '_public',
        'active' . '_plugins',
    ];
    $legacyPattern = '/\b(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms)) . ')\b/';

    foreach ($encodingSourceFiles() as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all($legacyPattern, $contents, $fileMatches) > 0) {
            $relative = str_replace($libsqliteRoot . '/', '', $file);
            foreach ($fileMatches[0] as $match) {
                $matches[] = "{$relative}: {$match}";
            }
        }
    }

    return $matches;
};

$legacyEncodingFixtureMatches = static function () use ($encodingFixtureFiles, $libsqliteRoot): array {
    $matches = [];
    $legacyTerms = [
        'wp' . '_options',
        'wp' . '_option',
        'wp' . '_',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_name_bytes',
        'opt' . 'ion_value',
        'opt' . 'ion_value_bytes',
        'auto' . 'load',
        'blog' . '_id',
        'WP' . '_LOCALE',
        'plug' . 'in',
        'Plug' . 'in',
        'PLUG' . 'IN',
        'theme',
        'Theme',
        'THEME',
        'site' . 'url',
        'blog' . '_public',
        'active' . '_plugins',
    ];
    $legacyPattern = '/\b(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms)) . ')\b/';

    foreach ($encodingFixtureFiles() as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all($legacyPattern, $contents, $fileMatches) > 0) {
            $relative = str_replace($libsqliteRoot . '/', '', $file);
            foreach ($fileMatches[0] as $match) {
                $matches[] = "{$relative}: {$match}";
            }
        }
    }

    return $matches;
};

return [
    'encoding source neutral scan covers dynamic production inventory' => static function (TestRunner $t) use ($encodingSourceFiles, $libsqliteRoot): void {
        $relativeFiles = array_map(
            static fn (string $file): string => str_replace($libsqliteRoot . '/', '', $file),
            $encodingSourceFiles()
        );

        $t->true(count($relativeFiles) >= 60, 'Expected the encoding source-neutral scan to cover the current source inventory');
        $t->true(in_array('src/SQLiteAffinityComparison.php', $relativeFiles, true));
        $t->true(in_array('src/SQLiteGlobCursor.php', $relativeFiles, true));
        $t->true(in_array('src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php', $relativeFiles, true));
    },
    'encoding source defaults use generic application setting sources' => static fn (TestRunner $t) => $t->same([], $legacyEncodingDefaultSourceMatches()),
    'encoding cursor direct fixtures use generic application setting keys' => static fn (TestRunner $t) => $t->same([], $legacyEncodingFixtureMatches()),
    'encoding default source helpers use generic application settings sources' => static function (TestRunner $t): void {
        $encode = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
        $currentRows = [
            ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => 'module_alpha', 'key_name_bytes' => $encode('module_alpha', 'UTF-8'), 'text_encoding' => 1],
        ];
        $nextRows = [
            ['setting_id' => 1, 'key_name' => 'module_alpha', 'key_value' => 'module_alpha', 'key_name_bytes' => $encode('module_alpha', 'UTF-8'), 'text_encoding' => 1],
            ['setting_id' => 2, 'key_name' => 'module_beta', 'key_value' => 'module_beta', 'key_name_bytes' => $encode('module_beta', 'UTF-16LE'), 'text_encoding' => 2],
        ];

        $plans = [
            SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'module%', 'LIKE'),
            SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan::keyValueRowValueMalformedGlobPlan($currentRows, $nextRows, 'module*'),
            SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $encode('module%', 'UTF-16LE'), 'UTF-16LE'),
            SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::keyValueRowKeyPatternPlan($currentRows, $nextRows, 'module%', 1, 'module%', 1),
        ];

        foreach ($plans as $plan) {
            $t->true(str_starts_with($plan['currentSource'], 'main.app_settings'));
            $t->true(str_starts_with($plan['nextSource'], 'main.app_settings'));
            $t->same(false, str_contains($plan['currentSource'], 'wp' . '_'));
            $t->same(false, str_contains($plan['nextSource'], 'wp' . '_'));
        }
    },
    'encoding collation affinity LIKE default patterns are source neutral' => static function (TestRunner $t): void {
        $encode = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
        $code = static fn (int|string $encoding): int => match ($encoding) {
            'UTF-8', 1 => 1,
            'UTF-16LE', 2 => 2,
            'UTF-16BE', 3 => 3,
        };
        $encodedRow = static fn (int $id, string $name, int|string $encoding): array => [
            'setting_id' => $id,
            'key_name_bytes' => $encode($name, $encoding),
            'text_encoding' => $code($encoding),
        ];

        $keyValueRows = [
            ['setting_id' => 1, 'key_name' => 'module_cache', 'key_value' => 'module_%literal', 'text_encoding' => 'UTF-8'],
            ['setting_id' => 2, 'key_name' => 'module_literal', 'key_value' => "module\0cache_suffix", 'text_encoding' => 'UTF-8'],
            ['setting_id' => 3, 'key_name' => 'MODULE_cache', 'key_value' => 'MODULE_cache', 'text_encoding' => 'UTF-8'],
        ];
        $encodedRows = [
            $encodedRow(1, 'module_cache%enabled', 'UTF-8'),
            $encodedRow(2, 'MODULE_CACHE%NEW', 'UTF-16BE'),
        ];
        $rtrimRows = [
            $encodedRow(1, 'module_cache', 'UTF-8'),
            $encodedRow(2, 'module_cache ', 'UTF-16LE'),
        ];

        $plans = [
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowValueEscapePlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationEmbeddedNulLikePlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationDanglingEscapeLikePlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNonAsciiEscapeLikePlan($encodedRows, $encodedRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeSourcePlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationBinaryCollationDefaultLikePlan($encodedRows, $encodedRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationNullableEscapeLikePlan($keyValueRows, $keyValueRows),
            SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan($rtrimRows, $rtrimRows),
        ];

        $t->same('module!_%!%%', $plans[0]['pattern']);
        $t->same("module\0cache!_%", $plans[1]['pattern']);
        $t->same('module!_cache!', $plans[2]['pattern']);
        $t->same('moduleé_cacheé%%', $plans[3]['pattern']);
        $t->same('module!_cache', $plans[4]['pattern']);
        $t->same('MODULE!_%', $plans[5]['pattern']);
        $t->same('Module%', $plans[6]['pattern']);
        $t->same('module!_%', $plans[7]['pattern']);
        $t->same('module_cache', $plans[8]['pattern']);

        foreach ($plans as $plan) {
            $t->same(false, str_contains($plan['pattern'], 'plug' . 'in'));
            $t->same(false, str_contains($plan['pattern'], 'Plug' . 'in'));
            $t->same(false, str_contains($plan['pattern'], 'PLUG' . 'IN'));
            $t->same(false, str_contains($plan['currentSource'], 'wp' . '_'));
            $t->same(false, str_contains($plan['nextSource'], 'wp' . '_'));
        }
    },
];
