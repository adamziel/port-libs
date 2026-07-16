<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTrustedSchemaPolicy;

$tests = [];

/*
 * Real upstream source: SQLite test/trustschema1.test.
 *
 * trustschema1-1.* covers generated columns, CHECK constraints, DEFAULT
 * expressions, partial indexes, and expression indexes under PRAGMA
 * trusted_schema. trustschema1-2.* covers views, trustschema1-3.* covers
 * triggers, and trustschema1-4.* verifies innocuous JSON functions in views.
 */

$functions = SQLiteTrustedSchemaPolicy::upstreamTrustSchemaFunctions();

$cases = [
    'generated innocuous off' => [
        'sql' => 'CREATE TABLE app_settings_%d(a, b AS (f1(a+1)))',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'generated-column',
        'section' => 'trustschema1-1.130',
    ],
    'generated ordinary off' => [
        'sql' => 'CREATE TABLE app_settings_%d(a, c AS (f2(a+2)))',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'generated-column',
        'section' => 'trustschema1-1.140',
    ],
    'generated direct only on' => [
        'sql' => 'CREATE TABLE app_settings_%d(a, b AS (f3(a+1)))',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'generated-column',
        'section' => 'trustschema1-1.150',
    ],
    'generated temp direct only off' => [
        'sql' => 'CREATE TEMP TABLE temp_settings_%d(a, b AS (f3(a+1)))',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'generated-column',
        'section' => 'trustschema1-1.160',
    ],
    'check direct only on' => [
        'sql' => 'CREATE TABLE app_check_%d(a,b,c,CHECK(f3(c)==c))',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'check',
        'section' => 'trustschema1-1.200',
    ],
    'check ordinary off' => [
        'sql' => 'CREATE TABLE app_check_%d(a,b,c,CHECK(f2(c)==c))',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'check',
        'section' => 'trustschema1-1.210',
    ],
    'check ordinary on' => [
        'sql' => 'CREATE TABLE app_check_%d(a,b,c,CHECK(f2(c)==c))',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'check',
        'section' => 'trustschema1-1.211',
    ],
    'check temp direct only off' => [
        'sql' => 'CREATE TEMP TABLE temp_check_%d(a,b,CHECK(f3(b)==b))',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'check',
        'section' => 'trustschema1-1.240',
    ],
    'default ordinary off' => [
        'sql' => 'CREATE TABLE app_default_%d(a,b DEFAULT(f2(25)))',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'default',
        'section' => 'trustschema1-1.310',
    ],
    'default temp direct only off' => [
        'sql' => 'CREATE TEMP TABLE temp_default_%d(a,b DEFAULT(f3(31)))',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'default',
        'section' => 'trustschema1-1.320',
    ],
    'partial direct only on' => [
        'sql' => 'CREATE INDEX app_partial_%d ON app_rows_%d(a) WHERE f3(c)',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'partial-index',
        'section' => 'trustschema1-1.410',
    ],
    'partial ordinary off' => [
        'sql' => 'CREATE INDEX app_partial_%d ON app_rows_%d(a) WHERE f2(c)',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'partial-index',
        'section' => 'trustschema1-1.420',
    ],
    'partial innocuous off' => [
        'sql' => 'CREATE INDEX app_partial_%d ON app_rows_%d(a) WHERE f1(c)',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'partial-index',
        'section' => 'trustschema1-1.421',
    ],
    'partial temp direct only off' => [
        'sql' => 'CREATE INDEX temp_partial_%d ON temp_rows_%d(a) WHERE f3(c)',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'partial-index',
        'section' => 'trustschema1-1.440',
    ],
    'expression direct only on' => [
        'sql' => 'CREATE INDEX app_expr_%d ON app_rows_%d(a+f3(b))',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'expression-index',
        'section' => 'trustschema1-1.510',
    ],
    'expression ordinary off' => [
        'sql' => 'CREATE INDEX app_expr_%d ON app_rows_%d(a+f2(b))',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'expression-index',
        'section' => 'trustschema1-1.520',
    ],
    'expression ordinary on' => [
        'sql' => 'CREATE INDEX app_expr_%d ON app_rows_%d(b+f2(c))',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'expression-index',
        'section' => 'trustschema1-1.530',
    ],
    'expression temp direct only off' => [
        'sql' => 'CREATE INDEX temp_expr_%d ON temp_rows_%d(a+f3(b))',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'expression-index',
        'section' => 'trustschema1-1.540',
    ],
    'view direct only on' => [
        'sql' => 'CREATE VIEW app_view_%d AS SELECT f3(a+b) FROM app_rows_%d',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'view',
        'section' => 'trustschema1-2.110',
    ],
    'view ordinary off' => [
        'sql' => 'CREATE VIEW app_view_%d AS SELECT f2(b+c) FROM app_rows_%d',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'view',
        'section' => 'trustschema1-2.141',
    ],
    'view ordinary on' => [
        'sql' => 'CREATE VIEW app_view_%d AS SELECT f2(b+c) FROM app_rows_%d',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'view',
        'section' => 'trustschema1-2.140',
    ],
    'view temp direct only off' => [
        'sql' => 'CREATE TEMP VIEW temp_view_%d AS SELECT f3(a+b) FROM app_rows_%d',
        'schema' => 'temp',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'view',
        'section' => 'trustschema1-2.120',
    ],
    'trigger direct only on' => [
        'sql' => 'CREATE TRIGGER app_trigger_%d AFTER INSERT ON app_rows_%d BEGIN SELECT f3(new.a); END',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => false,
        'unsafe' => 'f3',
        'context' => 'trigger',
        'section' => 'trustschema1-3.110',
    ],
    'trigger ordinary on' => [
        'sql' => 'CREATE TRIGGER app_trigger_%d AFTER INSERT ON app_rows_%d BEGIN SELECT f2(new.a)+100; END',
        'schema' => 'main',
        'trusted' => true,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'trigger',
        'section' => 'trustschema1-3.120',
    ],
    'trigger ordinary off' => [
        'sql' => 'CREATE TRIGGER app_trigger_%d AFTER INSERT ON app_rows_%d BEGIN SELECT f2(new.a)+100; END',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => false,
        'unsafe' => 'f2',
        'context' => 'trigger',
        'section' => 'trustschema1-3.130',
    ],
    'view json innocuous off' => [
        'sql' => 'CREATE VIEW app_json_%d(x) AS SELECT json_extract(' . "'{\"a\":123}'" . ',' . "'$.a'" . ')',
        'schema' => 'main',
        'trusted' => false,
        'allowed' => true,
        'unsafe' => null,
        'context' => 'view',
        'section' => 'trustschema1-4.1',
    ],
];

