<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 10, 'option_name' => 'plugin_seed_a', 'autoload' => 'yes', 'option_value' => '{"next":[20,30],"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}'],
    ['option_id' => 20, 'option_name' => 'plugin_seed_b', 'autoload' => 'yes', 'option_value' => '{"next":[40],"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":false}]}'],
    ['option_id' => 30, 'option_name' => 'plugin_seed_c', 'autoload' => 'no', 'option_value' => '{"next":[40],"rules":[{"name":"gallery","priority":5,"enabled":true},{"name":"seo","priority":3,"enabled":true}]}'],
    ['option_id' => 40, 'option_name' => 'plugin_seed_d', 'autoload' => 'no', 'option_value' => '{"next":[50],"rules":[{"name":"cache","priority":9,"enabled":true},{"name":"forms","priority":6,"enabled":false}]}'],
    ['option_id' => 50, 'option_name' => 'plugin_seed_e', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"media","priority":8,"enabled":true},{"name":"seo","priority":10,"enabled":false}]}'],
    ['option_id' => 60, 'option_name' => 'plugin_unused', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"unused","priority":99,"enabled":false}]}'],
];

$tables = ['wp_options' => $options];
$sql = "WITH RECURSIVE wanted(option_id) AS MATERIALIZED (
            VALUES (10), (20)
            UNION
            SELECT CAST(next.atom AS INTEGER)
              FROM wanted
              JOIN wp_options AS o ON o.option_id = wanted.option_id
              JOIN json_each(o.option_value, '$.next') AS next ON next.type = 'integer'
        )
        SELECT wanted.option_id AS option_id,
               o.option_name AS option_name,
               o.autoload AS autoload,
               jt.key AS attr,
               jt.atom AS atom,
               jt.type AS json_type,
               jt.fullkey AS fullkey
          FROM wanted
          JOIN wp_options AS o ON o.option_id = wanted.option_id
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer', 'true', 'false')
         ORDER BY wanted.option_id, jt.fullkey";

$plan = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
$recursive = static fn (): array => $plan()['recursiveCurrentNext'];
$rowIds = static fn (array $rows): array => array_column($rows, 'option_id');
$names = static fn (array $rows): array => array_column($rows, 'option_name');
$atoms = static fn (array $rows): array => array_column($rows, 'atom');
$select = static fn (string $selectSql, string $column): array => array_column(SQLiteSelectSql::execute($selectSql, $tables), $column);

