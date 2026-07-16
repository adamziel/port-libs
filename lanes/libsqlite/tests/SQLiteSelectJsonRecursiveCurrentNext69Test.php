<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 201, 'option_name' => 'wp_nav_seed', 'autoload' => 'yes', 'option_value' => '{"next":[202,203],"rules":[{"slug":"root","priority":30,"enabled":1},{"slug":"landing","priority":18,"enabled":1}]}'],
    ['option_id' => 202, 'option_name' => 'wp_nav_cache', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [204, 205], 'rules' => [['slug' => 'cache', 'priority' => 50, 'enabled' => 1], ['slug' => 'purge', 'priority' => 12, 'enabled' => 0]]]))],
    ['option_id' => 203, 'option_name' => 'wp_nav_media', 'autoload' => 'no', 'option_value' => '{"next":[205],"rules":[{"slug":"gallery","priority":42,"enabled":1},{"slug":"video","priority":9,"enabled":0}]}'],
    ['option_id' => 204, 'option_name' => 'wp_nav_store', 'autoload' => 'yes', 'option_value' => '{"next":[206],"rules":[{"slug":"cart","priority":38,"enabled":1},{"slug":"checkout","priority":35,"enabled":1}]}'],
    ['option_id' => 205, 'option_name' => 'wp_nav_forms', 'autoload' => 'yes', 'option_value' => '{"next":[206],"rules":[{"slug":"contact","priority":28,"enabled":1},{"slug":"captcha","priority":17,"enabled":1}]}'],
    ['option_id' => 206, 'option_name' => 'wp_nav_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[202],"rules":[{"slug":"sync","priority":60,"enabled":1},{"slug":"cleanup","priority":8,"enabled":0}]}'],
    ['option_id' => 207, 'option_name' => 'wp_nav_orphan', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"slug":"orphan","priority":99,"enabled":0}]}'],
];

$tables = ['wp_options' => $options];
$sql = <<<'SQL'
WITH RECURSIVE nav(option_id, depth, parent_name) AS MATERIALIZED (
    VALUES (201, 0, 'seed')
    UNION
    SELECT CAST(edge.atom AS INTEGER), nav.depth + 1, host.option_name
      FROM nav
      JOIN wp_options AS host ON host.option_id = nav.option_id
      JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
     WHERE nav.depth < 4
)
SELECT nav.option_id AS option_id,
       nav.depth AS depth,
       nav.parent_name AS parent_name,
       host.option_name AS option_name,
       field.key AS attr,
       field.atom AS atom,
       field.fullkey AS fullkey
  FROM nav
  JOIN wp_options AS host ON host.option_id = nav.option_id
  JOIN json_tree(host.option_value, '$.rules') AS field ON field.type IN ('text', 'integer', 'true', 'false')
 ORDER BY nav.depth, nav.option_id, field.fullkey
SQL;

$plan = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
$tape = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan(), ['option_id', 'depth'], ['attr', 'atom', 'fullkey']);
$select = static fn (string $selectSql): array => SQLiteSelectSql::execute($selectSql, $tables);

