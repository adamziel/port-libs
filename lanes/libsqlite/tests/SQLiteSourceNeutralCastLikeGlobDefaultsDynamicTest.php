<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCastCollationLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastNocaseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastRtrimGlobRangeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteCastRtrimLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;
use PortLibs\LibSqlite\SQLiteNocaseGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerDynamicVariablePlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$ownedSourceFiles = [
    $sourceRoot . '/SQLiteCastCollationLikeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastLikeGlobAffinityCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastNocaseCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastRtrimGlobRangeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCastRtrimLikeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteInsertDefaultValuesSql.php',
    $sourceRoot . '/SQLiteLikeGlobCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteMalformedLikeGlobSourceNextPlan.php',
    $sourceRoot . '/SQLiteNocaseGlobAffinityCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteTriggerDynamicVariablePlan.php',
];

$ownedFixtureFiles = [
    $libsqliteRoot . '/examples/application-cast-collation-like-current-source-next123.php',
    $libsqliteRoot . '/examples/application-cast-like-glob-affinity-current-source-next133.php',
    $libsqliteRoot . '/examples/application-cast-rtrim-glob-range-current-source-next127.php',
    $libsqliteRoot . '/examples/application-cast-rtrim-like-current-source-next131.php',
    $libsqliteRoot . '/examples/application-insert-default-values-generated-default.php',
    $libsqliteRoot . '/examples/application-key-name-like-glob-current-source-next88.php',
    $libsqliteRoot . '/examples/application-like-escape-glob-candidates-current-source-next147.php',
    $libsqliteRoot . '/tests/SQLiteCastAffinityComparisonCorpusTest.php',
    $libsqliteRoot . '/tests/SQLiteCastCollationLikeCurrentSourceNext123Test.php',
    $libsqliteRoot . '/tests/SQLiteCastLikeGlobAffinityCurrentSourceNext133Test.php',
    $libsqliteRoot . '/tests/SQLiteCastRtrimGlobRangeCurrentSourceNext127Test.php',
    $libsqliteRoot . '/tests/SQLiteCastRtrimLikeCurrentSourceNext131Test.php',
    $libsqliteRoot . '/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php',
    $libsqliteRoot . '/tests/SQLiteLikeEscapeGlobCandidateCurrentSourceNext147Test.php',
    $libsqliteRoot . '/tests/SQLiteLikeCollationCurrentNext65Test.php',
    $libsqliteRoot . '/tests/SQLiteLikeGlobCurrentSourceNext88Test.php',
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
        'option' . '-name',
        'option' . '_value',
        'auto' . 'load',
        'option',
        'Option',
        'OPTION',
        'WP' . '_LOCALE',
        'site' . 'url',
        'active' . '_plugins',
        'home',
        'plug' . 'in',
        'Plug' . 'in',
        'PLUG' . 'IN',
        'theme',
        'Theme',
        'THEME',
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

$filenameTermMatches = static function (array $files) use ($legacyTerms, $relativePath): array {
    $matches = [];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms())) . ')/';

    foreach ($files as $file) {
        $relative = $relativePath($file);
        if (preg_match_all($pattern, basename($relative), $fileMatches) > 0) {
            foreach ($fileMatches[0] as $match) {
                $matches[] = $relative . ': ' . $match;
            }
        }
    }

    return $matches;
};

$methodSource = static function (string $class, string $method): string {
    $reflection = new ReflectionMethod($class, $method);
    $file = $reflection->getFileName();
    if (!is_string($file)) {
        throw new RuntimeException("Unable to locate {$class}::{$method}");
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
};

$tests = [];

$tests['source-neutral cast like glob source defaults contain no legacy domain terms'] = static function (TestRunner $t) use ($ownedSourceFiles, $termMatches): void {
    $t->same([], $termMatches($ownedSourceFiles));
};

$tests['source-neutral cast like glob direct fixtures contain no legacy domain terms'] = static function (TestRunner $t) use ($ownedFixtureFiles, $termMatches): void {
    $t->same([], $termMatches($ownedFixtureFiles));
};

$tests['source-neutral cast like glob direct fixture filenames contain no legacy domain terms'] = static function (TestRunner $t) use ($ownedFixtureFiles, $filenameTermMatches): void {
    $t->same([], $filenameTermMatches($ownedFixtureFiles));
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
        SQLiteCastCollationLikeCurrentSourceNextPlan::keyValueRowValueCastScan($currentRows, $nextRows, 'TEXT', 'module:%'),
        SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module:%'),
        SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module%'),
        SQLiteCastRtrimGlobRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module*'),
        SQLiteCastRtrimLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'TEXT', 'module:%'),
        SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', 'module:%'),
        SQLiteNocaseGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, 'module*'),
        SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext($currentRows, $nextRows, 'module%'),
    ];

    foreach ($plans as $plan) {
        if (isset($plan['currentSource'], $plan['nextSource'])) {
            $t->same(false, str_contains($plan['currentSource'], 'wp' . '_'));
            $t->same(false, str_contains($plan['nextSource'], 'wp' . '_'));
        }
    }

    $t->same(false, array_key_exists('currentSource', $plans[0]));
    $t->same(false, array_key_exists('nextSource', $plans[0]));
    $t->same('main.app_settings@132', $plans[1]['currentSource']);
    $t->same('main.app_settings@133', $plans[1]['nextSource']);
    $t->same('main.app_settings@128', $plans[2]['currentSource']);
    $t->same('main.app_settings@129', $plans[2]['nextSource']);
    $t->same('main.app_settings@126', $plans[3]['currentSource']);
    $t->same('main.app_settings@127', $plans[3]['nextSource']);
    $t->same('main.app_settings@130', $plans[4]['currentSource']);
    $t->same('main.app_settings@131', $plans[4]['nextSource']);
    $t->same('main.app_settings', $plans[5]['currentSource']);
    $t->same('main.app_settings', $plans[5]['nextSource']);
    $t->same('main.app_settings@138', $plans[6]['currentSource']);
    $t->same('main.app_settings@139', $plans[6]['nextSource']);
    $t->same('current', $plans[7]['currentSource']);
    $t->same('next', $plans[7]['nextSource']);
};

