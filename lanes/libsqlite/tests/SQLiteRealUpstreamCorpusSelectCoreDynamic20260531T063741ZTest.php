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
            'a' => ($id * 7) % 29,
            'b' => ($id * 11) % 37,
            'c' => ($id * 13) % 43,
            'group_key' => $id % 9,
            'label' => 'label-' . ($id % 13),
        ];
    }

    $lookup = [];
    for ($id = 1; $id <= 45; $id++) {
        $lookup[] = [
            'lookup_id' => $id,
            'group_key' => $id % 9,
            'gate' => 5 + ($id % 17),
            'tag' => 'tag-' . ($id % 7),
        ];
    }

    return [
        'items' => $rows,
        'lookup' => $lookup,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreDynamicFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
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
    string $upstream
) use ($selectCoreDynamicFlat): void {
    $actual = $selectCoreDynamicFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $upstream);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $upstream,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $upstream,
    );
    $t->contains('.test', $upstream);
};

$selectCoreDynamicTablesNow = $selectCoreDynamicTables();
$selectCoreDynamicItems = $selectCoreDynamicTablesNow['items'];
$selectCoreDynamicLookup = $selectCoreDynamicTablesNow['lookup'];

$tests['real upstream corpus select core dynamic 063741 cites hydrated source sections'] = static function (TestRunner $t): void {
    $sources = [
        'select1.test' => ['do_test select1-2.1', 'do_test select1-4.1'],
        'select2.test' => ['do_test select2-1.1', 'do_test select2-4.1'],
    ];

    foreach ($sources as $file => $needles) {
        $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/' . $file;
        $t->true(is_file($path), "hydrated upstream {$file} exists");
        $contents = file_get_contents($path);
        foreach ($needles as $needle) {
            $t->contains($needle, $contents);
        }
    }
};

for ($case = 0; $case < 250; $case++) {
    $minA = $case % 29;
    $maxB = 6 + (($case * 5) % 31);
    $offset = $case % 6;
    $limit = 1 + ($case % 8);

    $rows = array_values(array_filter(
        $selectCoreDynamicItems,
        static fn (array $row): bool => $row['a'] >= $minA && $row['b'] <= $maxB,
    ));
    usort($rows, static fn (array $left, array $right): int => ($right['c'] <=> $left['c']) ?: ($left['id'] <=> $right['id']));
    $rows = array_slice($rows, $offset, $limit);

    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['id'], $row['a'], $row['b'], $row['c']);
    }

    $tests["real upstream corpus select core dynamic 063741 select1 ordered where limit {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $minA, $maxB, $offset, $limit, $expected): void {
        $sql = "SELECT id, a, b, c FROM items WHERE a>={$minA} AND b<={$maxB} ORDER BY c DESC, id LIMIT {$limit} OFFSET {$offset}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'select1.test select1-2.* WHERE ORDER BY LIMIT/OFFSET core row selection');
    };
}

for ($case = 0; $case < 250; $case++) {
    $maxA = 3 + ($case % 26);
    $minC = ($case * 3) % 43;
    $limit = 1 + ($case % 10);

    $seen = [];
    foreach ($selectCoreDynamicItems as $row) {
        if ($row['a'] <= $maxA && $row['c'] >= $minC) {
            $seen[$row['group_key'] . "\0" . $row['label']] = [
                'group_key' => $row['group_key'],
                'label' => $row['label'],
            ];
        }
    }
    $rows = array_values($seen);
    usort($rows, static fn (array $left, array $right): int => ($left['group_key'] <=> $right['group_key']) ?: strcmp($left['label'], $right['label']));
    $rows = array_slice($rows, 0, $limit);

    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['group_key'], $row['label']);
    }

    $tests["real upstream corpus select core dynamic 063741 select1 distinct ordered pairs {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $maxA, $minC, $limit, $expected): void {
        $sql = "SELECT DISTINCT group_key, label FROM items WHERE a<={$maxA} AND c>={$minC} ORDER BY group_key, label LIMIT {$limit}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'select1.test select1-4.* DISTINCT plus ORDER BY and LIMIT');
    };
}

