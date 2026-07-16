<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['name' => 'alpha', 'payload' => '["704-555-0101","919-555-0102"]', 'root' => '$'],
    ['name' => 'broken_text', 'payload' => '{bad', 'root' => '$'],
    ['name' => 'broken_blob', 'payload' => new SQLiteBlobValue('{bad'), 'root' => '$'],
    ['name' => 'broken_subtype', 'payload' => new SQLiteJsonSubtypeValue('{bad'), 'root' => '$'],
    ['name' => 'nested', 'payload' => '{"phones":["704-555-0201"],"meta":{"enabled":true}}', 'root' => '$.phones'],
    ['name' => 'null_payload', 'payload' => null, 'root' => '$'],
];
$scalarRows = [
    ['name' => 'alpha', 'payload' => '["704-555-0101","919-555-0102"]', 'root' => '$'],
    ['name' => 'broken_text', 'payload' => '{bad', 'root' => '$'],
    ['name' => 'nested', 'payload' => '{"phones":["704-555-0201"],"meta":{"enabled":true}}', 'root' => '$.phones'],
    ['name' => 'null_payload', 'payload' => null, 'root' => '$'],
];

$tests = [];

$tests['json_valid guard skips malformed text before json_each dynamic rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, json_each.value AS phone FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND json_each.value LIKE '704-%' ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
    $t->same(['704-555-0101'], array_column($result, 'phone'));
};

$tests['json_error_position guard skips malformed text before json_each dynamic rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE json_error_position(cfg.payload) = 0 AND json_each.value LIKE '704-%' ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['commuted json_valid guard protects json_each source'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE 1 = json_valid(cfg.payload) AND json_each.key = 0 ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['commuted json_error_position guard protects json_each source'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE 0 = json_error_position(cfg.payload) AND json_each.key = 1 ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['json_valid IS guard protects dynamic json table source'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) IS 1 AND json_each.value LIKE '919-%'",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['json_error_position IS NOT DISTINCT FROM guard protects dynamic json table source'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE json_error_position(cfg.payload) IS NOT DISTINCT FROM 0 AND json_each.value LIKE '919-%'",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['json_valid guard skips malformed text blob rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, json_each.value AS phone FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND cfg.name <> 'broken_text' ORDER BY name, phone",
        ['cfg' => $rows],
    );
    $t->same(['alpha', 'alpha', 'nested', 'nested'], array_column($result, 'name'));
    $t->same(['704-555-0101', '919-555-0102', '["704-555-0201"]', '{"enabled":true}'], array_column($result, 'phone'));
};

$tests['json_valid guard skips malformed subtype rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND cfg.name <> 'broken_blob' ORDER BY name, json_each.key",
        ['cfg' => $rows],
    );
    $t->same(['alpha', 'alpha', 'nested', 'nested'], array_column($result, 'name'));
};

$tests['json_error_position guard composes with dynamic root path expression'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, jt.value AS phone FROM cfg, json_tree(cfg.payload, cfg.root) AS jt WHERE json_error_position(cfg.payload) = 0 AND jt.type = 'text' ORDER BY phone",
        ['cfg' => $rows],
    );
    $t->same(['alpha', 'nested', 'alpha'], array_column($result, 'name'));
    $t->same(['704-555-0101', '704-555-0201', '919-555-0102'], array_column($result, 'phone'));
};

$tests['json_valid guard preserves null dynamic json as empty rowset'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND cfg.name = 'null_payload'",
        ['cfg' => $rows],
    );
    $t->same([], $result);
};

$tests['json_error_position projection reports malformed dynamic text boundary'] = static function (TestRunner $t) use ($scalarRows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT name, json_error_position(payload) AS error_pos FROM cfg WHERE name = 'broken_text'",
        ['cfg' => $scalarRows],
    );
    $t->same([['name' => 'broken_text', 'error_pos' => 5]], $result);
};

$tests['json_error_position projection reports malformed text blob boundary'] = static function (TestRunner $t): void {
    $result = SQLiteSelectSql::execute(
        "SELECT name, json_error_position(payload) AS error_pos FROM cfg WHERE name = 'broken_blob'",
        ['cfg' => [['name' => 'broken_blob', 'payload' => new SQLiteBlobValue('{bad')]]],
    );
    $t->same([['name' => 'broken_blob', 'error_pos' => 5]], $result);
};

