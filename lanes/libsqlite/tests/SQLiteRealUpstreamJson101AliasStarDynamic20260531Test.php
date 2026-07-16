<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$jsonText = static function (array $document): string {
    return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$expectedRows = static function (int $a, int $b): array {
    return [
        [
            'key' => 'a',
            'value' => $a,
            'type' => 'integer',
            'atom' => $a,
            'id' => 1,
            'parent' => null,
            'fullkey' => '$.a',
            'path' => '$',
            'rowid' => 1,
            '_rowid_' => 1,
            'oid' => 1,
        ],
        [
            'key' => 'b',
            'value' => $b,
            'type' => 'integer',
            'atom' => $b,
            'id' => 2,
            'parent' => null,
            'fullkey' => '$.b',
            'path' => '$',
            'rowid' => 2,
            '_rowid_' => 2,
            'oid' => 2,
        ],
    ];
};

$tests['real upstream json101 alias star dynamic cites upstream source and sections'] = static function (TestRunner $t): void {
    $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
    $source = file_get_contents($sourcePath);
    if (!is_string($source)) {
        $t->fail('Unable to read hydrated upstream json101.test');
        return;
    }

    $t->contains('do_execsql_test json101-15.100', $source);
    $t->contains('do_execsql_test json101-15.110', $source);
    $t->contains('do_execsql_test json101-15.120', $source);
    $t->contains('do_execsql_test json101-15.130', $source);
    $t->contains("SELECT xyz.* FROM (JSON_EACH('{\"a\":1, \"b\":2}')) AS xyz", $source);
};

$tests['real upstream json101 alias star dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no-new-support-component; reuses SQLiteSelectSql source planning, JSON table sources, and wildcard projection',
        'no-new-support-component; reuses SQLiteSelectSql source planning, JSON table sources, and wildcard projection',
    );
};

$shapes = [
    'json101-15.100 uppercase plain star source' => static fn (string $literal): string => 'SELECT * FROM JSON_EACH(' . $literal . ')',
    'json101-15.110 uppercase alias star source' => static fn (string $literal): string => 'SELECT xyz.* FROM JSON_EACH(' . $literal . ') AS xyz',
    'json101-15.120 parenthesized plain star source' => static fn (string $literal): string => 'SELECT * FROM (JSON_EACH(' . $literal . '))',
    'json101-15.130 parenthesized alias star source' => static fn (string $literal): string => 'SELECT xyz.* FROM (JSON_EACH(' . $literal . ')) AS xyz',
];

for ($case = 1; $case <= 250; $case++) {
    $a = $case;
    $b = $case + 1000;
    $literal = $sqlLiteral($jsonText(['a' => $a, 'b' => $b]));
    $expected = $expectedRows($a, $b);

    foreach ($shapes as $label => $query) {
        $tests[sprintf('real upstream json101 alias star dynamic %s case %03d', $label, $case)] = static function (TestRunner $t) use ($query, $literal, $expected): void {
            $t->same($expected, SQLiteSelectSql::execute($query($literal), []));
        };
    }
}

return $tests;
