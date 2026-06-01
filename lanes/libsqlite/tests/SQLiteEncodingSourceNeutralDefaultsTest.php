<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$encodingSourceFiles = static function () use ($sourceRoot): array {
    return [
        $sourceRoot . '/SQLiteCastCollationLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteCastLikeGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteCastNocaseCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteCastRtrimGlobRangeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteCastRtrimLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteDynamicTriggerForeignKeyPlan.php',
        $sourceRoot . '/SQLiteEncodingAffinityLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingNumericAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingCollationSourceCursor.php',
        $sourceRoot . '/SQLiteEncodingLikeGlobSourceSwitchPlan.php',
        $sourceRoot . '/SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteInsertDefaultValuesSql.php',
        $sourceRoot . '/SQLiteLikeGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteMalformedLikeGlobSourceNextPlan.php',
        $sourceRoot . '/SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseLikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseRtrimLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteRtrimNocaseGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteTriggerForeignKeyDynamicPlan.php',
        $sourceRoot . '/SQLiteUpsertReturningDynamicCorpusPlan.php',
        $sourceRoot . '/SQLiteUtf16CastGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16GlobRangeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16LikeEscapeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16LikeGlobAffinityCurrentSourceCursor.php',
        $sourceRoot . '/SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16LikeGlobCurrentNextCursor.php',
        $sourceRoot . '/SQLiteUtf16LikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16RtrimNocaseCurrentSourceNextPlan.php',
    ];
};

$encodingFixtureFiles = static function () use ($libsqliteRoot): array {
    return [
        $libsqliteRoot . '/tests/SQLiteEncodingCollationSourceCursorNext82Test.php',
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
    'encoding source defaults use generic application setting sources' => static fn (TestRunner $t) => $t->same([], $legacyEncodingDefaultSourceMatches()),
    'encoding cursor direct fixtures use generic application setting keys' => static fn (TestRunner $t) => $t->same([], $legacyEncodingFixtureMatches()),
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
