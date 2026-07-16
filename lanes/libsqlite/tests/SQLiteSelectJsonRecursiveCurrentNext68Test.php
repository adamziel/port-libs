<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'menu_root', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"items":[{"label":"home","weight":1,"enabled":1},{"label":"docs","weight":4,"enabled":1}]}'],
    ['option_id' => 2, 'option_name' => 'menu_docs', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [4, 5], 'items' => [['label' => 'api', 'weight' => 9, 'enabled' => 1], ['label' => 'guides', 'weight' => 6, 'enabled' => 1]]]))],
    ['option_id' => 3, 'option_name' => 'menu_blog', 'autoload' => 'no', 'option_value' => '{"next":[5,6],"items":[{"label":"news","weight":5,"enabled":1},{"label":"events","weight":2,"enabled":0}]}'],
    ['option_id' => 4, 'option_name' => 'menu_api', 'autoload' => 'yes', 'option_value' => '{"next":[7],"items":[{"label":"rest","weight":8,"enabled":1},{"label":"cli","weight":3,"enabled":1}]}'],
    ['option_id' => 5, 'option_name' => 'menu_search', 'autoload' => 'yes', 'option_value' => '{"next":[7],"items":[{"label":"search","weight":10,"enabled":1},{"label":"filters","weight":7,"enabled":1}]}'],
    ['option_id' => 6, 'option_name' => 'menu_theme', 'autoload' => 'no', 'option_value' => '{"next":[],"items":[{"label":"colors","weight":2,"enabled":1},{"label":"fonts","weight":1,"enabled":1}]}'],
    ['option_id' => 7, 'option_name' => 'menu_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[2],"items":[{"label":"sync","weight":11,"enabled":1},{"label":"cleanup","weight":0,"enabled":0}]}'],
];

$tables = ['wp_options' => $options];
$sql = "WITH RECURSIVE crawl(option_id, depth, source) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION
            SELECT CAST(edge.atom AS INTEGER), crawl.depth + 1, host.option_name
              FROM crawl
              JOIN wp_options AS host ON host.option_id = crawl.option_id
              JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
             WHERE crawl.depth < 4
             ORDER BY depth DESC, option_id ASC
             LIMIT 7 OFFSET 1
        )
        SELECT crawl.option_id AS option_id,
               crawl.depth AS depth,
               crawl.source AS source,
               host.option_name AS option_name,
               item.key AS attr,
               item.atom AS atom,
               item.fullkey AS fullkey
          FROM crawl
          JOIN wp_options AS host ON host.option_id = crawl.option_id
          JOIN json_tree(host.option_value, '$.items') AS item ON item.type IN ('text', 'integer', 'true', 'false')
         ORDER BY crawl.depth, crawl.option_id, item.fullkey";

$plan = static function () use ($sql, $tables): array {
    static $cached = null;
    if ($cached === null) {
        $cached = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
    }

    return $cached;
};
$frontier = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier($plan(), ['option_id', 'depth']);

$tests['select json recursive current next68 materializes emitted item rows'] = static function (TestRunner $t) use ($plan): void {
    $t->same(42, count($plan()['rows']));
};

$tests['select json recursive current next68 frontier has one entry per recursive trace'] = static function (TestRunner $t) use ($plan, $frontier): void {
    $t->same(count($plan()['recursiveCurrentNext']), count($frontier()));
};

$tests['select json recursive current next68 first current remains skipped seed'] = static function (TestRunner $t) use ($frontier): void {
    $first = $frontier()[0];
    $t->same(['option_id' => 1, 'depth' => 0], $first['currentKey']);
    $t->same('queued-current', $first['status']);
    $t->same(0, $first['currentJsonCount']);
};

$tests['select json recursive current next68 first next is first emitted row'] = static function (TestRunner $t) use ($frontier): void {
    $t->same(['option_id' => 2, 'depth' => 1], $frontier()[0]['nextKey']);
};

$tests['select json recursive current next68 terminal current has null next'] = static function (TestRunner $t) use ($frontier): void {
    $last = $frontier()[count($frontier()) - 1];
    $t->same(null, $last['next']);
    $t->same('terminal-current', $last['status']);
};

$tests['select json recursive current next68 accepted next json rows attach to generated rows'] = static function (TestRunner $t) use ($frontier): void {
    $t->same([6, 6], $frontier()[0]['acceptedNextJsonCounts']);
};

