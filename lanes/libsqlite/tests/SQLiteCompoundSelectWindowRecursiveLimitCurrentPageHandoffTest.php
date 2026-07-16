<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions232 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions232 = [
    ...$currentOptions232,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables232 = ['wp_options' => $currentOptions232];
$nextTables232 = ['wp_options' => $nextOptions232];

$sql232 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY score DESC) AS rn
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summary232 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageHandoff($sql232, $currentTables232, $nextTables232, $cursor);
$tests = [];

$tests['compound select window recursive limit current source current-page-handoff status dependencies'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->same('compound-select-window-recursive-limit-current-source-current-page-handoff-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-next-source-handoff-current-page-handoff', $plan['dependencies'], true));
    $t->contains('acknowledgement handoff to the next-source cursor', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source current-page-handoff inherits dense rank rows'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 2, 3], array_column($plan['currentRows'], 'rn'));
    $t->same([2, 2, 3], array_column($plan['nextRows'], 'rn'));
};

$tests['compound select window recursive limit current source current-page-handoff handoff token shape'] = static function (TestRunner $t) use ($summary232): void {
    $handoff = $summary232()['currentSourceHandoffCurrentPageHandoff'];
    $t->same(64, strlen($handoff['currentPageToken']));
    $t->same(64, strlen($handoff['nextSourceCursor']['sourceEpoch']));
    $t->same(3, $handoff['requiredAckCount']);
    $t->same(3, count($handoff['requiredCurrentAcks']));
    $t->same('held-until-current-page-acks-match', $handoff['nextExposure']);
};

$tests['compound select window recursive limit current source current-page-handoff labels and truncation'] = static function (TestRunner $t) use ($summary232): void {
    $handoff = $summary232()['currentSourceHandoffCurrentPageHandoff'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $handoff['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $handoff['nextLabels']);
    $t->same(['plugin_prime'], $handoff['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $handoff['currentOnlyLabels']);
    $t->same(['seed:2:3'], $handoff['currentSkippedLabels']);
    $t->same(['seed:2:3'], $handoff['nextSkippedLabels']);
};

$tests['compound select window recursive limit current source current-page-handoff exposes next cursor after exact acknowledgements'] = static function (TestRunner $t) use ($summary232): void {
    $first = $summary232();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $first['currentSourceHandoffCurrentPageHandoff']['requiredCurrentAcks'];
    $again = $summary232($cursor);
    $t->same($first['currentSourceHandoffCurrentPageHandoff']['currentPageToken'], $again['currentSourceHandoffCurrentPageHandoff']['currentPageToken']);
    $t->same($first['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor'], $again['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor']);
    $t->same($first['sourceWindow']['nextToken'], $again['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor']['currentToken']);
};

$tests['compound select window recursive limit current source current-page-handoff rejects stale page token'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['currentPageTokenCurrentPageHandoff'] = str_repeat('1', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source current-page-handoff rejects incomplete acknowledgements'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = array_slice($cursor['requiredCurrentAcksCurrentPageHandoff'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source current-page-handoff rejects unexpected acknowledgement'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = [...$cursor['requiredCurrentAcksCurrentPageHandoff'], str_repeat('a', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source current-page-handoff executor parity'] = static function (TestRunner $t) use ($sql232, $currentTables232, $summary232): void {
    $t->same(SQLiteSelectSql::execute($sql232, $currentTables232), $summary232()['currentRows']);
};

$tests['compound select window recursive limit current source current-page-handoff non overlap'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->contains('extends accepted union-except-dense-rank-limit', $plan['non_overlap']);
    $t->true(in_array('compound-dense-rank-except-current-page-handoff-current-page-handoff', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-window-page-acks-before-next-source-current-page-handoff', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source current-page-handoff generated handoff ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageHandoff($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageHandoff($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $plan['currentSourceHandoffCurrentPageHandoff']['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $plan['currentSourceHandoffCurrentPageHandoff']['nextLabels']);
        $t->same(['plugin_' . $case], $plan['currentSourceHandoffCurrentPageHandoff']['nextOnlyLabels']);
        $t->same(3, $plan['currentSourceHandoffCurrentPageHandoff']['requiredAckCount']);
        $t->same($plan['currentSourceHandoffCurrentPageHandoff']['currentPageToken'], $again['currentSourceHandoffCurrentPageHandoff']['currentPageToken']);
        $t->same($plan['sourceWindow']['nextToken'], $again['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor']['currentToken']);
    };
}

return $tests;