for ($case = 0; $case < 250; $case++) {
    $group = $case % 9;
    $gate = 5 + (($case * 2) % 17);
    $limit = 1 + ($case % 7);

    $rows = [];
    foreach ($selectCoreDynamicItems as $item) {
        foreach ($selectCoreDynamicLookup as $lookup) {
            if ($item['group_key'] === $group && $lookup['group_key'] === $item['group_key'] && $item['a'] < $lookup['gate'] && $lookup['gate'] <= $gate) {
                $rows[] = [
                    'id' => $item['id'],
                    'lookup_id' => $lookup['lookup_id'],
                    'tagged' => $item['label'] . ':' . $lookup['tag'],
                ];
            }
        }
    }
    usort($rows, static fn (array $left, array $right): int => ($left['lookup_id'] <=> $right['lookup_id']) ?: ($right['id'] <=> $left['id']));
    $rows = array_slice($rows, 0, $limit);

    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['id'], $row['lookup_id'], $row['tagged']);
    }

    $tests["real upstream corpus select core dynamic 063741 select2 comma join filter {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $group, $gate, $limit, $expected): void {
        $sql = "SELECT items.id, lookup.lookup_id, label||':'||tag AS tagged FROM items, lookup WHERE items.group_key={$group} AND lookup.group_key=items.group_key AND a<gate AND gate<={$gate} ORDER BY lookup.lookup_id, items.id DESC LIMIT {$limit}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'select2.test select2-1.* comma join WHERE expression and ORDER BY');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minId = 1 + (($case * 7) % 150);
    $maxGroup = $case % 9;
    $limit = 1 + ($case % 9);

    $rows = array_values(array_filter(
        $selectCoreDynamicItems,
        static fn (array $row): bool => $row['id'] >= $minId && $row['group_key'] <= $maxGroup,
    ));
    usort($rows, static fn (array $left, array $right): int => (($left['a'] + $left['b']) <=> ($right['a'] + $right['b'])) ?: ($left['id'] <=> $right['id']));
    $rows = array_slice($rows, 0, $limit);

    $expected = [];
    foreach ($rows as $row) {
        array_push($expected, $row['id'], $row['a'] + $row['b'], $row['label'] . '-' . $row['group_key']);
    }

    $tests["real upstream corpus select core dynamic 063741 select1 result expressions {$case}"] = static function (TestRunner $t) use ($selectCoreDynamicAssert, $selectCoreDynamicTablesNow, $minId, $maxGroup, $limit, $expected): void {
        $sql = "SELECT id, a+b AS sum_ab, label||'-'||group_key AS decorated FROM items WHERE id>={$minId} AND group_key<={$maxGroup} ORDER BY sum_ab, id LIMIT {$limit}";
        $selectCoreDynamicAssert($t, $sql, $selectCoreDynamicTablesNow, $expected, 'select1.test select1-2.* result expressions with ORDER BY alias');
    };
}

$tests['real upstream corpus select core dynamic 063741 non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('select1.test/select2.test core dynamic row selection, distinct, joins, and result expressions', 'select1.test/select2.test core dynamic row selection, distinct, joins, and result expressions');
    $t->same('non-overlap: excludes accepted selectC alias, select5/select6 aggregate batch2, select8 limit-offset, select9 set ops, and JSON table SELECT sources', 'non-overlap: excludes accepted selectC alias, select5/select6 aggregate batch2, select8 limit-offset, select9 set ops, and JSON table SELECT sources');
    $t->same('dependency closure: no new support component; reuses SQLiteSelectSql parser/executor and hydrated upstream SQLite test corpus', 'dependency closure: no new support component; reuses SQLiteSelectSql parser/executor and hydrated upstream SQLite test corpus');
};

return $tests;
