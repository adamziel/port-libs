<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ['option_id' => 9, 'blog_id' => 2, 'option_name' => 'network_old', 'autoload' => 'no'],
];

$incoming = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'source' => 'update'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'source' => 'update'],
    ['option_id' => 5, 'blog_id' => 1, 'option_name' => 'active_plugins', 'source' => 'insert'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'network_admin_email', 'source' => 'network'],
    ['option_id' => null, 'blog_id' => 3, 'option_name' => 'null_import', 'source' => 'ignored'],
];

$labels = [
    ['option_id' => 1, 'blog_id' => 1, 'label' => 'existing-url'],
    ['option_id' => 2, 'blog_id' => 1, 'label' => 'existing-home'],
    ['option_id' => 5, 'blog_id' => 1, 'label' => 'new-plugin'],
    ['option_id' => 6, 'blog_id' => 2, 'label' => 'network-new'],
    ['option_id' => 7, 'blog_id' => 2, 'label' => 'event-only'],
];

$events = [
    ['option_id' => 1, 'blog_id' => 1, 'event' => 'update', 'priority' => 20],
    ['option_id' => 2, 'blog_id' => 1, 'event' => 'update', 'priority' => 10],
    ['option_id' => 5, 'blog_id' => 1, 'event' => 'insert', 'priority' => 30],
    ['option_id' => 6, 'blog_id' => 2, 'event' => 'network', 'priority' => 40],
    ['option_id' => 7, 'blog_id' => 2, 'event' => 'orphan_event', 'priority' => 50],
];

$tables = [
    'wp_options' => $current,
    'incoming_options' => $incoming,
    'option_labels' => $labels,
    'option_events' => $events,
];

$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);
$pairs = static fn (string $sql): array => array_map(
    static fn (array $row): string => (string) ($row['name'] ?? '') . ':' . (string) ($row['detail'] ?? ''),
    SQLiteSelectSql::execute($sql, $tables),
);

$cases = [
    'parenthesized inner join source keeps qualified names' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) WHERE l.label GLOB '*new' ORDER BY i.option_id",
        'name',
        ['network_admin_email'],
    ],
    'parenthesized join group feeds outer left join' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) LEFT JOIN option_events AS e USING(option_id) WHERE e.event IN ('insert', 'network') ORDER BY e.priority",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
    'parenthesized join group feeds outer right join' => [
        "SELECT coalesce(i.option_name, 'missing') AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) RIGHT JOIN option_events AS e USING(option_id) WHERE e.event = 'orphan_event'",
        'name',
        ['missing'],
    ],
    'parenthesized join group feeds outer full join' => [
        "SELECT coalesce(i.option_name, c.option_name, 'missing') AS name FROM (wp_options AS c FULL JOIN incoming_options AS i USING(option_id)) FULL JOIN option_events AS e USING(option_id) WHERE e.event IN ('orphan_event', 'network') ORDER BY e.priority",
        'name',
        ['network_admin_email', 'missing'],
    ],
    'parenthesized left join preserves null extended rows' => [
        "SELECT c.option_name AS name FROM (wp_options AS c LEFT JOIN incoming_options AS i USING(option_id)) WHERE i.option_id IS NULL ORDER BY c.option_id",
        'name',
        ['blogname', '_transient_feed', 'network_old'],
    ],
    'parenthesized right join preserves null extended rows' => [
        "SELECT i.option_name AS name FROM (wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id)) WHERE c.option_id IS NULL ORDER BY i.blog_id, i.option_name",
        'name',
        ['active_plugins', 'network_admin_email', 'null_import'],
    ],
    'parenthesized full join can be filtered before next join' => [
        "SELECT coalesce(i.option_name, c.option_name) AS name FROM (wp_options AS c FULL JOIN incoming_options AS i USING(option_id)) WHERE i.source = 'insert' OR c.option_name = 'blogname' ORDER BY name",
        'name',
        ['active_plugins', 'blogname'],
    ],
    'parenthesized comma join source is normalized inside group' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i, option_labels AS l) WHERE i.option_id = l.option_id AND l.label LIKE 'existing%' ORDER BY i.option_id",
        'name',
        ['siteurl', 'home'],
    ],
    'parenthesized natural join source finds common columns' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i NATURAL JOIN option_labels AS l) WHERE l.label GLOB '*new' ORDER BY i.option_id",
        'name',
        ['network_admin_email'],
    ],
    'parenthesized group alias is accepted for later joins' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) AS staged JOIN option_events AS e USING(option_id) WHERE e.priority >= 30 ORDER BY e.priority",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
    'parenthesized group can be the right side of an outer join' => [
        "SELECT coalesce(i.option_name, 'missing') AS name FROM wp_options AS c LEFT JOIN (incoming_options AS i JOIN option_labels AS l USING(option_id)) AS staged ON c.option_id = i.option_id WHERE c.option_id IN (1, 3) ORDER BY c.option_id",
        'name',
        ['siteurl', 'missing'],
    ],
    'parenthesized group can be the right side of a full join' => [
        "SELECT coalesce(i.option_name, c.option_name) AS name FROM wp_options AS c FULL JOIN (incoming_options AS i JOIN option_labels AS l USING(option_id)) AS staged ON c.option_id = i.option_id WHERE c.option_id IS NULL OR i.source = 'network' ORDER BY name",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
    'nested parenthesized groups preserve planner order' => [
        "SELECT i.option_name AS name FROM ((incoming_options AS i JOIN option_labels AS l USING(option_id)) JOIN option_events AS e USING(option_id)) WHERE e.priority >= 30 ORDER BY e.priority",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
    'parenthesized group supports grouped aggregate after join' => [
        "SELECT i.source AS source, count(e.event) AS total FROM (incoming_options AS i LEFT JOIN option_events AS e USING(option_id)) GROUP BY i.source HAVING count(e.event) >= 1 ORDER BY source",
        'source',
        ['insert', 'network', 'update'],
    ],
    'parenthesized group supports expression order after join' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) ORDER BY length(l.label), i.option_name LIMIT 3",
        'name',
        ['active_plugins', 'network_admin_email', 'siteurl'],
    ],
    'parenthesized group supports distinct after full join' => [
        "SELECT DISTINCT coalesce(i.source, 'current') AS source FROM (wp_options AS c FULL JOIN incoming_options AS i USING(option_id)) ORDER BY source",
        'source',
        ['current', 'ignored', 'insert', 'network', 'update'],
    ],
    'parenthesized group supports cte source inside group' => [
        "WITH picked(id) AS (VALUES (1), (5), (6)) SELECT i.option_name AS name FROM (picked JOIN incoming_options AS i ON picked.id = i.option_id) ORDER BY picked.id",
        'name',
        ['siteurl', 'active_plugins', 'network_admin_email'],
    ],
    'parenthesized group supports derived source inside group' => [
        "SELECT i.option_name AS name FROM ((SELECT option_id FROM option_events WHERE priority >= 30) AS picked JOIN incoming_options AS i ON picked.option_id = i.option_id) ORDER BY picked.option_id",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
    'parenthesized group keeps null using values unmatched' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i LEFT JOIN option_labels AS l USING(option_id)) WHERE i.option_id IS NULL AND l.label IS NULL",
        'name',
        ['null_import'],
    ],
    'parenthesized group supports compound where predicates' => [
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) WHERE (i.source = 'insert' AND l.label = 'new-plugin') OR (i.source = 'network' AND l.label = 'network-new') ORDER BY i.option_id",
        'name',
        ['active_plugins', 'network_admin_email'],
    ],
];

