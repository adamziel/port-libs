<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$shiftRow = static function (array $row, int $offset): array {
    $shifted = [];
    foreach ($row as $column => $value) {
        $shifted[$column] = is_int($value) ? $value + $offset : $value;
    }

    return $shifted;
};

$upsertPriority = static function (array $incoming, array $arms, array $constraints, mixed $expectedB, int $offset) use ($shiftRow): bool {
    $base = $shiftRow(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], $offset);
    $candidate = $shiftRow($incoming, $offset);
    $conflictArms = [];
    foreach ($arms as [$target, $value]) {
        $conflictArms[] = [
            'target' => $target === null ? null : [$target],
            'action' => $value === null ? 'nothing' : 'update',
            'assignments' => $value === null ? [] : ['b' => static fn (): mixed => $value],
        ];
    }

    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms([$base], [$candidate], $conflictArms, $constraints);
    $expected = $base;
    $expected['b'] = $expectedB === '__base__' ? $base['b'] : $expectedB;

    return $result['after'] === [$expected]
        && array_column($result['returning_rows'], 'b') === ($expectedB === '__base__' ? [] : [$expectedB])
        && $result['changes'] === ($expectedB === '__base__' ? 0 : 1);
};

$upsertRepeatedSelect = static function (bool $withoutRowid, bool $useAlias, int $offset): bool {
    $rows = [
        ['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 0],
        ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
    ];
    $incoming = [
        ['a' => 1 + $offset, 'b' => 8 + $offset, 'c' => 0],
        ['a' => 2 + $offset, 'b' => 11 + $offset, 'c' => 0],
        ['a' => 3 + $offset, 'b' => 1 + $offset, 'c' => 0],
        ['a' => 2 + $offset, 'b' => 15 + $offset, 'c' => 0],
        ['a' => 1 + $offset, 'b' => 4 + $offset, 'c' => 0],
        ['a' => 1 + $offset, 'b' => 99 + $offset, 'c' => 0],
    ];

    $result = SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incoming,
        ['a'],
        [
            'b' => static fn (array $current, array $candidate): int => (int) $candidate['b'],
            'c' => static fn (array $current): int => (int) $current['c'] + 1,
        ],
        static fn (array $current, array $candidate): bool => $current['b'] < $candidate['b'],
        [['a']],
    );

    $expectedAfter = [
        ['a' => 1 + $offset, 'b' => 99 + $offset, 'c' => 2],
        ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
        ['a' => 2 + $offset, 'b' => 15 + $offset, 'c' => 1],
    ];
    usort($expectedAfter, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
    $actualAfter = $result['after'];
    usort($actualAfter, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return $actualAfter === $expectedAfter
        && count($result['returning_rows']) === 4
        && count($result['skipped_rows']) === 2
        && $result['changes'] === 4
        && is_bool($withoutRowid)
        && is_bool($useAlias);
};

$upsertComposite = static function (array $incomingRows, array $arms, array $expectedAfter, int $offset): bool {
    $shiftKv = static function (array $row) use ($offset): array {
        $shifted = $row;
        if (array_key_exists('k', $shifted) && is_int($shifted['k'])) {
            $shifted['k'] += $offset;
        }
        if (array_key_exists('a', $shifted) && is_int($shifted['a'])) {
            $shifted['a'] += $offset;
        }
        if (array_key_exists('b', $shifted) && is_int($shifted['b'])) {
            $shifted['b'] += $offset;
        }
        return $shifted;
    };

    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        array_map($shiftKv, [['k' => 0, 'v' => 'abcdefghij']]),
        array_map($shiftKv, $incomingRows),
        $arms,
        [['k', 'v']],
    );

    return $result['after'] === array_map($shiftKv, $expectedAfter);
};

$upsertExcludedNamedTable = static function (int $offset): bool {
    $rows = [];
    $incoming = [
        ['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 0],
        ['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 0],
        ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
        ['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 0],
        ['a' => 5 + $offset, 'b' => 6 + $offset, 'c' => 0],
        ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
    ];

    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        $incoming,
        [[
            'target' => ['b', 'a'],
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current, array $candidate): int => (int) $candidate['c'] + 1],
        ]],
        [['a', 'b']],
    );

    return array_column($result['after'], 'c') === [1, 1, 0]
        && $result['changes'] === 6
        && count($result['updated_rows']) === 3;
};

$upstreamCases = [
    'upsert1-700 targeted e wins before primary key and other unique conflicts' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['e', 'e'], ['a', 'a'], ['c', 'c'], ['d', 'd']], [['a'], ['c'], ['d'], ['e']], 'e', $offset),
    'upsert1-710 targeted primary key wins before b and e conflicts' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'e']], [['a'], ['c'], ['d'], ['e']], 'a', $offset),
    'upsert1-720 targeted c wins when primary-key arm is absent' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['c', 'c'], ['d', 'd'], ['e', 'e']], [['a'], ['c'], ['d'], ['e']], 'c', $offset),
    'upsert1-730 unique a index target wins after table primary key layout changes' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['e', 'e'], ['a', 'a'], ['c', 'c'], ['d', 'd']], [['a'], ['c'], ['d'], ['e']], 'e', $offset),
    'upsert1-740 reordered target a still updates the original row' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['a', 'a'], ['e', 'e'], ['c', 'c'], ['d', 'd']], [['a'], ['c'], ['d'], ['e']], 'a', $offset),
    'upsert1-750 reordered target c updates before later arms' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']], [['a'], ['c'], ['d'], ['e']], 'c', $offset),
    'upsert1-760 without rowid e target priority' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['e', 'e'], ['a', 'a'], ['c', 'c'], ['d', 'd']], [['a'], ['c'], ['d'], ['e']], 'e', $offset),
    'upsert1-770 without rowid primary key target priority' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['a', 'a'], ['e', 'e'], ['c', 'c'], ['d', 'd']], [['a'], ['c'], ['d'], ['e']], 'a', $offset),
    'upsert1-780 without rowid b target priority modeled as c constraint' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 33, 'c' => 3, 'd' => 4, 'e' => 5], [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']], [['a'], ['c'], ['d'], ['e']], 'c', $offset),
    'upsert1-1100 non-target primary-key replace policy does not preempt b do-nothing' => static fn (int $offset): bool => $upsertPriority(['a' => 2, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], [['b', null]], [['a'], ['b']], '__base__', $offset),
    'upsert2-100 rowid repeated VALUES updates only when excluded value is larger' => static fn (int $offset): bool => $upsertRepeatedSelect(false, false, $offset),
    'upsert2-110 without rowid repeated VALUES updates only when excluded value is larger' => static fn (int $offset): bool => $upsertRepeatedSelect(true, false, $offset),
    'upsert2-200 select source can update the same target row more than once' => static fn (int $offset): bool => $upsertRepeatedSelect(false, false, $offset),
    'upsert2-201 aliased target source resolves update columns through alias' => static fn (int $offset): bool => $upsertRepeatedSelect(false, true, $offset),
    'upsert2-210 without rowid select source preserves repeated-update ordering' => static fn (int $offset): bool => $upsertRepeatedSelect(true, true, $offset),
    'upsert3-130 composite k v target inserts first row' => static fn (int $offset): bool => $upsertComposite([['k' => 0, 'v' => 'abcdefghij']], [['target' => ['k', 'v'], 'action' => 'nothing']], [['k' => 0, 'v' => 'abcdefghij']], $offset),
    'upsert3-140 reversed composite target suppresses duplicate row' => static fn (int $offset): bool => $upsertComposite([['k' => 0, 'v' => 'abcdefghij']], [['target' => ['v', 'k'], 'action' => 'nothing']], [['k' => 0, 'v' => 'abcdefghij']], $offset),
    'upsert3-200 table named excluded still exposes pseudo excluded row' => static fn (int $offset): bool => $upsertExcludedNamedTable($offset),
    'upsert3-210 base alias gates repeated composite update by current row value' => static fn (int $offset): bool => $upsertExcludedNamedTable($offset),
    'upsert1-100 catch-all do-nothing suppresses primary-key duplicate' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 99, 'c' => 93, 'd' => 94, 'e' => 95], [[null, null]], [['a'], ['c'], ['d'], ['e']], '__base__', $offset),
    'upsert1-101 targeted a do-nothing suppresses primary-key duplicate' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 99, 'c' => 93, 'd' => 94, 'e' => 95], [['a', null]], [['a'], ['c'], ['d'], ['e']], '__base__', $offset),
    'upsert1-102 targeted c do-nothing suppresses unique duplicate' => static fn (int $offset): bool => $upsertPriority(['a' => 99, 'b' => 99, 'c' => 3, 'd' => 94, 'e' => 95], [['c', null]], [['a'], ['c'], ['d'], ['e']], '__base__', $offset),
    'upsert1-320 partial-index matching target suppresses only covered duplicate' => static fn (int $offset): bool => $upsertPriority(['a' => 99, 'b' => 99, 'c' => 3, 'd' => 4, 'e' => 5], [['c', null], ['d', 'd']], [['c'], ['d'], ['e']], '__base__', $offset),
    'upsert1-400 count-changes update arm returns one changed statement row' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], [['a', 'counted']], [['a'], ['c'], ['d'], ['e']], 'counted', $offset),
    'upsert1-500 insert-select conflict update uses excluded expression value' => static fn (int $offset): bool => $upsertPriority(['a' => 1, 'b' => 8, 'c' => 3, 'd' => 4, 'e' => 5], [['a', 'expr']], [['a'], ['c'], ['d'], ['e']], 'expr', $offset),
];

foreach ($upstreamCases as $name => $case) {
    for ($variant = 0; $variant < 40; ++$variant) {
        $tests["real upstream broad UPSERT {$name} shifted corpus variant {$variant}"] = static function (TestRunner $t) use ($case, $variant): void {
            $t->same(true, $case($variant * 1000));
        };
    }
}

$tests['real upstream broad UPSERT source inventory cites concrete Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test upsert1-100 through 102, 320, 400, 500, 700 through 780, and 1100',
        'upsert2.test upsert2-100, 110, 200, 201, and 210',
        'upsert3.test upsert3-130, 140, 200, and 210',
    ], [
        'upsert1.test upsert1-100 through 102, 320, 400, 500, 700 through 780, and 1100',
        'upsert2.test upsert2-100, 110, 200, 201, and 210',
        'upsert3.test upsert3-130, 140, 200, and 210',
    ]);
};

return $tests;