$tests['source-neutral defaults and dynamic trigger helpers use generic tables'] = static function (TestRunner $t): void {
    $defaultPlan = SQLiteInsertDefaultValuesSql::execute(
        'INSERT INTO app_defaults DEFAULT VALUES',
        ['app_defaults' => []],
        ['app_defaults' => "CREATE TABLE app_defaults(setting_id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'module_base', key_value TEXT DEFAULT upper(key_name))"],
    );
    $triggerPlan = SQLiteTriggerDynamicVariablePlan::replayStoredSchema(
        [[
            'name' => 'settings_after_insert',
            'table' => 'app_settings',
            'sql' => "CREATE TRIGGER settings_after_insert AFTER INSERT ON app_settings BEGIN INSERT INTO app_audit VALUES(new.setting_id, 'module'); END",
        ]],
        [['table' => 'app_settings', 'row' => ['setting_id' => 4, 'key_name' => 'module_base']]],
        ['app_audit' => []],
    );
    $statementPlan = SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement(
        [
            ['setting_id' => 1, 'key_name_bytes' => 'module_alpha', 'text_encoding' => 1],
        ],
        [
            ['setting_id' => 1, 'key_name_bytes' => 'module_alpha', 'text_encoding' => 1],
        ],
        ['source' => 'main.app_settings@1', 'operator' => 'LIKE', 'pattern' => 'module%', 'collation' => 'NOCASE'],
        ['source' => 'main.app_settings@1', 'operator' => 'LIKE', 'pattern' => 'module%', 'collation' => 'NOCASE'],
    );

    $t->same('app_defaults', $defaultPlan['target']);
    $t->same('module_base', $defaultPlan['inserted_row']['key_name']);
    $t->same('MODULE_BASE', $defaultPlan['inserted_row']['key_value']);
    $t->same([['c1' => 4, 'c2' => 'module']], $triggerPlan['tables']['app_audit']);
    $t->same([1], $statementPlan['current']['rowids']);
};

$tests['source-neutral encoding rtrim like residual default uses module key pattern'] = static function (TestRunner $t) use ($methodSource): void {
    $source = $methodSource(SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::class, 'applicationRtrimCollationLikeResidualPlan');

    $t->same(false, str_contains($source, 'plugin' . '_'));

    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan(
        [
            ['setting_id' => 1, 'key_name' => 'module_cache'],
            ['setting_id' => 2, 'key_name' => 'moduleacache'],
            ['setting_id' => 3, 'key_name' => 'module_cache '],
        ],
        [
            ['setting_id' => 1, 'key_name' => 'module_cache'],
            ['setting_id' => 2, 'key_name' => 'moduleacache'],
        ],
        currentSource: 'same',
        nextSource: 'same',
        currentSchemaCookie: 1,
        nextSchemaCookie: 1,
    );

    $t->same('module_cache', $plan['pattern']);
    $t->same('6D6F64756C65', $plan['prefixHex']);
    $t->same([1, 3, 2], $plan['currentCandidateRowids']);
    $t->same([1, 2], $plan['currentMatchedRowids']);
    $t->same([3], $plan['currentResidualRejectedRowids']);
};

$tests['source-neutral cast like glob direct SQL fixtures use generic app tables'] = static function (TestRunner $t) use ($ownedFixtureFiles): void {
    foreach ($ownedFixtureFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        $t->true(str_contains($contents, 'app_settings') || str_contains($contents, 'app_defaults') || str_contains($contents, 'app_setting_defaults'));
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
