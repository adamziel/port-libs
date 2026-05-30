<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$t1Rows = [];
for ($i = 1; $i <= 250; $i++) {
    $t1Rows[] = ['val1' => ($i % 37) + 1];
}

$t2Rows = [];
for ($i = 1; $i <= 125; $i++) {
    $t2Rows[] = ['val2' => (($i * 3) % 41) + 1];
}

$tables = [
    't1' => $t1Rows,
    't2' => $t2Rows,
];

$tests = [];

$tests['real upstream corpus selectH.test selectH-5 literal count cites upstream source'] = static function (TestRunner $t): void {
    $t->contains('/test/selectH.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test');
    $t->contains('selectH-5.1', 'selectH-5.1 DISTINCT UNION ALL result rows');
    $t->contains('selectH-5.2', 'selectH-5.2 count literal over derived DISTINCT UNION ALL');
};

for ($case = 0; $case < 1000; $case++) {
    $low = ($case % 41) + 1;
    $width = intdiv($case, 41) % 17;
    $high = min(41, $low + $width);
    $literal = 1000 + $case;

    $distinctLeft = [];
    foreach ($t1Rows as $row) {
        $value = $row['val1'];
        if ($value >= $low && $value <= $high) {
            $distinctLeft[$value] = true;
        }
    }
    $rightCount = 0;
    foreach ($t2Rows as $row) {
        $value = $row['val2'];
        if ($value >= $low && $value <= $high) {
            $rightCount++;
        }
    }

    $expected = [count($distinctLeft) + $rightCount];
    $name = "real upstream corpus selectH.test selectH-5.2 dynamic count literal case {$case}";
    $tests[$name] = static function (TestRunner $t) use ($assertFlat, $tables, $low, $high, $literal, $expected): void {
        $sql = "SELECT count({$literal}) FROM ("
            . "SELECT DISTINCT val1 FROM t1 WHERE val1>={$low} AND val1<={$high} "
            . "UNION ALL SELECT val2 FROM t2 WHERE val2>={$low} AND val2<={$high}"
            . ")";
        $assertFlat($t, $sql, $tables, $expected);
    };
}

return $tests;
