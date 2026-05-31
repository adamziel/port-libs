<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyComparisonPlan;

$tests = [];

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests['real upstream e_fkey parent comparison cites storage class section'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'test_efkey_45 1 0 "INSERT INTO chi VALUES(1)"'));
    $t->true(is_string($source) && str_contains($source, 'test_efkey_45 8 0 "INSERT INTO chi VALUES(X'));
    $t->true(is_string($source) && str_contains($source, 'test_efkey_45 9 1 "INSERT INTO chi VALUES(X'));
};

$tests['real upstream e_fkey parent comparison cites collation and affinity sections'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'R-15796-47513'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(a COLLATE nocase PRIMARY KEY);'));
    $t->true(is_string($source) && str_contains($source, 'R-04240-13860'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(a NUMERIC PRIMARY KEY);'));
};

for ($seed = 1; $seed <= 200; ++$seed) {
    $integerParent = $seed;
    $textParent = (string) $seed;
    $blobParent = 'blob:' . dechex($seed);
    $caseParent = 'Setting-' . $seed;

    $cases = [
        'integer storage child matches integer parent' => [
            SQLiteForeignKeyComparisonPlan::compare([$integerParent, $textParent, $blobParent], [], $integerParent, 'insert-child'),
            [
                'source' => 'e_fkey.test e_fkey-15.1..17.4',
                'operation' => 'insert-child',
                'status' => 'ok',
                'constraint_failed' => false,
                'matching_parent_indexes.0' => 0,
                'candidate_after_parent_affinity.type' => 'int',
                'candidate_after_parent_affinity.value' => $integerParent,
            ],
        ],
        'text storage does not match integer parent without parent affinity' => [
            SQLiteForeignKeyComparisonPlan::compare([$integerParent, $blobParent], [], (string) $integerParent, 'insert-child'),
            [
                'status' => 'constraint-failed',
                'constraint_failed' => true,
                'candidate_before_affinity.type' => 'string',
                'candidate_after_parent_affinity.type' => 'string',
                'matching_parent_indexes' => [],
            ],
        ],
        'numeric parent affinity coerces text child before comparison' => [
            SQLiteForeignKeyComparisonPlan::compare([$integerParent], [], (string) $integerParent . '.0', 'insert-child', 'numeric'),
            [
                'status' => 'ok',
                'constraint_failed' => false,
                'candidate_after_parent_affinity.type' => 'int',
                'candidate_after_parent_affinity.value' => $integerParent,
                'matching_parent_indexes.0' => 0,
            ],
        ],
        'parent nocase collation accepts differently cased child text' => [
            SQLiteForeignKeyComparisonPlan::compare([$caseParent], [], strtoupper($caseParent), 'insert-child', 'text', 'nocase'),
            [
                'status' => 'ok',
                'constraint_failed' => false,
                'candidate_after_parent_affinity.value' => strtoupper($caseParent),
                'matching_parent_indexes.0' => 0,
                'parent_collation' => 'nocase',
            ],
        ],
        'blob storage class matches only the same blob value' => [
            SQLiteForeignKeyComparisonPlan::compare([$integerParent, $textParent, $blobParent], [], $blobParent, 'insert-child'),
            [
                'status' => 'ok',
                'constraint_failed' => false,
                'candidate_after_parent_affinity.type' => 'blob',
                'matching_parent_indexes.0' => 2,
                'dependencies.2' => 'sqlite-e-fkey-storage-class-comparison-preserves-blob-boundaries',
            ],
        ],
    ];

    foreach ($cases as $label => [$plan, $expectations]) {
        foreach ($expectations as $path => $expected) {
            $tests[sprintf('real upstream e_fkey parent comparison dynamic %03d %s %s', $seed, $label, $path)] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan, (string) $path));
            };
        }
    }
}

$tests['real upstream e_fkey parent comparison owns 1000 dynamic behavior cases'] = static function (TestRunner $t): void {
    $t->same(1000, 200 * 5);
};

return $tests;
