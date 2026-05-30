<?php

declare(strict_types=1);

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
        $sourceRoot . '/SQLiteEncodingCollationSourceCursor.php',
        $sourceRoot . '/SQLiteEncodingLikeGlobSourceSwitchPlan.php',
        $sourceRoot . '/SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteInsertDefaultValuesSql.php',
        $sourceRoot . '/SQLiteLikeGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteMalformedLikeGlobSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseGlobAffinityCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteNocaseLikeRtrimCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteRtrimNocaseGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteTriggerForeignKeyDynamicPlan.php',
        $sourceRoot . '/SQLiteUpsertReturningDynamicCorpusPlan.php',
        $sourceRoot . '/SQLiteUtf16CastGlobCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16LikeGlobAffinityCurrentSourceCursor.php',
        $sourceRoot . '/SQLiteUtf16LikeGlobCurrentNextCursor.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeCurrentSourceNextPlan.php',
        $sourceRoot . '/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan.php',
    ];
};

$legacyEncodingDefaultSourceMatches = static function () use ($encodingSourceFiles, $libsqliteRoot): array {
    $matches = [];
    $legacyTerms = [
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
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

return [
    'encoding source defaults use generic application setting sources' => static fn (TestRunner $t) => $t->same([], $legacyEncodingDefaultSourceMatches()),
];
