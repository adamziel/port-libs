<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
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

$applicationCaseGenerated = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT,
    autoload TEXT DEFAULT 'yes',
    option_name_lower TEXT AS (
        lower(option_name)
    ) VIRTUAL,
    autoload_rank INTEGER GENERATED ALWAYS AS (
        CASE autoload
            WHEN 'yes' THEN 1
            WHEN 'no' THEN 2
            ELSE 9
        END
    ) STORED,
    option_kind TEXT AS (
        CASE
            WHEN option_name_lower LIKE '_transient_%' THEN 'transient'
            WHEN option_name_lower LIKE 'theme_%' THEN 'theme'
            ELSE 'option'
        END
    ) VIRTUAL,
    option_route TEXT GENERATED ALWAYS AS (
        CASE option_kind
            WHEN 'transient' THEN option_kind || ':' || option_name_lower
            ELSE option_kind || ':' || autoload_rank
        END
    ) STORED,
    UNIQUE(option_route),
    CHECK(option_kind <> 'forbidden')
)
SQL;

$quotedCaseGenerated = <<<'SQL'
CREATE TABLE "wp option meta"(
    "option name" TEXT,
    [case] TEXT,
    "When" TEXT,
    "label" TEXT AS (
        CASE [case]
            WHEN 'autoload' THEN "option name"
            WHEN 'literal option name' THEN 'option name'
            ELSE "When"
        END
    ) VIRTUAL,
    "route" TEXT GENERATED ALWAYS AS (
        CASE
            WHEN "label" IS NULL THEN [case]
            ELSE "label" || ':' || [case]
        END
    ) STORED UNIQUE
)
SQL;

$directCaseLoop = <<<'SQL'
CREATE TABLE wp_options(
    option_name TEXT,
    option_state TEXT AS (
        CASE WHEN option_state IS NULL THEN option_name ELSE option_state END
    ) VIRTUAL
)
SQL;

$indirectCaseLoop = <<<'SQL'
CREATE TABLE wp_options(
    option_name TEXT,
    option_flag TEXT AS (
        CASE WHEN option_route = 'autoload' THEN option_name ELSE 'manual' END
    ) VIRTUAL,
    option_route TEXT AS (
        CASE option_flag WHEN 'manual' THEN option_flag ELSE option_name END
    ) STORED
)
SQL;

$caseLiteralNoise = <<<'SQL'
CREATE TABLE wp_options(
    option_name TEXT,
    option_value TEXT,
    autoload TEXT,
    option_note TEXT AS (
        CASE
            WHEN option_value = 'option_name autoload CASE WHEN THEN ELSE END'
            THEN 'autoload'
            ELSE printf('option_value:%s', option_name)
        END
    ) VIRTUAL
)
SQL;

