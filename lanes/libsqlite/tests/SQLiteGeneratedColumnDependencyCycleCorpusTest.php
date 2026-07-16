<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteGeneratedColumnDependencyPlan;

$tests = [];

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$applicationAcyclic = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    autoload TEXT DEFAULT 'yes',
    option_name_fold TEXT AS (lower(option_name)) VIRTUAL,
    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
    option_cache_key TEXT AS (option_name_fold || ':' || option_value_len) VIRTUAL,
    option_storage TEXT GENERATED ALWAYS AS (
        CASE WHEN option_value_len > 128 THEN 'large' ELSE 'inline' END
    ) STORED
)
SQL;

$applicationDirect = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    option_name_fold TEXT AS (lower(option_name_fold)) VIRTUAL
)
SQL;

$applicationIndirect = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT NOT NULL,
    option_name_fold TEXT AS (option_cache_key || ':' || option_name) VIRTUAL,
    option_cache_key TEXT AS (option_storage || ':' || option_name_fold) STORED,
    option_storage TEXT AS (option_cache_key || ':' || option_value) VIRTUAL
)
SQL;

$quotedCycle = <<<'SQL'
CREATE TABLE "wp options"(
    "option name" TEXT,
    [cache key] TEXT AS ("option name" || ':' || [cache key]) VIRTUAL,
    "json_extract" TEXT AS (json_extract('{"json_extract":1}', '$.json_extract')) STORED
)
SQL;

foreach ([
    'acyclic table name' => [$applicationAcyclic, 'table', 'wp_options'],
    'acyclic status ok' => [$applicationAcyclic, 'status', 'ok'],
    'acyclic column count' => [$applicationAcyclic, 'columns.count', 8],
    'acyclic generated count first virtual' => [$applicationAcyclic, 'columns.4.generated', true],
    'acyclic generated storage virtual default' => [$applicationAcyclic, 'columns.4.storage', 'VIRTUAL'],
    'acyclic generated storage stored verbose' => [$applicationAcyclic, 'columns.5.storage', 'STORED'],
    'acyclic direct dependency visible base' => [$applicationAcyclic, 'columns.4.dependencies.0', 'option_name'],
    'acyclic length dependency skips function token' => [$applicationAcyclic, 'columns.5.dependencies.0', 'option_value'],
    'acyclic chain dependency first generated' => [$applicationAcyclic, 'columns.6.dependencies.0', 'option_name_fold'],
    'acyclic chain dependency second generated' => [$applicationAcyclic, 'columns.6.dependencies.1', 'option_value_len'],
    'acyclic case dependency generated only' => [$applicationAcyclic, 'columns.7.dependencies.0', 'option_value_len'],
    'acyclic evaluation order first dependency' => [$applicationAcyclic, 'order.0', 'option_name_fold'],
    'acyclic evaluation order second dependency' => [$applicationAcyclic, 'order.1', 'option_value_len'],
    'acyclic evaluation order composed column' => [$applicationAcyclic, 'order.2', 'option_cache_key'],
    'acyclic evaluation order final storage column' => [$applicationAcyclic, 'order.3', 'option_storage'],
    'acyclic message null' => [$applicationAcyclic, 'message', null],
    'acyclic cycle empty' => [$applicationAcyclic, 'cycle.count', 0],
    'direct status error' => [$applicationDirect, 'status', 'error'],
    'direct cycle column' => [$applicationDirect, 'cycle.0', 'option_name_fold'],
    'direct upstream-shaped message' => [$applicationDirect, 'message', 'generated column loop on "option_name_fold"'],
    'direct expression preserved' => [$applicationDirect, 'columns.3.expression', 'lower(option_name_fold)'],
    'indirect status error' => [$applicationIndirect, 'status', 'error'],
    'indirect cycle first column' => [$applicationIndirect, 'cycle.0', 'option_cache_key'],
    'indirect cycle second column' => [$applicationIndirect, 'cycle.1', 'option_storage'],
    'indirect cycle length' => [$applicationIndirect, 'cycle.count', 2],
    'indirect upstream-shaped message chooses newest loop column' => [$applicationIndirect, 'message', 'generated column loop on "option_storage"'],
    'indirect stored dependency preserved' => [$applicationIndirect, 'columns.4.dependencies.0', 'option_storage'],
    'quoted table name unquoted' => [$quotedCycle, 'table', 'wp options'],
    'quoted bracket dependency cycle status' => [$quotedCycle, 'status', 'error'],
    'quoted bracket dependency preserved' => [$quotedCycle, 'columns.1.dependencies.1', 'cache key'],
    'quoted function-like column skips function call' => [$quotedCycle, 'columns.2.dependencies.count', 0],
    'quoted upstream-shaped message' => [$quotedCycle, 'message', 'generated column loop on "cache key"'],
] as $name => [$sql, $path, $expected]) {
    $tests['generated column dependency cycle corpus ' . $name] = static function (TestRunner $t) use ($valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt(SQLiteGeneratedColumnDependencyPlan::analyze($sql), $path));
    };
}

return $tests;
