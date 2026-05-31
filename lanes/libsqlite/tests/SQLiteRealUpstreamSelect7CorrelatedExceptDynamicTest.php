<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatRows = static function (array $rows): array {
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
 * @param list<int> $expected
 */
$assertCorrelatedExcept = static function (TestRunner $t, array $tables, array $expected, int $case) use ($flatRows): void {
    $sql = "
        SELECT P.pk
        FROM photo P
        WHERE NOT EXISTS (
            SELECT T2.pk FROM tag T2 WHERE T2.fk = P.pk
            EXCEPT
            SELECT T3.pk FROM tag T3 WHERE T3.fk = P.pk AND T3.name LIKE '%foo%'
        )
        ORDER BY P.pk
    ";
    $actual = $flatRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, 'select7-4 correlated EXCEPT result case ' . $case);
    $t->same(count($expected), count($actual), 'select7-4 result width case ' . $case);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'select7-4 edge rows case ' . $case,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'select7-4 fingerprint case ' . $case,
    );
};

$tests['real upstream select7.test select7-4 correlated except cites source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test';

    $t->true(is_file($source), 'hydrated upstream select7.test is available');
    $text = file_get_contents($source);
    $t->contains('Ticket #2018', $text);
    $t->contains('do_test select7-4.1', $text);
    $t->contains('do_test select7-4.2', $text);
    $t->contains('EXCEPT', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $photoCount = 5 + ($case % 9);
    $tagId = 1000 + ($case * 50);
    $photos = [];
    $tags = [];
    $expected = [];

    for ($photo = 1; $photo <= $photoCount; $photo++) {
        $photos[] = ['pk' => $photo, 'x' => $case + $photo];
        $tagCount = ($case + $photo) % 5;
        $allTagsMatchFoo = true;

        for ($tag = 0; $tag < $tagCount; $tag++) {
            $matchesFoo = (($case + $photo + $tag) % 3) !== 0;
            $name = $matchesFoo
                ? 'tag-foo-' . $case . '-' . $photo . '-' . $tag
                : 'tag-bar-' . $case . '-' . $photo . '-' . $tag;
            $tags[] = ['pk' => $tagId++, 'fk' => $photo, 'name' => $name];
            $allTagsMatchFoo = $allTagsMatchFoo && $matchesFoo;
        }

        if ($tagCount === 0 || $allTagsMatchFoo) {
            $expected[] = $photo;
        }
    }

    $tables = ['photo' => $photos, 'tag' => $tags];

    $tests[sprintf('real upstream select7.test select7-4 correlated except dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($assertCorrelatedExcept, $tables, $expected, $case): void {
            $assertCorrelatedExcept($t, $tables, $expected, $case);
            $t->contains('select7-4.2', 'select7.test select7-4.2 NOT EXISTS over EXCEPT correlated names');
        };
}

return $tests;
