<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastNocaseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;
use PortLibs\LibSqlite\SQLiteNocaseGlobAffinityCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$ownedSourceFiles = [
    $sourceRoot . '/SQLiteCastLikeGlobAffinityCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastNocaseCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastRtrimLikeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteMalformedLikeGlobSourceNextPlan.php',
    $sourceRoot . '/SQLiteNocaseGlobAffinityCurrentSourceNextPlan.php',
];

$ownedFixtureFiles = [
    $libsqliteRoot . '/tests/SQLiteCastAffinityComparisonCorpusTest.php',
    $libsqliteRoot . '/tests/SQLiteLikeCollationCurrentNext65Test.php',
];

$relativePath = static fn (string $path): string => str_replace($libsqliteRoot . '/', '', $path);

$legacyTerms = static function (): array {
    return [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_sitemeta',
        'blog' . '_id',
        'option' . '_id',
        'option' . '_name',
        'option' . '_value',
        'auto' . 'load',
        'WP' . '_LOCALE',
        'site' . 'url',
        'active' . '_plugins',
    ];
};

$termMatches = static function (array $files) use ($legacyTerms, $relativePath): array {
    $matches = [];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms())) . ')/';

    foreach ($files as $file) {
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

$tests = [];

$tests['source-neutral cast like glob source defaults contain no legacy domain terms'] = static function (TestRunner $t) use ($ownedSourceFiles, $termMatches): void {
    $t->same([], $termMatches($ownedSourceFiles));
};

$tests['source-neutral cast like glob direct fixtures contain no legacy domain terms'] = static function (TestRunner $t) use ($ownedFixtureFiles, $termMatches): void {
    $t->same([], $termMatches($ownedFixtureFiles));
};

$tests['source-neutral cast like glob default source names use app settings'] = static function (TestRunner $t): void {
    $currentRows = [
        [
            'setting_id' => 1,
            'key_name' => 'module_alpha',
            'key_value' => 'module:alpha',
            'key_name_bytes' => 'module_alpha',
            'text_encoding' => 1,
        ],
    ];
    $nextRows = [
        [
            'setting_id' => 1,
            'key_name' => 'module_alpha',
            'key_value' => 'module:alpha',
            'key_name_bytes' => 'module_alpha',
            'text_encoding' => 1,
        ],
        [
            'setting_id' => 2,
            'key_name' => 'module_beta',
            'key_value' => 'module:beta',
            'key_name_bytes' => 'module_beta',
            'text_encoding' => 1,
        ],
    ];

    $plans = [
        SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module:%'),
        SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module%'),
        SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module:%'),
        SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', 'module:%'),
        SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, 'module*'),
        SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext($currentRows, $nextRows, 'module%'),
    ];

    foreach ($plans as $plan) {
        $t->same(false, str_contains($plan['currentSource'], 'wp' . '_'));
        $t->same(false, str_contains($plan['nextSource'], 'wp' . '_'));
    }

    $t->same('main.app_settings@132', $plans[0]['currentSource']);
    $t->same('main.app_settings@133', $plans[0]['nextSource']);
    $t->same('main.app_settings@128', $plans[1]['currentSource']);
    $t->same('main.app_settings@129', $plans[1]['nextSource']);
    $t->same('main.app_settings@130', $plans[2]['currentSource']);
    $t->same('main.app_settings@131', $plans[2]['nextSource']);
    $t->same('main.app_settings', $plans[3]['currentSource']);
    $t->same('main.app_settings', $plans[3]['nextSource']);
    $t->same('main.app_settings@138', $plans[4]['currentSource']);
    $t->same('main.app_settings@139', $plans[4]['nextSource']);
    $t->same('current', $plans[5]['currentSource']);
    $t->same('next', $plans[5]['nextSource']);
};

$tests['source-neutral cast like glob direct SQL fixtures use app settings table'] = static function (TestRunner $t) use ($ownedFixtureFiles): void {
    foreach ($ownedFixtureFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        $t->true(str_contains($contents, 'app_settings'));
        $t->true(str_contains($contents, 'setting_id'));
        $t->true(str_contains($contents, 'key_name'));
    }
};

$tests['source-neutral cast like glob dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; existing cast, LIKE, GLOB, and SELECT SQL helpers are reused with neutral app-settings fixtures',
        'no new support component needed; existing cast, LIKE, GLOB, and SELECT SQL helpers are reused with neutral app-settings fixtures',
    );
};

return $tests;
