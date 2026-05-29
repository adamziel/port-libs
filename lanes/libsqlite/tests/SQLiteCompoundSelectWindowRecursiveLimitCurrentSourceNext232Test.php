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

$summary232 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext232($sql232, $currentTables232, $nextTables232, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next232 status dependencies'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->same('compound-select-window-recursive-limit-current-source-next232-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-next-source-handoff-next232', $plan['dependencies'], true));
    $t->contains('acknowledgement handoff to the next-source cursor', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next232 inherits dense rank rows'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 2, 3], array_column($plan['currentRows'], 'rn'));
    $t->same([2, 2, 3], array_column($plan['nextRows'], 'rn'));
};

$tests['compound select window recursive limit current source next232 handoff token shape'] = static function (TestRunner $t) use ($summary232): void {
    $handoff = $summary232()['currentSourceHandoffNext232'];
    $t->same(64, strlen($handoff['currentPageToken']));
    $t->same(64, strlen($handoff['nextSourceCursor']['sourceEpoch']));
    $t->same(3, $handoff['requiredAckCount']);
    $t->same(3, count($handoff['requiredCurrentAcks']));
    $t->same('held-until-current-page-acks-match', $handoff['nextExposure']);
};

$tests['compound select window recursive limit current source next232 labels and truncation'] = static function (TestRunner $t) use ($summary232): void {
    $handoff = $summary232()['currentSourceHandoffNext232'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $handoff['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $handoff['nextLabels']);
    $t->same(['plugin_prime'], $handoff['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $handoff['currentOnlyLabels']);
    $t->same(['seed:2:3'], $handoff['currentSkippedLabels']);
    $t->same(['seed:2:3'], $handoff['nextSkippedLabels']);
};

$tests['compound select window recursive limit current source next232 exposes next cursor after exact acknowledgements'] = static function (TestRunner $t) use ($summary232): void {
    $first = $summary232();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $first['currentSourceHandoffNext232']['requiredCurrentAcks'];
    $again = $summary232($cursor);
    $t->same($first['currentSourceHandoffNext232']['currentPageToken'], $again['currentSourceHandoffNext232']['currentPageToken']);
    $t->same($first['currentSourceHandoffNext232']['nextSourceCursor'], $again['currentSourceHandoffNext232']['nextSourceCursor']);
    $t->same($first['sourceWindow']['nextToken'], $again['currentSourceHandoffNext232']['nextSourceCursor']['currentToken']);
};

$tests['compound select window recursive limit current source next232 rejects stale page token'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['currentPageTokenNext232'] = str_repeat('1', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source next232 rejects incomplete acknowledgements'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = array_slice($cursor['requiredCurrentAcksNext232'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source next232 rejects unexpected acknowledgement'] = static function (TestRunner $t) use ($summary232): void {
    $cursor = $summary232()['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = [...$cursor['requiredCurrentAcksNext232'], str_repeat('a', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary232($cursor));
};

$tests['compound select window recursive limit current source next232 executor parity'] = static function (TestRunner $t) use ($sql232, $currentTables232, $summary232): void {
    $t->same(SQLiteSelectSql::execute($sql232, $currentTables232), $summary232()['currentRows']);
};

$tests['compound select window recursive limit current source next232 non overlap'] = static function (TestRunner $t) use ($summary232): void {
    $plan = $summary232();
    $t->contains('extends accepted next229', $plan['non_overlap']);
    $t->true(in_array('compound-dense-rank-except-current-page-handoff-next232', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-window-page-acks-before-next-source-next232', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next232 generated handoff ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext232($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext232($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $plan['currentSourceHandoffNext232']['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $plan['currentSourceHandoffNext232']['nextLabels']);
        $t->same(['plugin_' . $case], $plan['currentSourceHandoffNext232']['nextOnlyLabels']);
        $t->same(3, $plan['currentSourceHandoffNext232']['requiredAckCount']);
        $t->same($plan['currentSourceHandoffNext232']['currentPageToken'], $again['currentSourceHandoffNext232']['currentPageToken']);
        $t->same($plan['sourceWindow']['nextToken'], $again['currentSourceHandoffNext232']['nextSourceCursor']['currentToken']);
    };
}

return $tests;
