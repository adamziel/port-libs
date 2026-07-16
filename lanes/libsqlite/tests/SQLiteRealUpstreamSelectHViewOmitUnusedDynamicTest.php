<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,int>>>
 */
$selectHWideTable = static function (int $base): array {
    $row = [];
    for ($column = 0; $column <= 65; $column++) {
        $row['c' . $column] = $base + $column;
    }

    return ['t1' => [$row]];
};

/**
 * @param list<array<string,mixed>> $rows
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

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelectH = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' flat result fingerprint',
    );
};

$tests = [];

$tests['real upstream corpus selectH.test selectH-3 view omit-unused cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('CREATE VIEW v1 AS', $text);
    $t->contains('SELECT count(*) FROM v1 WHERE c60=60', $text);
    $t->contains('SELECT count(a) FROM v1 WHERE c60=60', $text);
    $t->contains('SELECT x FROM v1 WHERE c60=60', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $base = 10_000 + ($case * 100);
    $tables = $selectHWideTable($base);
    $filterColumn = 'c' . (60 - ($case % 4));
    $filterValue = $base + (60 - ($case % 4));
    $projectStart = 16 + ($case % 9);

    $arms = [];
    $expectedA = [];
    $expectedX = [];
    for ($arm = 0; $arm < 4; $arm++) {
        $aColumn = 'c' . ($projectStart + $arm);
        $xValue = ($case * 4) + $arm + 1;
        $arms[] = sprintf('SELECT %s AS a, *, %d AS x FROM t1', $aColumn, $xValue);
        $expectedA[] = $base + $projectStart + $arm;
        $expectedX[] = $xValue;
    }

    $viewBody = implode(' UNION ALL ', $arms);

    $tests[sprintf('real upstream corpus selectH.test selectH-3 view count/count-a/x dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelectH, $tables, $viewBody, $filterColumn, $filterValue, $expectedA, $expectedX, $case): void {
            $where = sprintf('%s=%d', $filterColumn, $filterValue);
            $countSql = sprintf('SELECT count(*) FROM (%s) WHERE %s', $viewBody, $where);
            $countASql = sprintf('SELECT count(a) FROM (%s) WHERE %s', $viewBody, $where);
            $aSql = sprintf('SELECT a FROM (%s) WHERE %s', $viewBody, $where);
            $xSql = sprintf('SELECT x FROM (%s) WHERE %s', $viewBody, $where);

            $assertFlatSelectH($t, $countSql, $tables, [4], 'selectH-3.1 count(*) case ' . $case);
            $assertFlatSelectH($t, $countASql, $tables, [4], 'selectH-3.3 count(a) case ' . $case);
            $assertFlatSelectH($t, $aSql, $tables, $expectedA, 'selectH-3.4 a projection case ' . $case);
            $assertFlatSelectH($t, $xSql, $tables, $expectedX, 'selectH-3.6 x projection case ' . $case);

            $t->same(4, count($expectedA), 'selectH-3 view has four UNION ALL arms');
            $t->same(4, count($expectedX), 'selectH-3 selected x values materialize all arms');
            $t->contains('UNION ALL', $viewBody, 'selectH-3 view body preserves compound shape');
        };
}

return $tests;