$pairCases = [
    'parenthesized group projects joined label pairs' => [
        "SELECT i.option_name AS name, l.label AS detail FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) WHERE i.source IN ('insert', 'network') ORDER BY i.option_id",
        ['active_plugins:new-plugin', 'network_admin_email:network-new'],
    ],
    'parenthesized group projects event pairs after outer join' => [
        "SELECT i.option_name AS name, coalesce(e.event, 'missing') AS detail FROM (incoming_options AS i LEFT JOIN option_events AS e USING(option_id)) WHERE i.source IN ('insert', 'ignored') ORDER BY i.option_id",
        ['null_import:missing', 'active_plugins:insert'],
    ],
    'nested parenthesized group projects label event pairs' => [
        "SELECT i.option_name AS name, l.label || '/' || e.event AS detail FROM ((incoming_options AS i JOIN option_labels AS l USING(option_id)) JOIN option_events AS e USING(option_id)) WHERE e.priority >= 30 ORDER BY e.priority",
        ['active_plugins:new-plugin/insert', 'network_admin_email:network-new/network'],
    ],
];

$tests = [];
foreach ($cases as $name => [$sql, $columnName, $expected]) {
    $tests['select join planner current next72 ' . $name] = static function (TestRunner $t) use ($column, $sql, $columnName, $expected): void {
        $rows = $column($sql, $columnName);
        $t->same($expected, $rows);
        $t->same(count($expected), count($rows));
    };
}

foreach ($pairCases as $name => [$sql, $expected]) {
    $tests['select join planner current next72 ' . $name] = static function (TestRunner $t) use ($pairs, $sql, $expected): void {
        $rows = $pairs($sql);
        $t->same($expected, $rows);
        $t->same(count($expected), count($rows));
    };
}

foreach (range(1, 18) as $priority) {
    $minimum = $priority % 2 === 0 ? 30 : 40;
    $expected = $minimum === 30 ? ['active_plugins', 'network_admin_email'] : ['network_admin_email'];
    $tests['select join planner current next72 repeated parenthesized event threshold ' . $priority] = static function (TestRunner $t) use ($column, $minimum, $expected): void {
        $rows = $column(
            "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_events AS e USING(option_id)) WHERE e.priority >= {$minimum} ORDER BY e.priority",
            'name',
        );
        $t->same($expected, $rows);
        $t->same(count($expected), count($rows));
    };
}

$tests['select join planner current next72 plan records grouped source join'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT i.option_name AS name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) JOIN option_events AS e USING(option_id) WHERE e.priority >= 30",
        $tables,
    );

    $t->same('join_group', $plan['sourceAlias'] ?? 'join_group');
    $t->same(1, count($plan['joins'] ?? []));
};

$tests['select join planner current next72 rejects malformed group alias'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT i.option_name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) trailing tokens",
        $tables,
    ));
};

$tests['select join planner current next72 rejects parenthesized join column aliases'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT i.option_name FROM (incoming_options AS i JOIN option_labels AS l USING(option_id)) AS staged(a, b)",
        $tables,
    ));
};

return $tests;
