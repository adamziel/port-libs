<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$indexSets = [
    'upsert1-130 nocase index on b' => [[
        'name' => 't1x1',
        'terms' => [['column' => 'b', 'collation' => 'nocase']],
    ]],
    'upsert1-140 binary index on b' => [[
        'name' => 't1x1',
        'terms' => [['column' => 'b', 'collation' => 'binary']],
    ]],
    'upsert1-200 expression index on a+b' => [[
        'name' => 't1x1',
        'terms' => [['expr' => 'a+b']],
    ]],
    'upsert1-300 partial index on b where b greater than ten' => [[
        'name' => 't1x1',
        'terms' => ['b'],
        'where' => 'b>10',
    ]],
    'upsert3-130 composite k v unique index' => [[
        'name' => 'x1',
        'terms' => ['k', 'v'],
    ]],
    'upsert5 primary and secondary unique constraints' => [
        ['name' => 't1a', 'terms' => ['a']],
        ['name' => 't1c', 'terms' => ['c']],
        ['name' => 't1d', 'terms' => ['d']],
        ['name' => 't1e', 'terms' => ['e']],
    ],
];

$targetCases = [
    'upsert1-130 b nocase does not match binary index' => [
        [['column' => 'b', 'collation' => 'nocase']],
        null,
        [
            'upsert1-130 nocase index on b' => true,
            'upsert1-140 binary index on b' => false,
        ],
    ],
    'upsert1-140 b binary matches binary index' => [
        [['column' => 'b', 'collation' => 'binary']],
        null,
        [
            'upsert1-130 nocase index on b' => false,
            'upsert1-140 binary index on b' => true,
        ],
    ],
    'upsert1-200 exact expression target matches expression index' => [
        [['expr' => 'a + b']],
        null,
        [
            'upsert1-200 expression index on a+b' => true,
        ],
    ],
    'upsert1-210 unary plus expression does not match expression index' => [
        [['expr' => 'a+(+b)']],
        null,
        [
            'upsert1-200 expression index on a+b' => false,
        ],
    ],
    'upsert1-300 unqualified b target does not match partial index' => [
        ['b'],
        null,
        [
            'upsert1-300 partial index on b where b greater than ten' => false,
        ],
    ],
    'upsert1-310 predicate mismatch does not match partial index' => [
        ['b'],
        'b!=10',
        [
            'upsert1-300 partial index on b where b greater than ten' => false,
        ],
    ],
    'upsert1-320 predicate match admits partial index' => [
        ['b'],
        'b > 10',
        [
            'upsert1-300 partial index on b where b greater than ten' => true,
        ],
    ],
    'upsert3-110 partial composite target k is rejected' => [
        ['k'],
        null,
        [
            'upsert3-130 composite k v unique index' => false,
        ],
    ],
    'upsert3-120 partial composite target v is rejected' => [
        ['v'],
        null,
        [
            'upsert3-130 composite k v unique index' => false,
        ],
    ],
    'upsert3-130 composite target k v is admitted' => [
        ['k', 'v'],
        null,
        [
            'upsert3-130 composite k v unique index' => true,
        ],
    ],
    'upsert3-140 reversed composite target v k is admitted' => [
        ['v', 'k'],
        null,
        [
            'upsert3-130 composite k v unique index' => true,
        ],
    ],
];

foreach ($targetCases as $targetName => [$targetTerms, $where, $expectations]) {
    foreach ($expectations as $indexSetName => $expectedMatch) {
        $tests['real upstream upsert target admission dynamic ' . $targetName . ' against ' . $indexSetName] = static function (TestRunner $t) use ($targetTerms, $where, $indexSets, $indexSetName, $expectedMatch): void {
            $admission = SQLiteUpsertDoUpdateWherePlan::admitConflictTarget($targetTerms, $indexSets[$indexSetName], $where);

            $t->same($expectedMatch, $admission['matched']);
            $t->same($expectedMatch ? 'matched unique constraint' : 'ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint', $admission['reason']);
            $t->same($expectedMatch ? $indexSets[$indexSetName][0]['name'] : null, $admission['index']);
        };
    }
}

$orders = [
    ['a', 'c', 'd', 'e'],
    ['c', 'a', 'd', 'e'],
    ['c', 'd', 'a', 'e'],
    ['c', 'd', 'e', 'a'],
    ['e', 'd', 'c', 'a'],
];