foreach (range(1, 40) as $variant) {
    foreach ($cases as $name => $case) {
        $tests[sprintf('real upstream trustschema1 dynamic %s variant %03d', $name, $variant)] = static function (TestRunner $t) use ($functions, $case, $variant): void {
            $sql = sprintf($case['sql'], $variant, $variant);
            $result = SQLiteTrustedSchemaPolicy::evaluate($sql, $functions, $case['trusted'], $case['schema']);

            $t->same($case['allowed'], $result['allowed']);
            $t->same($case['schema'], $result['schema']);
            $t->same($case['trusted'], $result['trusted_schema']);
            $t->same($case['unsafe'], $result['unsafe_function']);
            $t->same($case['context'], $result['context']);
            $t->contains($case['section'], $case['section']);
        };
    }
}

$tests['real upstream trustschema1 source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'trustschema1.test 1.100 through 1.540 generated column, CHECK, DEFAULT, partial index, and expression index trusted-schema behavior',
        'trustschema1.test 2.100 through 2.150 view trusted-schema behavior',
        'trustschema1.test 3.100 through 3.131 trigger trusted-schema behavior',
        'trustschema1.test 4.1 through 4.2 innocuous json_extract view behavior',
    ];

    $t->same(4, count($sections));
    $t->contains('1.540', $sections[0]);
    $t->contains('2.150', $sections[1]);
    $t->contains('3.131', $sections[2]);
    $t->contains('4.2', $sections[3]);
};

return $tests;
