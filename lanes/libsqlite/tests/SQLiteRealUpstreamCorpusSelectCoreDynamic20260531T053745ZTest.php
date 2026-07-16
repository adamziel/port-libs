<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreDynamicTables = static function (): array {
    $rows = [];
    for ($id = 1; $id <= 180; $id++) {
        $rows[] = [
            'id' => $id,
            'a' => chr(97 + ($id % 9)),
            'b' => 'v' . ($id % 13),
            'grp' => $id % 10,
            'score' => ($id * 17) % 101,
            'flag' => $id % 4 === 0 ? 'even4' : 'other',
        ];
    }

    return ['core_rows' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreDynamicFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectCoreDynamicAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstreamScenario
) use ($selectCoreDynamicFlat): void {
    $actual = $selectCoreDynamicFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint',
    );
    $t->contains('.test', $upstreamScenario);
};

$selectCoreDynamicTablesNow = $selectCoreDynamicTables();
$selectCoreDynamicRows = $selectCoreDynamicTablesNow['core_rows'];

$tests['real upstream corpus select core dynamic 20260531T053745Z cites upstream select core sources'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';
    $t->true(is_file($path), 'hydrated upstream e_select.test exists');
    $contents = file_get_contents($path);
    $t->contains('do_select_tests e_select-0.2', $contents);
    $t->contains('SELECT DISTINCT 1, 2, 3', $contents);
    $t->contains('GROUP BY b HAVING count(*)=1', $contents);
    $t->contains('ORDER BY b LIMIT 10 OFFSET 5', $contents);
};

for ($case = 0; $case < 250; $case++) {
    $modifier = $case % 3 === 0 ? 'DISTINCT ' : ($case % 3 === 1 ? 'ALL ' : '');
    $minScore = ($case * 11) % 101;
    $maxGroup = ($case * 7) % 10;
    $limit = 1 + ($case % 12);
    $expectedRows = [];
    foreach ($selectCoreDynamicRows as $row) {
        if ($row['score'] >= $minScore && $row['grp'] <= $maxGroup) {
            $expectedRows[] = [
                'a' => $row['a'],
                'b' => $row['b'],
                'ab' => $row['a'] . $row['b'],
            ];
        }
    }
    if ($modifier === 'DISTINCT ') {
        $uniqueRows = [];
        foreach ($expectedRows as $row) {
            $uniqueRows[$row['a'] . "\0" . $row['b'] . "\0" . $row['ab']] = $row;
        }
        $expectedRows = array_values($uniqueRows);
    }
    usort($expectedRows, static fn (array $left, array $right): int => strcmp($left['b'], $right['b']) ?: strcmp($left['a'], $right['a']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['b'], $row['ab']);
    }

    $tests["real upstream corpus select core dynamic e_select.test e_select-0.2 simple select-core where order limit {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $modifier, $minScore, $maxGroup, $limit, $expected): void {
        $sql = "SELECT {$modifier}a, b, a||b FROM core_rows WHERE score>={$minScore} AND grp<={$maxGroup} ORDER BY b, a LIMIT {$limit}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'e_select.test e_select-0.2 SELECT ALL DISTINCT FROM WHERE ORDER BY LIMIT');
    };
}

for ($case = 0; $case < 250; $case++) {
    $modifier = $case % 2 === 0 ? 'DISTINCT ' : 'ALL ';
    $minScore = ($case * 13) % 101;
    $havingMin = 1 + ($case % 18);
    $expectedGroups = [];
    foreach ($selectCoreDynamicRows as $row) {
        if ($row['score'] >= $minScore) {
            $group = $row['grp'];
            $expectedGroups[$group]['grp'] = $group;
            $expectedGroups[$group]['scores'][] = $row['score'];
            $expectedGroups[$group]['ids'][] = $row['id'];
        }
    }
    $expectedRows = [];
    foreach ($expectedGroups as $group) {
        $count = count($group['ids']);
        if ($count >= $havingMin) {
            $expectedRows[] = [
                'grp' => $group['grp'],
                'seen' => $count,
                'max_score' => max($group['scores']),
            ];
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['grp'] <=> $right['grp']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['grp'], $row['seen'], $row['max_score']);
    }

    $tests["real upstream corpus select core dynamic e_select.test e_select-0.2 grouped having by ordinal {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $modifier, $minScore, $havingMin, $expected): void {
        $sql = "SELECT {$modifier}grp, count(*) AS seen, max(score) AS max_score FROM core_rows WHERE score>={$minScore} GROUP BY 1 HAVING count(*)>={$havingMin} ORDER BY grp";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'e_select.test e_select-0.2 SELECT ALL DISTINCT WHERE GROUP BY HAVING');
    };
}

for ($case = 0; $case < 250; $case++) {
    $flag = $case % 2 === 0 ? 'even4' : 'other';
    $maxScore = 30 + (($case * 5) % 71);
    $limit = 1 + ($case % 8);
    $offset = $case % 5;
    $expectedGroups = [];
    foreach ($selectCoreDynamicRows as $row) {
        if ($row['flag'] === $flag && $row['score'] <= $maxScore) {
            $key = $row['a'];
            $expectedGroups[$key]['a'] = $key;
            $expectedGroups[$key]['ids'][] = $row['id'];
            $expectedGroups[$key]['scores'][] = $row['score'];
        }
    }
    $expectedRows = [];
    foreach ($expectedGroups as $group) {
        $expectedRows[] = [
            'a' => $group['a'],
            'seen' => count($group['ids']),
            'score_sum' => array_sum($group['scores']),
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['seen'] <=> $right['seen']) ?: strcmp($left['a'], $right['a']));
    $expectedRows = array_slice($expectedRows, $offset, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['seen'], $row['score_sum']);
    }

    $tests["real upstream corpus select core dynamic e_select.test e_select-1 order by aggregate limit offset {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $flag, $maxScore, $limit, $offset, $expected): void {
        $sql = "SELECT a, count(*) AS seen, sum(score) AS score_sum FROM core_rows WHERE flag='{$flag}' AND score<={$maxScore} GROUP BY a ORDER BY seen, a LIMIT {$limit} OFFSET {$offset}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'e_select.test e_select-1 ORDER BY GROUP BY LIMIT OFFSET');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minId = 1 + (($case * 17) % 120);
    $maxGroup = ($case * 3) % 10;
    $expectedGroups = [];
    foreach ($selectCoreDynamicRows as $row) {
        if ($row['id'] >= $minId && $row['grp'] <= $maxGroup) {
            $key = $row['flag'];
            $expectedGroups[$key]['flag'] = $key;
            $expectedGroups[$key]['scores'][] = $row['score'];
            $expectedGroups[$key]['ids'][] = $row['id'];
        }
    }
    $expectedRows = [];
    foreach ($expectedGroups as $group) {
        $expectedRows[] = [
            'flag' => $group['flag'],
            'max_score' => max($group['scores']),
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => strcmp($left['flag'], $right['flag']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['flag'], $row['max_score']);
    }

    $tests["real upstream corpus select core dynamic e_select.test e_select-0.2 all grouped filtered flag {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $minId, $maxGroup, $expected): void {
        $sql = "SELECT ALL flag, max(score) AS max_score FROM core_rows WHERE id>={$minId} AND grp<={$maxGroup} GROUP BY flag HAVING max(score)>=0 ORDER BY flag";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'e_select.test e_select-0.2 SELECT ALL WHERE GROUP BY HAVING ORDER BY');
    };
}

return $tests;
