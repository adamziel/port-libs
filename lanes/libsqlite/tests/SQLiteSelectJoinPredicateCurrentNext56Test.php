<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no'],
];

$incoming = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'source' => 'copied'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'source' => 'copied'],
    ['option_id' => 5, 'blog_id' => 1, 'option_name' => 'active_plugins', 'source' => 'insert'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'network_admin_email', 'source' => 'network'],
    ['option_id' => null, 'blog_id' => 3, 'option_name' => 'null_import', 'source' => 'ignored'],
];

$events = [
    ['option_id' => 1, 'blog_id' => 1, 'event' => 'update', 'priority' => 20],
    ['option_id' => 2, 'blog_id' => 1, 'event' => 'update', 'priority' => 10],
    ['option_id' => 5, 'blog_id' => 1, 'event' => 'insert', 'priority' => 30],
    ['option_id' => 6, 'blog_id' => 2, 'event' => 'network', 'priority' => 40],
    ['option_id' => 7, 'blog_id' => 2, 'event' => 'orphan_event', 'priority' => 50],
];

$labels = [
    ['option_id' => 1, 'blog_id' => 1, 'label' => 'existing-url'],
    ['option_id' => 2, 'blog_id' => 1, 'label' => 'existing-home'],
    ['option_id' => 5, 'blog_id' => 1, 'label' => 'new-plugin'],
    ['option_id' => 6, 'blog_id' => 2, 'label' => 'network-new'],
    ['option_id' => 7, 'blog_id' => 2, 'label' => 'event-only'],
];

$tables = [
    'wp_options' => $current,
    'incoming_options' => $incoming,
    'option_events' => $events,
    'option_labels' => $labels,
];

$tests = [];

$cases = [
    'right join feeds following using for inserted option' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE i.source = 'insert'",
        ['active_plugins:new-plugin'],
    ],
    'right join feeds following using for network option' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE i.source = 'network'",
        ['network_admin_email:network-new'],
    ],
    'right join feeds following multi column using' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id, blog_id) JOIN option_labels AS l USING(option_id, blog_id) WHERE i.source IN ('insert', 'network') ORDER BY i.option_id",
        ['active_plugins:new-plugin', 'network_admin_email:network-new'],
    ],
    'full join feeds following using for inserted option' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c FULL OUTER JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE i.source = 'insert'",
        ['active_plugins:new-plugin'],
    ],
    'full join feeds following using for current-only rows' => [
        "SELECT c.option_name AS name, l.label AS label FROM wp_options AS c FULL OUTER JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE i.option_name IS NULL ORDER BY c.option_id",
        [],
    ],
    'full join feeds following using before filtering current rows' => [
        "SELECT ifnull(i.option_name, c.option_name) AS name, l.label AS label FROM wp_options AS c FULL OUTER JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) ORDER BY name",
        ['active_plugins:new-plugin', 'home:existing-home', 'network_admin_email:network-new', 'siteurl:existing-url'],
    ],
    'right join using chain can join event rows' => [
        "SELECT i.option_name AS name, e.event AS event FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_events AS e USING(option_id) WHERE e.event IN ('insert', 'network') ORDER BY e.priority",
        ['active_plugins:insert', 'network_admin_email:network'],
    ],
    'full join using chain can join event rows' => [
        "SELECT ifnull(i.option_name, c.option_name) AS name, e.event AS event FROM wp_options AS c FULL JOIN incoming_options AS i USING(option_id) JOIN option_events AS e USING(option_id) ORDER BY e.priority",
        ['home:update', 'siteurl:update', 'active_plugins:insert', 'network_admin_email:network'],
    ],
    'right join current key drives later left join null extension' => [
        "SELECT i.option_name AS name, ifnull(e.event, 'missing') AS event FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) LEFT JOIN option_events AS e USING(option_id) WHERE i.source IN ('insert', 'network') ORDER BY i.option_id",
        ['active_plugins:insert', 'network_admin_email:network'],
    ],
    'right join current key drives later full join unmatched right rows' => [
        "SELECT ifnull(i.option_name, 'none') AS name, e.event AS event FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) FULL JOIN option_events AS e USING(option_id) WHERE e.event IN ('network', 'orphan_event') ORDER BY e.priority",
        ['network_admin_email:network', 'none:orphan_event'],
    ],
    'right join current key remains null for null using value' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) LEFT JOIN option_labels AS l USING(option_id) WHERE i.option_name = 'null_import'",
        ['null_import:'],
    ],
    'full join current key remains null for null using value' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c FULL JOIN incoming_options AS i USING(option_id) LEFT JOIN option_labels AS l USING(option_id) WHERE i.option_name = 'null_import'",
        ['null_import:'],
    ],
    'right join current key supports chained where predicate' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE l.label GLOB '*new' ORDER BY name",
        ['network_admin_email:network-new'],
    ],
    'full join current key supports chained where predicate' => [
        "SELECT ifnull(i.option_name, c.option_name) AS name, l.label AS label FROM wp_options AS c FULL JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l USING(option_id) WHERE l.label LIKE 'existing%' ORDER BY name",
        ['home:existing-home', 'siteurl:existing-url'],
    ],
    'right join current key supports later on predicate' => [
        "SELECT i.option_name AS name, l.label AS label FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_labels AS l ON i.option_id = l.option_id WHERE i.source = 'insert'",
        ['active_plugins:new-plugin'],
    ],
    'right join current using key supports later using after on' => [
        "SELECT i.option_name AS name, e.event AS event FROM wp_options AS c RIGHT JOIN incoming_options AS i ON c.option_id = i.option_id JOIN option_events AS e USING(option_id) WHERE i.source = 'insert'",
        ['active_plugins:insert'],
    ],
    'full join current using key supports later using after on' => [
        "SELECT i.option_name AS name, e.event AS event FROM wp_options AS c FULL JOIN incoming_options AS i ON c.option_id = i.option_id JOIN option_events AS e USING(option_id) WHERE i.source = 'network'",
        ['network_admin_email:network'],
    ],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['select join predicate current next56 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $actual = array_map(
            static fn (array $row): string => (string) ($row['name'] ?? '') . ':' . (string) ($row['label'] ?? $row['event'] ?? ''),
            $rows,
        );

        $t->same($expected, $actual);
        $t->same(count($expected), count($rows));
    };
}

for ($i = 1; $i <= 33; $i++) {
    $event = $i % 2 === 0 ? 'insert' : 'network';
    $expected = $event === 'insert' ? ['active_plugins'] : ['network_admin_email'];
    $tests['select join predicate current next56 repeated current key probe ' . $i] = static function (TestRunner $t) use ($tables, $event, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT i.option_name AS name FROM wp_options AS c RIGHT JOIN incoming_options AS i USING(option_id) JOIN option_events AS e USING(option_id) WHERE e.event = '{$event}'",
            $tables,
        );

        $t->same($expected, array_column($rows, 'name'));
        $t->same($expected === [] ? 0 : 1, count($rows));
    };
}

return $tests;
