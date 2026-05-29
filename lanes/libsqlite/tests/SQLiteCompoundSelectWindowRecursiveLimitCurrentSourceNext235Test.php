<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions235 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions235 = [
    ...$currentOptions235,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables235 = ['wp_options' => $currentOptions235];
$nextTables235 = ['wp_options' => $nextOptions235];

$sql235 = <<<'SQL'
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

$summary235 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext235($sql235, $currentTables235, $nextTables235, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next235 status dependencies'] = static function (TestRunner $t) use ($summary235): void {
    $plan = $summary235();
    $t->same('compound-select-window-recursive-limit-current-source-next235-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-promotion-barrier-next235', $plan['dependencies'], true));
    $t->contains('recursive trace plus window-frame acknowledgement tokens', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next235 inherits page handoff rows'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierNext235'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $barrier['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $barrier['nextLabels']);
    $t->same(['plugin_prime'], $barrier['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $barrier['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next235 token lengths'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierNext235'];
    $t->same(64, strlen($barrier['barrierToken']));
    $t->same(64, strlen($barrier['currentPageToken']));
    $t->same(64, strlen($barrier['recursiveTraceToken']));
    $t->same(64, strlen($barrier['windowFrameToken']));
    $t->same(3, $barrier['requiredPromotionAckCount']);
};

$tests['compound select window recursive limit current source next235 ack prefix shape'] = static function (TestRunner $t) use ($summary235): void {
    $acks = $summary235()['recursiveWindowPromotionBarrierNext235']['requiredPromotionAcks'];
    $t->same('page:', substr($acks[0], 0, 5));
    $t->same('recursive:', substr($acks[1], 0, 10));
    $t->same('window:', substr($acks[2], 0, 7));
    $t->same(69, strlen($acks[0]));
    $t->same(74, strlen($acks[1]));
    $t->same(71, strlen($acks[2]));
};

$tests['compound select window recursive limit current source next235 recursive and window metadata'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierNext235'];
    $t->same(['seed', 'seed:2'], $barrier['recursiveSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $barrier['recursiveEmittedLabels']);
    $t->same(['dense_rank'], $barrier['windowFunctions']);
    $t->same(0, $barrier['windowMetricCount']);
    $t->same(3, $barrier['currentPageAckCount']);
};

$tests['compound select window recursive limit current source next235 promotes after exact acknowledgements'] = static function (TestRunner $t) use ($summary235): void {
    $first = $summary235();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $again = $summary235($cursor);
    $t->same($first['recursiveWindowPromotionBarrierNext235']['barrierToken'], $again['recursiveWindowPromotionBarrierNext235']['barrierToken']);
    $t->same($first['recursiveWindowPromotionBarrierNext235']['promotedNextSourceCursor'], $again['recursiveWindowPromotionBarrierNext235']['promotedNextSourceCursor']);
    $t->same($first['currentSourceHandoffNext232']['nextSourceCursor'], $again['recursiveWindowPromotionBarrierNext235']['promotedNextSourceCursor']);
};

$tests['compound select window recursive limit current source next235 rejects stale barrier token'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['promotionBarrierTokenNext235'] = str_repeat('2', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source next235 rejects stale recursive token'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['recursiveTraceTokenNext235'] = str_repeat('3', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source next235 rejects missing promotion acknowledgement'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['acknowledgedPromotionAcksNext235'] = array_slice($cursor['requiredPromotionAcksNext235'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source next235 rejects unexpected promotion acknowledgement'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['acknowledgedPromotionAcksNext235'] = [...$cursor['requiredPromotionAcksNext235'], str_repeat('b', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source next235 executor parity'] = static function (TestRunner $t) use ($sql235, $currentTables235, $summary235): void {
    $t->same(SQLiteSelectSql::execute($sql235, $currentTables235), $summary235()['currentRows']);
};

$tests['compound select window recursive limit current source next235 non overlap'] = static function (TestRunner $t) use ($summary235): void {
    $plan = $summary235();
    $t->contains('extends accepted next232', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-promotion-barrier-next235', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-trace-and-window-frame-acks-next235', $plan['replanReasons'], true));
};

foreach (range(1, 62) as $case) {
    $tests['compound select window recursive limit current source next235 generated promotion barrier ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext235($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext235($sql, $tables, $nextTables, $cursor);
        $barrier = $plan['recursiveWindowPromotionBarrierNext235'];

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $barrier['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $barrier['nextLabels']);
        $t->same(['plugin_' . $case], $barrier['nextOnlyLabels']);
        $t->same(3, $barrier['requiredPromotionAckCount']);
        $t->same('held-until-page-recursive-and-window-acks-match', $barrier['promotionState']);
        $t->same($barrier['barrierToken'], $again['recursiveWindowPromotionBarrierNext235']['barrierToken']);
        $t->same($plan['currentSourceHandoffNext232']['nextSourceCursor'], $again['recursiveWindowPromotionBarrierNext235']['promotedNextSourceCursor']);
    };
}

return $tests;