$tests['select json recursive current next68 jsonb accepted row keeps item atoms'] = static function (TestRunner $t) use ($frontier): void {
    $rows = $frontier()[0]['acceptedNextJsonRows'][0];
    $t->same(['api', 'guides'], array_values(array_filter(array_column($rows, 'atom'), 'is_string')));
};

$tests['select json recursive current next68 union duplicate cycle is reported'] = static function (TestRunner $t) use ($frontier): void {
    $skipped = array_merge(...array_map(static fn (array $entry): array => $entry['skippedDuplicateKeys'], $frontier()));
    $t->true(in_array(['option_id' => 2, 'depth' => 4], $skipped, true));
};

$tests['select json recursive current next68 emitted entries expose current json rows'] = static function (TestRunner $t) use ($frontier): void {
    $emitted = array_values(array_filter($frontier(), static fn (array $entry): bool => $entry['status'] === 'emitted-current'));
    $t->same(6, $emitted[0]['currentJsonCount']);
    $t->same(['api', 'guides'], array_values(array_filter(array_column($emitted[0]['currentJsonRows'], 'atom'), 'is_string')));
};

$tests['select json recursive current next68 frontier keeps recursive dependency'] = static function (TestRunner $t) use ($plan): void {
    $t->true(in_array('sqlite-recursive-current-next-json-yield-boundary', $plan()['dependencies'], true));
};

foreach ([0, 1, 2, 3, 4, 5, 6] as $position) {
    $tests['select json recursive current next68 position ' . $position . ' iteration is stable'] = static function (TestRunner $t) use ($frontier, $position): void {
        $t->same($position, $frontier()[$position]['iteration']);
    };
    $tests['select json recursive current next68 position ' . $position . ' current key has option and depth'] = static function (TestRunner $t) use ($frontier, $position): void {
        $key = $frontier()[$position]['currentKey'];
        $t->true(isset($key['option_id']));
        $t->true(isset($key['depth']));
    };
    $tests['select json recursive current next68 position ' . $position . ' json counts match row arrays'] = static function (TestRunner $t) use ($frontier, $position): void {
        $entry = $frontier()[$position];
        $t->same(count($entry['currentJsonRows']), $entry['currentJsonCount']);
        $t->same(count($entry['nextJsonRows']), $entry['nextJsonCount']);
    };
    $tests['select json recursive current next68 position ' . $position . ' accepted counts match arrays'] = static function (TestRunner $t) use ($frontier, $position): void {
        $entry = $frontier()[$position];
        $t->same(count($entry['acceptedNext']), count($entry['acceptedNextJsonRows']));
        $t->same(count($entry['acceptedNext']), count($entry['acceptedNextKeys']));
    };
}

foreach ([2 => 12, 3 => 6, 4 => 6, 5 => 6, 6 => 0, 7 => 12] as $optionId => $expectedRows) {
    $tests['select json recursive current next68 option ' . $optionId . ' emitted json row count'] = static function (TestRunner $t) use ($frontier, $optionId, $expectedRows): void {
        $count = 0;
        foreach ($frontier() as $entry) {
            if (($entry['current']['option_id'] ?? null) === $optionId) {
                $count += $entry['currentJsonCount'];
            }
        }
        $t->same($expectedRows, $count);
    };
    $tests['select json recursive current next68 option ' . $optionId . ' next json count is bounded'] = static function (TestRunner $t) use ($frontier, $optionId): void {
        $counts = [];
        foreach ($frontier() as $entry) {
            if (($entry['next']['option_id'] ?? null) === $optionId) {
                $counts[] = $entry['nextJsonCount'];
            }
        }
        $t->true($counts === [] || max($counts) <= 6);
    };
}

foreach (['api', 'guides', 'news', 'events', 'search', 'filters', 'sync', 'cleanup'] as $label) {
    $tests['select json recursive current next68 label frontier includes ' . $label] = static function (TestRunner $t) use ($frontier, $label): void {
        $atoms = [];
        foreach ($frontier() as $entry) {
            $atoms = array_merge($atoms, array_column($entry['currentJsonRows'], 'atom'));
        }
        $t->true(in_array($label, $atoms, true));
    };
}

$tests['select json recursive current next68 rejects empty identity columns'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier($plan(), []));
};

$tests['select json recursive current next68 rejects missing identity column'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier($plan(), ['option_id', 'missing']));
};

$tests['select json recursive current next68 rejects non recursive plan'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier(['rows' => []], ['option_id']));
};

return $tests;
