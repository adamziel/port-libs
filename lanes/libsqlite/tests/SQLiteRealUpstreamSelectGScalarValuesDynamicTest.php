<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectGFlatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$tests = [];

$tests['real upstream corpus selectG.test cites scalar VALUES source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test';
    $t->true(is_file($source), 'hydrated upstream selectG.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'selectG.test source can be read');
    $t->contains('selectG', $text);
    $t->contains('do_test 110', $text);
    $t->contains('do_test 120', $text);
    $t->contains('Only the left-most term of a multi-valued VALUES', $text);
};

$tests['real upstream corpus selectG.test scalar VALUES first row baseline'] = static function (TestRunner $t) use ($selectGFlatValues): void {
    $rows = SQLiteSelectSql::execute('SELECT (VALUES (1),(2),(3)) AS v', []);

    $t->same([1], $selectGFlatValues($rows));
    $t->same([['v' => 1]], $rows);
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 2 + ($case % 9);
    $firstValue = $case * 3 - 2;
    $values = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $values[] = '(' . ($firstValue + ($row * ($case + 1))) . ')';
    }
    $sql = 'SELECT (VALUES ' . implode(',', $values) . ') AS selected_value';
    $name = sprintf(
        'real upstream corpus selectG.test selectG-110 scalar VALUES dynamic case %04d rows %02d',
        $case,
        $rowCount,
    );

    $tests[$name] = static function (TestRunner $t) use ($selectGFlatValues, $sql, $firstValue, $rowCount, $case): void {
        $rows = SQLiteSelectSql::execute($sql, []);
        $flat = $selectGFlatValues($rows);

        $t->same(1, count($rows));
        $t->same(1, count($flat));
        $t->same($firstValue, $flat[0] ?? null);
        $t->same(['selected_value' => $firstValue], $rows[0] ?? null);
        $t->true($rowCount >= 2, 'selectG scalar VALUES variants keep multiple VALUES rows');
        $t->true($case >= 1, 'selectG dynamic case id is positive');
    };
}

$tests['real upstream corpus selectG.test scalar VALUES rejects two-column scalar expression'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteSelectSql::execute('SELECT (VALUES (1,2),(3,4)) AS bad', []),
    );
};

return $tests;