$tests = [
    'materialized recursive json current next51 records boundary dependency' => static function (TestRunner $t) use ($plan): void {
        $t->true(in_array('sqlite-recursive-current-next-json-yield-boundary', $plan()['dependencies'], true));
    },
    'materialized recursive json current next51 keeps union recursive operator' => static function (TestRunner $t) use ($plan): void {
        $t->same('UNION', $plan()['trace']['operator']);
    },
    'materialized recursive json current next51 keeps two anchor rows first' => static function (TestRunner $t) use ($plan): void {
        $t->same([10, 20], array_slice(array_column($plan()['trace']['rows'], 'option_id'), 0, 2));
    },
    'materialized recursive json current next51 dedupes duplicate recursive target' => static function (TestRunner $t) use ($plan): void {
        $t->same([10, 20, 30, 40, 50], array_column($plan()['trace']['rows'], 'option_id'));
    },
    'materialized recursive json current next51 records skipped duplicate rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, count($plan()['trace']['skipped']));
    },
    'materialized recursive json current next51 first skipped duplicate is page twenty' => static function (TestRunner $t) use ($plan): void {
        $t->same(20, $plan()['trace']['skipped'][0]['row']['option_id']);
    },
    'materialized recursive json current next51 second skipped duplicate is page forty' => static function (TestRunner $t) use ($plan): void {
        $t->same(40, $plan()['trace']['skipped'][1]['row']['option_id']);
    },
    'materialized recursive json current next51 has one boundary per recursive row' => static function (TestRunner $t) use ($recursive): void {
        $t->same(5, count($recursive()));
    },
    'materialized recursive json current next51 first current row is first anchor' => static function (TestRunner $t) use ($recursive): void {
        $t->same(10, $recursive()[0]['current']['option_id']);
    },
    'materialized recursive json current next51 first next row is second anchor' => static function (TestRunner $t) use ($recursive): void {
        $t->same(20, $recursive()[0]['next']['option_id']);
    },
    'materialized recursive json current next51 second next row is first generated child' => static function (TestRunner $t) use ($recursive): void {
        $t->same(30, $recursive()[1]['next']['option_id']);
    },
    'materialized recursive json current next51 terminal next is null' => static function (TestRunner $t) use ($recursive): void {
        $t->same(null, $recursive()[4]['next']);
    },
    'materialized recursive json current next51 current positions are stable' => static function (TestRunner $t) use ($recursive): void {
        $t->same([0, 1, 2, 3, 4], array_column($recursive(), 'currentPosition'));
    },
    'materialized recursive json current next51 next positions end with null' => static function (TestRunner $t) use ($recursive): void {
        $t->same([1, 2, 3, 4, null], array_column($recursive(), 'nextPosition'));
    },
    'materialized recursive json current next51 current json row counts stay per source row' => static function (TestRunner $t) use ($recursive): void {
        $t->same([6, 6, 6, 6, 6], array_map(static fn (array $pair): int => count($pair['currentJsonRows']), $recursive()));
    },
    'materialized recursive json current next51 next json row counts end empty' => static function (TestRunner $t) use ($recursive): void {
        $t->same([6, 6, 6, 6, 0], array_map(static fn (array $pair): int => count($pair['nextJsonRows']), $recursive()));
    },
    'materialized recursive json current next51 first current json belongs to seed a' => static function (TestRunner $t) use ($recursive, $names): void {
        $t->same(['plugin_seed_a'], array_values(array_unique($names($recursive()[0]['currentJsonRows']))));
    },
    'materialized recursive json current next51 first next json belongs to seed b' => static function (TestRunner $t) use ($recursive, $names): void {
        $t->same(['plugin_seed_b'], array_values(array_unique($names($recursive()[0]['nextJsonRows']))));
    },
    'materialized recursive json current next51 duplicate target keeps first accepted path' => static function (TestRunner $t) use ($recursive): void {
        $t->same(40, $recursive()[3]['current']['option_id']);
    },
    'materialized recursive json current next51 accepted next rows are attached to current boundary' => static function (TestRunner $t) use ($recursive, $rowIds): void {
        $t->same([30], $rowIds($recursive()[0]['acceptedNext']));
    },
    'materialized recursive json current next51 duplicate boundary records skipped duplicate' => static function (TestRunner $t) use ($recursive, $rowIds): void {
        $t->same([40], $rowIds($recursive()[2]['skippedDuplicates']));
    },
    'materialized recursive json current next51 terminal accepted next is empty' => static function (TestRunner $t) use ($recursive): void {
        $t->same([], $recursive()[4]['acceptedNext']);
    },
    'materialized recursive json current next51 terminal skipped duplicate is empty' => static function (TestRunner $t) use ($recursive): void {
        $t->same([], $recursive()[4]['skippedDuplicates']);
    },
    'materialized recursive json current next51 materialized rows omit unused option' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('plugin_unused', array_column($plan()['rows'], 'option_name'), true));
    },
    'materialized recursive json current next51 materialized row count follows five sources' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, count($plan()['rows']));
    },
    'materialized recursive json current next51 composite index has fifteen keys' => static function (TestRunner $t) use ($plan): void {
        $t->same(15, count($plan()['indexes']));
    },
    'materialized recursive json current next51 current next covers materialized rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(30, count($plan()['currentNext']));
    },
    'materialized recursive json current next51 lookup seed d priority uses row local json' => static function (TestRunner $t) use ($plan): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => 40, 'attr' => 'priority']);
        $t->same([9, 6], [$pairs[0]['current']['atom'], $pairs[0]['next']['atom']]);
    },
    'materialized recursive json current next51 lookup seed e enabled terminates' => static function (TestRunner $t) use ($plan): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => 50, 'attr' => 'enabled']);
        $t->same(null, $pairs[1]['next']);
    },
    'materialized recursive json current next51 derived select can read current boundary rows' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_id FROM ({$sql}) AS r WHERE attr = 'name' ORDER BY option_id, fullkey LIMIT 5", $tables);
        $t->same([10, 10, 20, 20, 30], array_column($rows, 'option_id'));
    },
    'materialized recursive json current next51 derived select preserves terminal option order' => static function (TestRunner $t) use ($select, $sql): void {
        $t->same(['media', 'seo'], $select("SELECT atom FROM ({$sql}) AS r WHERE option_id = 50 AND attr = 'name' ORDER BY fullkey", 'atom'));
    },
    'materialized recursive json current next51 derived select filters generated options' => static function (TestRunner $t) use ($select, $sql): void {
        $t->same(['plugin_seed_c', 'plugin_seed_d'], $select("SELECT DISTINCT option_name FROM ({$sql}) AS r WHERE option_id IN (30, 40) ORDER BY option_name", 'option_name'));
    },
    'materialized recursive json current next51 derived select can group by autoload' => static function (TestRunner $t) use ($select, $sql): void {
        $t->same([12, 18], $select("SELECT autoload, count(atom) AS total FROM ({$sql}) AS r GROUP BY autoload ORDER BY autoload", 'total'));
    },
    'materialized recursive json current next51 derived select can filter json atom priority' => static function (TestRunner $t) use ($select, $sql): void {
        $t->same(['plugin_seed_e', 'plugin_seed_d', 'plugin_seed_e'], $select("SELECT option_name FROM ({$sql}) AS r WHERE attr = 'priority' AND atom >= 8 ORDER BY atom", 'option_name'));
    },
    'materialized recursive json current next51 rejects missing recursive json lookup key' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => 10]));
    },
];

foreach ([0 => 10, 1 => 20, 2 => 30, 3 => 40, 4 => 50] as $position => $optionId) {
    $tests['materialized recursive json current next51 boundary current id ' . $position] = static function (TestRunner $t) use ($recursive, $position, $optionId): void {
        $t->same($optionId, $recursive()[$position]['current']['option_id']);
    };
    $tests['materialized recursive json current next51 boundary json option id ' . $position] = static function (TestRunner $t) use ($recursive, $position, $optionId): void {
        $t->same([$optionId], array_values(array_unique(array_column($recursive()[$position]['currentJsonRows'], 'option_id'))));
    };
    $tests['materialized recursive json current next51 boundary emitted ' . $position] = static function (TestRunner $t) use ($recursive, $position): void {
        $t->same(true, $recursive()[$position]['emitted']);
    };
}

foreach ([10 => ['seo', 'cache'], 20 => ['forms', 'media'], 30 => ['gallery', 'seo'], 40 => ['cache', 'forms'], 50 => ['media', 'seo']] as $optionId => $expectedNames) {
    $tests['materialized recursive json current next51 name current next for ' . $optionId] = static function (TestRunner $t) use ($plan, $optionId, $expectedNames): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'name']);
        $t->same($expectedNames, array_column(array_map(static fn (array $pair): array => $pair['current'], $pairs), 'atom'));
    };
    $tests['materialized recursive json current next51 priority pair count for ' . $optionId] = static function (TestRunner $t) use ($plan, $optionId): void {
        $t->same(2, count(SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'priority'])));
    };
}

return $tests;