$tests['json_error_position projection returns zero for valid dynamic json'] = static function (TestRunner $t) use ($scalarRows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT name, json_error_position(payload) AS error_pos FROM cfg WHERE name = 'alpha'",
        ['cfg' => $scalarRows],
    );
    $t->same([['name' => 'alpha', 'error_pos' => 0]], $result);
};

$tests['json_error_position projection returns null for sql null payload'] = static function (TestRunner $t) use ($scalarRows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT name, json_error_position(payload) AS error_pos FROM cfg WHERE name = 'null_payload'",
        ['cfg' => $scalarRows],
    );
    $t->same([['name' => 'null_payload', 'error_pos' => null]], $result);
};

$tests['json_valid false comparison is not treated as an error boundary'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT cfg.name FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 0",
        ['cfg' => $rows],
    ));
};

$tests['json_error_position nonzero comparison is not treated as an error boundary'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT cfg.name FROM cfg, json_each(cfg.payload) WHERE json_error_position(cfg.payload) = 2",
        ['cfg' => $rows],
    ));
};

$tests['unguarded dynamic malformed text still raises'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT cfg.name FROM cfg, json_each(cfg.payload)",
        ['cfg' => $rows],
    ));
};

$tests['guard on unrelated column does not protect json table argument'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT cfg.name FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.name) = 1",
        ['cfg' => $rows],
    ));
};

$tests['guarded dynamic json_tree supports left join null projection for skipped rows'] = static function (TestRunner $t) use ($scalarRows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, jt.value AS phone FROM cfg LEFT JOIN json_each(cfg.payload, '$.phones') AS jt ON jt.value LIKE '704-%' WHERE json_valid(cfg.payload) = 1 ORDER BY name",
        ['cfg' => $scalarRows],
    );
    $t->same(['alpha', 'nested'], array_column($result, 'name'));
    $t->same([null, '704-555-0201'], array_column($result, 'phone'));
};

$tests['guarded dynamic json_each composes with grouping after skipped rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, count(json_each.value) AS phones FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND json_each.type = 'text' GROUP BY cfg.name HAVING count(json_each.value) >= 2 ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same([['name' => 'alpha', 'phones' => 2]], $result);
};

$tests['guarded dynamic json_each composes with distinct after skipped rows'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT DISTINCT substr(json_each.value, 1, 3) AS area FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND json_each.type = 'text' AND json_each.value LIKE '%-%' ORDER BY area",
        ['cfg' => $rows],
    );
    $t->same(['704', '919'], array_column($result, 'area'));
};

$tests['guarded dynamic json_each composes with cte materialization'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "WITH phones AS (SELECT cfg.name AS name, json_each.value AS phone FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1) SELECT name FROM phones WHERE phone LIKE '704-%' ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

$tests['guarded dynamic json_tree preserves order expression execution'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT cfg.name AS name, jt.atom AS phone FROM cfg, json_tree(cfg.payload, cfg.root) AS jt WHERE json_valid(cfg.payload) = 1 AND jt.type = 'text' ORDER BY length(jt.atom) DESC, phone LIMIT 2",
        ['cfg' => $rows],
    );
    $t->same(['alpha', 'nested'], array_column($result, 'name'));
};

$tests['guarded dynamic json_each preserves comma limit execution'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT json_each.value AS phone FROM cfg, json_each(cfg.payload) WHERE json_valid(cfg.payload) = 1 AND json_each.type = 'text' ORDER BY phone LIMIT 1, 2",
        ['cfg' => $rows],
    );
    $t->same(['919-555-0102'], array_column($result, 'phone'));
};

$tests['guarded dynamic json_each supports alias qualified json argument'] = static function (TestRunner $t) use ($rows): void {
    $result = SQLiteSelectSql::execute(
        "SELECT c.name AS name, p.value AS phone FROM cfg AS c, json_each(c.payload) AS p WHERE json_valid(c.payload) = 1 AND p.value LIKE '704-%' ORDER BY name",
        ['cfg' => $rows],
    );
    $t->same(['alpha'], array_column($result, 'name'));
};

return $tests;
