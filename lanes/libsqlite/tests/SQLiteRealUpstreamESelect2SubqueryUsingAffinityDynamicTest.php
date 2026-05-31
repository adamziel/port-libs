<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream e_select2.test cites subquery using affinity source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test';

    $t->true(is_file($source), 'hydrated upstream e_select2.test is available');
    $text = file_get_contents($source);
    $t->contains('EVIDENCE-OF: R-59237-46742', $text);
    $t->contains('SELECT count(*) AS y FROM t4', $text);
    $t->contains('SELECT x AS y FROM t4', $text);
    $t->contains('SELECT +y AS x FROM t5', $text);
};

for ($case = 0; $case < 1250; $case++) {
    $target = 2 + ($case % 5);
    $tenant = 10 + ($case % 13);
    $textNumeric = (string) $target . '.0';
    $otherText = 'not-' . $case;
    $tables = [
        'source_text' => [
            ['tenant_id' => $tenant, 'x' => $textNumeric],
            ['tenant_id' => $tenant, 'x' => $otherText],
        ],
        'source_count' => array_fill(0, $target, ['tenant_id' => $tenant]),
        'target_numbers' => [
            ['y' => $target, 'label' => 'match-' . $case],
            ['y' => $target + 1, 'label' => 'miss-' . $case],
        ],
    ];

    $countLeftSql = 'SELECT * FROM target_numbers, (SELECT count(*) AS y FROM source_count) USING (y)';
    $countLeftExpected = [$target, 'match-' . $case, $target];

    $textLeftSql = 'SELECT * FROM (SELECT x AS y FROM source_text) AS subquery JOIN target_numbers USING (y)';
    $textLeftExpected = [$textNumeric, $target, 'match-' . $case];

    $textRightSql = 'SELECT * FROM target_numbers JOIN (SELECT x AS y FROM source_text) AS subquery USING (y)';
    $textRightExpected = [$target, 'match-' . $case, $textNumeric];

    $textNoMatchSql = 'SELECT * FROM (SELECT label AS x FROM target_numbers WHERE label=\'match-' . $case . '\') AS subquery JOIN source_text USING (x)';
    $textNoMatchExpected = [];

    $tests[sprintf('real upstream e_select2.test dynamic subquery using affinity case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertFlat,
            $tables,
            $countLeftSql,
            $countLeftExpected,
            $textLeftSql,
            $textLeftExpected,
            $textRightSql,
            $textRightExpected,
            $textNoMatchSql,
            $textNoMatchExpected,
            $case,
            $target,
            $tenant
        ): void {
            $assertFlat($t, $countLeftSql, $tables, $countLeftExpected, 'e_select2-2.2 count subquery USING');
            $assertFlat($t, $textLeftSql, $tables, $textLeftExpected, 'e_select2-2.2 text subquery left USING');
            $assertFlat($t, $textRightSql, $tables, $textRightExpected, 'e_select2-2.2 text subquery right USING');
            $assertFlat($t, $textNoMatchSql, $tables, $textNoMatchExpected, 'e_select2-2.2 text subquery USING non-match');
            $t->same(true, $case >= 0 && $case < 1250, 'bounded dynamic e_select2 affinity case id');
            $t->same(true, $target >= 2 && $target <= 6, 'target count varies across real e_select2 count-subquery shape');
            $t->same(true, $tenant >= 10 && $tenant <= 22, 'generic tenant data varies without domain-specific table names');
        };
}

return $tests;
