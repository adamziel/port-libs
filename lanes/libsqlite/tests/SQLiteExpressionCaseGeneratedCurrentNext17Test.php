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

$wordpressCaseGenerated = <<<'SQL'
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
    'wordpress table name' => [$wordpressCaseGenerated, 'table', 'wp_options'],
    'wordpress status ok' => [$wordpressCaseGenerated, 'status', 'ok'],
    'wordpress column count' => [$wordpressCaseGenerated, 'columns.count', 8],
    'wordpress first generated name' => [$wordpressCaseGenerated, 'columns.4.name', 'option_name_lower'],
    'wordpress first generated storage' => [$wordpressCaseGenerated, 'columns.4.storage', 'VIRTUAL'],
    'wordpress first generated dependency' => [$wordpressCaseGenerated, 'columns.4.dependencies.0', 'option_name'],
    'simple case generated name' => [$wordpressCaseGenerated, 'columns.5.name', 'autoload_rank'],
    'simple case generated storage' => [$wordpressCaseGenerated, 'columns.5.storage', 'STORED'],
    'simple case depends on base autoload once' => [$wordpressCaseGenerated, 'columns.5.dependencies.0', 'autoload'],
    'simple case dependency count skips literals' => [$wordpressCaseGenerated, 'columns.5.dependencies.count', 1],
    'searched case generated name' => [$wordpressCaseGenerated, 'columns.6.name', 'option_kind'],
    'searched case depends on generated lower' => [$wordpressCaseGenerated, 'columns.6.dependencies.0', 'option_name_lower'],
    'searched case dependency count skips like literals' => [$wordpressCaseGenerated, 'columns.6.dependencies.count', 1],
    'nested case generated name' => [$wordpressCaseGenerated, 'columns.7.name', 'option_route'],
    'nested case dependency first generated' => [$wordpressCaseGenerated, 'columns.7.dependencies.0', 'option_kind'],
    'nested case dependency second generated' => [$wordpressCaseGenerated, 'columns.7.dependencies.1', 'option_name_lower'],
    'nested case dependency third generated' => [$wordpressCaseGenerated, 'columns.7.dependencies.2', 'autoload_rank'],
    'nested case dependency count' => [$wordpressCaseGenerated, 'columns.7.dependencies.count', 3],
    'wordpress evaluation first lower' => [$wordpressCaseGenerated, 'order.0', 'option_name_lower'],
    'wordpress evaluation second rank' => [$wordpressCaseGenerated, 'order.1', 'autoload_rank'],
    'wordpress evaluation third kind' => [$wordpressCaseGenerated, 'order.2', 'option_kind'],
    'wordpress evaluation fourth route' => [$wordpressCaseGenerated, 'order.3', 'option_route'],
    'wordpress cycle empty' => [$wordpressCaseGenerated, 'cycle.count', 0],
    'wordpress message null' => [$wordpressCaseGenerated, 'message', null],
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
    'wordpress generated case unique route autoindex' => [
        $wordpressCaseGenerated,
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