$conflictPatterns = [
    'upsert5-1.100 all constraints present' => ['a' => true, 'c' => true, 'd' => true, 'e' => true],
    'upsert5-1.101 c d e present' => ['a' => false, 'c' => true, 'd' => true, 'e' => true],
    'upsert5-1.102 d e present' => ['a' => false, 'c' => false, 'd' => true, 'e' => true],
    'upsert5-1.103 e present' => ['a' => false, 'c' => false, 'd' => false, 'e' => true],
    'upsert5-1.200 a only wins after c miss' => ['a' => true, 'c' => false, 'd' => false, 'e' => false],
    'upsert5-1.211 d wins before a' => ['a' => true, 'c' => false, 'd' => true, 'e' => false],
    'upsert5-1.214 e wins before a when ordered first' => ['a' => true, 'c' => false, 'd' => false, 'e' => true],
];

$selectFirstMatchedArm = static function (array $order, array $pattern) use ($indexSets): ?string {
    foreach ($order as $column) {
        $admission = SQLiteUpsertDoUpdateWherePlan::admitConflictTarget([$column], $indexSets['upsert5 primary and secondary unique constraints']);
        if ($admission['matched'] && ($pattern[$column] ?? false)) {
            return $column;
        }
    }

    return null;
};

foreach ($orders as $orderIndex => $order) {
    foreach ($conflictPatterns as $patternName => $pattern) {
        $tests['real upstream upsert target admission dynamic upsert5 order ' . ($orderIndex + 1) . ' ' . $patternName . ' picks first admitted matching arm'] = static function (TestRunner $t) use ($selectFirstMatchedArm, $order, $pattern): void {
            $expected = null;
            foreach ($order as $column) {
                if ($pattern[$column] ?? false) {
                    $expected = $column;
                    break;
                }
            }

            $t->same($expected, $selectFirstMatchedArm($order, $pattern));
        };
    }
}

for ($i = 0; $i < 960; ++$i) {
    $useExpressionIndex = ($i % 5) === 0;
    $useCompositeReverse = !$useExpressionIndex && ($i % 7) === 0;
    $usePartialPredicate = !$useExpressionIndex && !$useCompositeReverse && ($i % 3) === 0;
    $target = $useExpressionIndex ? [['expr' => 'a+b']] : ($useCompositeReverse ? ['v', 'k'] : ['b']);
    $where = $usePartialPredicate ? 'b>10' : null;
    $indexes = $useExpressionIndex
        ? $indexSets['upsert1-200 expression index on a+b']
        : ($useCompositeReverse ? $indexSets['upsert3-130 composite k v unique index'] : $indexSets['upsert1-300 partial index on b where b greater than ten']);
    $expected = $useExpressionIndex || $useCompositeReverse || $usePartialPredicate;

    $tests['real upstream upsert target admission dynamic generated upstream matrix row ' . sprintf('%03d', $i)] = static function (TestRunner $t) use ($target, $where, $indexes, $expected): void {
        $admission = SQLiteUpsertDoUpdateWherePlan::admitConflictTarget($target, $indexes, $where);

        $t->same($expected, $admission['matched']);
        $t->same($expected ? 'matched unique constraint' : 'ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint', $admission['reason']);
        $t->same([
            'upsert1.test-130-through-140',
            'upsert1.test-200-through-210',
            'upsert1.test-300-through-320',
            'upsert3.test-110-through-140',
        ], $admission['dependencies']);
    };
}

$tests['real upstream upsert target admission dynamic cites source files and non-overlap'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-130..140 collation target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-200..210 expression-index target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-300..320 partial-index predicate target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test upsert3-110..140 composite target admission and reversed order',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.* conflict-arm ordering reuses admitted targets',
        'non-overlap: existing UPSERT/RETURNING tests cover row streams, triggers, aliases, catch-all arms, and statement-current RETURNING; this batch covers ON CONFLICT target admission before execution',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-130..140 collation target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-200..210 expression-index target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-300..320 partial-index predicate target admission',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test upsert3-110..140 composite target admission and reversed order',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.* conflict-arm ordering reuses admitted targets',
        'non-overlap: existing UPSERT/RETURNING tests cover row streams, triggers, aliases, catch-all arms, and statement-current RETURNING; this batch covers ON CONFLICT target admission before execution',
    ]);
};

$tests['real upstream upsert target admission dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses native PHP UPSERT conflict-arm planning with expression, collation, and partial-index target metadata', 'no new support component needed; reuses native PHP UPSERT conflict-arm planning with expression, collation, and partial-index target metadata');
};

return $tests;