foreach ([
    'application table name' => [$applicationCaseGenerated, 'table', 'wp_options'],
    'application status ok' => [$applicationCaseGenerated, 'status', 'ok'],
    'application column count' => [$applicationCaseGenerated, 'columns.count', 8],
    'application first generated name' => [$applicationCaseGenerated, 'columns.4.name', 'option_name_lower'],
    'application first generated storage' => [$applicationCaseGenerated, 'columns.4.storage', 'VIRTUAL'],
    'application first generated dependency' => [$applicationCaseGenerated, 'columns.4.dependencies.0', 'option_name'],
    'simple case generated name' => [$applicationCaseGenerated, 'columns.5.name', 'autoload_rank'],
    'simple case generated storage' => [$applicationCaseGenerated, 'columns.5.storage', 'STORED'],
    'simple case depends on base autoload once' => [$applicationCaseGenerated, 'columns.5.dependencies.0', 'autoload'],
    'simple case dependency count skips literals' => [$applicationCaseGenerated, 'columns.5.dependencies.count', 1],
    'searched case generated name' => [$applicationCaseGenerated, 'columns.6.name', 'option_kind'],
    'searched case depends on generated lower' => [$applicationCaseGenerated, 'columns.6.dependencies.0', 'option_name_lower'],
    'searched case dependency count skips like literals' => [$applicationCaseGenerated, 'columns.6.dependencies.count', 1],
    'nested case generated name' => [$applicationCaseGenerated, 'columns.7.name', 'option_route'],
    'nested case dependency first generated' => [$applicationCaseGenerated, 'columns.7.dependencies.0', 'option_kind'],
    'nested case dependency second generated' => [$applicationCaseGenerated, 'columns.7.dependencies.1', 'option_name_lower'],
    'nested case dependency third generated' => [$applicationCaseGenerated, 'columns.7.dependencies.2', 'autoload_rank'],
    'nested case dependency count' => [$applicationCaseGenerated, 'columns.7.dependencies.count', 3],
    'application evaluation first lower' => [$applicationCaseGenerated, 'order.0', 'option_name_lower'],
    'application evaluation second rank' => [$applicationCaseGenerated, 'order.1', 'autoload_rank'],
    'application evaluation third kind' => [$applicationCaseGenerated, 'order.2', 'option_kind'],
    'application evaluation fourth route' => [$applicationCaseGenerated, 'order.3', 'option_route'],
    'application cycle empty' => [$applicationCaseGenerated, 'cycle.count', 0],
    'application message null' => [$applicationCaseGenerated, 'message', null],
    'quoted table name preserved' => [$quotedCaseGenerated, 'table', 'wp option meta'],
    'quoted status ok' => [$quotedCaseGenerated, 'status', 'ok'],
    'quoted column named keyword case' => [$quotedCaseGenerated, 'columns.1.name', 'case'],
    'quoted column named keyword when' => [$quotedCaseGenerated, 'columns.2.name', 'When'],
    'quoted label generated' => [$quotedCaseGenerated, 'columns.3.generated', true],
    'quoted label depends on bracket case' => [$quotedCaseGenerated, 'columns.3.dependencies.0', 'case'],
    'quoted label depends on option name' => [$quotedCaseGenerated, 'columns.3.dependencies.1', 'option name'],
    'quoted label depends on When identifier' => [$quotedCaseGenerated, 'columns.3.dependencies.2', 'When'],
    'quoted label skips single-quoted column text' => [$quotedCaseGenerated, 'columns.3.dependencies.count', 3],
    'quoted route generated stored' => [$quotedCaseGenerated, 'columns.4.storage', 'STORED'],
    'quoted route depends on label' => [$quotedCaseGenerated, 'columns.4.dependencies.0', 'label'],
    'quoted route depends on case' => [$quotedCaseGenerated, 'columns.4.dependencies.1', 'case'],
    'quoted route dependency count' => [$quotedCaseGenerated, 'columns.4.dependencies.count', 2],
    'quoted evaluation first label' => [$quotedCaseGenerated, 'order.0', 'label'],
    'quoted evaluation second route' => [$quotedCaseGenerated, 'order.1', 'route'],
    'quoted cycle empty' => [$quotedCaseGenerated, 'cycle.count', 0],
    'direct loop status error' => [$directCaseLoop, 'status', 'error'],
    'direct loop column' => [$directCaseLoop, 'cycle.0', 'option_state'],
    'direct loop message' => [$directCaseLoop, 'message', 'generated column loop on "option_state"'],
    'direct loop expression preserved' => [$directCaseLoop, 'columns.1.expression', "CASE WHEN option_state IS NULL THEN option_name ELSE option_state END"],
    'indirect loop status error' => [$indirectCaseLoop, 'status', 'error'],
    'indirect loop first column' => [$indirectCaseLoop, 'cycle.0', 'option_flag'],
    'indirect loop second column' => [$indirectCaseLoop, 'cycle.1', 'option_route'],
    'indirect loop message' => [$indirectCaseLoop, 'message', 'generated column loop on "option_route"'],
    'literal noise status ok' => [$caseLiteralNoise, 'status', 'ok'],
    'literal noise generated dependency first' => [$caseLiteralNoise, 'columns.3.dependencies.0', 'option_value'],
    'literal noise generated dependency second' => [$caseLiteralNoise, 'columns.3.dependencies.1', 'option_name'],
    'literal noise dependency count skips string keyword text' => [$caseLiteralNoise, 'columns.3.dependencies.count', 2],
] as $name => [$sql, $path, $expected]) {
    $tests['expression case generated current next17 dependency ' . $name] = static function (TestRunner $t) use ($valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt(SQLiteGeneratedColumnDependencyPlan::analyze($sql), $path));
    };
}

foreach ([
    'application generated case unique route autoindex' => [
        $applicationCaseGenerated,
        [['option_route']],
    ],
    'quoted generated case unique route autoindex' => [
        $quotedCaseGenerated,
        [['route']],
    ],
    'case expression unique text ignored outside column constraint' => [
        "CREATE TABLE wp_options(option_name TEXT, option_kind TEXT AS (CASE WHEN option_name = 'UNIQUE(option_name)' THEN 'u' ELSE 'n' END) VIRTUAL, autoload TEXT UNIQUE)",
        [['autoload']],
    ],
    'stored generated column primary key text ignored' => [
        "CREATE TABLE wp_options(option_name TEXT, option_route TEXT GENERATED ALWAYS AS (CASE option_name WHEN 'PRIMARY KEY' THEN option_name ELSE 'x' END) STORED UNIQUE)",
        [['option_route']],
    ],
] as $name => [$sql, $expected]) {
    $tests['expression case generated current next17 autoindex ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $actual = array_map(
            static fn (array $columns): array => array_map(static fn ($column): string => $column->columnName, $columns),
            SQLiteCreateTable::automaticIndexColumnMetadata($sql),
        );

        $t->same($expected, $actual);
    };
}

return $tests;
