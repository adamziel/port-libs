<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowRowValueUpsertCurrentSourcePlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';
$sourceFiles = [
    $sourceRoot . '/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan.php',
    $sourceRoot . '/SQLiteCompoundSelectRecursiveAffinityLimitPlan.php',
    $sourceRoot . '/SQLiteWindowRowValueUpsertCurrentSourcePlan.php',
];

$compoundWindowSourceMatches = static function () use ($sourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'opt' . 'ion_walk',
        'auto' . 'load',
        'Auto' . 'load',
        'application-' . 'option',
        'Application ' . 'option',
    ];
    $pattern = '/(?:\bwp\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($sourceFiles as $sourceFile) {
        $contents = file_get_contents($sourceFile);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$sourceFile}");
        }

        if (preg_match_all($pattern, $contents, $fileMatches, PREG_OFFSET_CAPTURE) < 1) {
            continue;
        }

        $relative = str_replace($libsqliteRoot . '/', '', $sourceFile);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match[0]}";
        }
    }

    return $matches;
};

$windowRowValueDefaults = static fn (): array => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute(
    [
        ['key_name' => 'base_url', 'version' => 1, 'priority' => 10, 'load_policy' => 'yes', 'key_value' => 'old'],
        ['key_name' => 'site_title', 'version' => 1, 'priority' => 5, 'load_policy' => 'no', 'key_value' => 'title'],
    ],
    [
        ['key_name' => 'base_url', 'version' => 1, 'priority' => 12, 'load_policy' => 'yes', 'key_value' => 'new'],
        ['key_name' => 'module_registry', 'version' => 1, 'priority' => 4, 'load_policy' => 'no', 'key_value' => 'module'],
    ],
    ['key_name'],
    ['version', 'priority'],
);

$windowRowValueKeys = static function () use ($windowRowValueDefaults): array {
    $row = $windowRowValueDefaults()['window_rows'][0] ?? [];
    ksort($row);

    return array_keys($row);
};

return [
    'source-neutral compound window defaults dynamic source has no legacy setting table terms' => static fn (TestRunner $t) => $t->same([], $compoundWindowSourceMatches()),
    'source-neutral row-value window defaults expose generic key names' => static fn (TestRunner $t) => $t->same([
        'action',
        'first_key_name',
        'frame',
        'frame_count',
        'frame_priority_concat',
        'frame_priority_sum',
        'key_name',
        'last_key_name',
        'sequence',
    ], $windowRowValueKeys()),
];