$tests = [
    'select json recursive current next69 exposes yield boundary dependency' => static function (TestRunner $t) use ($plan): void {
        $t->true(in_array('sqlite-recursive-current-next-json-yield-boundary', $plan()['dependencies'], true));
    },
    'select json recursive current next69 materializes reachable json fields' => static function (TestRunner $t) use ($plan): void {
        $t->same(54, count($plan()['rows']));
    },
    'select json recursive current next69 excludes orphan option' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('wp_nav_orphan', array_column($plan()['rows'], 'option_name'), true));
    },
    'select json recursive current next69 builds one tape row per recursive queue row' => static function (TestRunner $t) use ($plan, $tape): void {
        $t->same(count($plan()['recursiveCurrentNext']), count($tape()));
    },
    'select json recursive current next69 first tape key is seed' => static function (TestRunner $t) use ($tape): void {
        $t->same(['option_id' => 201, 'depth' => 0], $tape()[0]['currentKey']);
    },
    'select json recursive current next69 first tape next key is cache' => static function (TestRunner $t) use ($tape): void {
        $t->same(['option_id' => 202, 'depth' => 1], $tape()[0]['nextKey']);
    },
    'select json recursive current next69 first tape projects current json fields' => static function (TestRunner $t) use ($tape): void {
        $t->same([1, 30, 'root', 1, 18, 'landing'], array_column($tape()[0]['currentJson'], 'atom'));
    },
    'select json recursive current next69 first tape projects next jsonb fields' => static function (TestRunner $t) use ($tape): void {
        $t->same([1, 50, 'cache', 0, 12, 'purge'], array_column($tape()[0]['nextJson'], 'atom'));
    },
    'select json recursive current next69 terminal row has null next key' => static function (TestRunner $t) use ($tape): void {
        $last = $tape()[count($tape()) - 1];
        $t->same(null, $last['nextKey']);
    },
    'select json recursive current next69 terminal row records duplicate cycle' => static function (TestRunner $t) use ($tape): void {
        $last = $tape()[count($tape()) - 1];
        $t->same('terminal', $last['transition']);
    },
    'select json recursive current next69 trace records skipped duplicate cycle' => static function (TestRunner $t) use ($plan): void {
        $skipped = $plan()['trace']['skipped'];
        $t->same('union-duplicate-cycle', $skipped[0]['reason']);
        $t->same(['option_id' => 206, 'depth' => 3, 'parent_name' => 'wp_nav_forms'], $skipped[0]['row']);
    },
    'select json recursive current next69 accepted next keys preserve first frontier' => static function (TestRunner $t) use ($tape): void {
        $t->same([['option_id' => 202, 'depth' => 1], ['option_id' => 203, 'depth' => 1]], $tape()[0]['acceptedNextKeys']);
    },
    'select json recursive current next69 marks regular yield transitions' => static function (TestRunner $t) use ($tape): void {
        $t->same('yield', $tape()[1]['transition']);
    },
    'select json recursive current next69 keeps queue after count visible' => static function (TestRunner $t) use ($tape): void {
        $t->true($tape()[0]['queueAfterCount'] >= 2);
    },
    'select json recursive current next69 records generated count for branching row' => static function (TestRunner $t) use ($tape): void {
        $t->same(2, $tape()[1]['generatedCount']);
    },
    'select json recursive current next69 records accepted count for branching row' => static function (TestRunner $t) use ($tape): void {
        $t->same(2, $tape()[1]['acceptedNextCount']);
    },
    'select json recursive current next69 derived select filters high priorities' => static function (TestRunner $t) use ($select, $sql): void {
        $rows = $select("SELECT option_name FROM ({$sql}) AS j WHERE attr = 'priority' AND atom >= 38 ORDER BY atom DESC, option_name");
        $t->same(['wp_nav_leaf', 'wp_nav_leaf', 'wp_nav_cache', 'wp_nav_cache', 'wp_nav_media', 'wp_nav_store'], array_column($rows, 'option_name'));
    },
    'select json recursive current next69 derived select groups attributes' => static function (TestRunner $t) use ($select, $sql): void {
        $rows = $select("SELECT attr, count(atom) AS total FROM ({$sql}) AS j GROUP BY attr ORDER BY attr");
        $t->same([18, 18, 18], array_column($rows, 'total'));
    },
    'select json recursive current next69 rejects empty key columns' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan(), [], ['atom']));
    },
    'select json recursive current next69 rejects empty json columns' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan(), ['option_id'], []));
    },
    'select json recursive current next69 rejects missing key column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan(), ['missing'], ['atom']));
    },
    'select json recursive current next69 rejects missing json column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan(), ['option_id'], ['missing']));
    },
];

foreach ([201 => 6, 202 => 12, 203 => 6, 204 => 6, 205 => 12, 206 => 12] as $optionId => $rowCount) {
    $tests["select json recursive current next69 option {$optionId} materialized count"] = static function (TestRunner $t) use ($plan, $optionId, $rowCount): void {
        $rows = array_values(array_filter($plan()['rows'], static fn (array $row): bool => $row['option_id'] === $optionId));
        $t->same($rowCount, count($rows));
    };
    $tests["select json recursive current next69 option {$optionId} slug lookup count"] = static function (TestRunner $t) use ($plan, $optionId, $rowCount): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'slug']);
        $t->same((int) ($rowCount / 3), count($pairs));
    };
    $tests["select json recursive current next69 option {$optionId} priority lookup count"] = static function (TestRunner $t) use ($plan, $optionId, $rowCount): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'priority']);
        $t->same((int) ($rowCount / 3), count($pairs));
    };
    $tests["select json recursive current next69 option {$optionId} enabled lookup count"] = static function (TestRunner $t) use ($plan, $optionId, $rowCount): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'enabled']);
        $t->same((int) ($rowCount / 3), count($pairs));
    };
}

foreach (range(0, 8) as $position) {
    $tests["select json recursive current next69 tape position {$position} keeps iteration"] = static function (TestRunner $t) use ($tape, $position): void {
        $t->same($position, $tape()[$position]['iteration']);
    };
    $tests["select json recursive current next69 tape position {$position} has six current json cells"] = static function (TestRunner $t) use ($tape, $position): void {
        $t->same(6, count($tape()[$position]['currentJson']));
    };
}

foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $limit) {
    $tests["select json recursive current next69 derived source limit {$limit}"] = static function (TestRunner $t) use ($select, $sql, $limit): void {
        $rows = $select("SELECT atom FROM ({$sql}) AS j WHERE attr = 'slug' ORDER BY atom LIMIT {$limit}");
        $t->same($limit, count($rows));
    };
}

return $tests;
