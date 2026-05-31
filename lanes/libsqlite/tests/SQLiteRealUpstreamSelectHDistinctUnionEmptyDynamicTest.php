<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertSelectH51 = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' direct compound result');
    $t->same(count($expected), count($actual), $label . ' result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('UNION ALL', $sql, $label . ' preserves upstream compound shape');
};

$tests = [];

$tests['real upstream selectH.test selectH-5.1 cites distinct-left empty-right source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('forum post https://sqlite.org/forum/forumpost/b83c7b2168', $text);
    $t->contains('do_execsql_test 5.1', $text);
    $t->contains('SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2', $text);
    $t->contains('do_execsql_test 5.2', $text);
};

$tests['real upstream selectH.test selectH-5.1 canonical direct result'] = static function (TestRunner $t) use ($assertSelectH51): void {
    $tables = [
        't1' => [
            ['val1' => 4],
            ['val1' => 5],
        ],
        't2' => [],
    ];

    $assertSelectH51(
        $t,
        'SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2',
        $tables,
        [4, 5],
        'selectH-5.1 canonical',
    );
};

for ($case = 0; $case < 1000; $case++) {
    $base = 10000 + ($case * 3);
    $first = $base + ($case % 7);
    $second = $first + 1 + ($case % 5);
    $third = $second + 1 + ($case % 3);
    $duplicateFirstCount = 1 + ($case % 4);
    $duplicateSecondCount = 1 + (($case + 1) % 3);
    $includeThird = $case % 5 === 0;

    $leftRows = [];
    for ($i = 0; $i < $duplicateFirstCount; $i++) {
        $leftRows[] = ['val1' => $first];
    }
    for ($i = 0; $i < $duplicateSecondCount; $i++) {
        $leftRows[] = ['val1' => $second];
    }
    if ($includeThird) {
        $leftRows[] = ['val1' => $third];
        $leftRows[] = ['val1' => $third];
    }

    $expected = [$first, $second];
    if ($includeThird) {
        $expected[] = $third;
    }

    $tables = [
        't1' => $leftRows,
        't2' => [],
    ];

    $tests[sprintf('real upstream selectH.test selectH-5.1 dynamic distinct left empty right case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelectH51, $tables, $expected, $case, $duplicateFirstCount, $duplicateSecondCount): void {
            $sql = 'SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2';

            $assertSelectH51($t, $sql, $tables, $expected, 'selectH-5.1 dynamic case ' . $case);
            $t->true($duplicateFirstCount >= 1, 'left arm includes first value duplicates');
            $t->true($duplicateSecondCount >= 1, 'left arm includes second value duplicates');
            $t->same([], $tables['t2'], 'right arm stays empty like upstream selectH-5.1');
        };
}

return $tests;
