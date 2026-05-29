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

$summary235 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveWindowPromotionBarrier($sql235, $currentTables235, $nextTables235, $cursor);
$tests = [];

$tests['compound select window recursive limit current source recursive-window-promotion-barrier status dependencies'] = static function (TestRunner $t) use ($summary235): void {
    $plan = $summary235();
    $t->same('compound-select-window-recursive-limit-current-source-recursive-window-promotion-barrier-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-promotion-barrier-recursive-window-promotion-barrier', $plan['dependencies'], true));
    $t->contains('recursive trace plus window-frame acknowledgement tokens', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier inherits page handoff rows'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $barrier['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $barrier['nextLabels']);
    $t->same(['plugin_prime'], $barrier['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $barrier['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier token lengths'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier'];
    $t->same(64, strlen($barrier['barrierToken']));
    $t->same(64, strlen($barrier['currentPageToken']));
    $t->same(64, strlen($barrier['recursiveTraceToken']));
    $t->same(64, strlen($barrier['windowFrameToken']));
    $t->same(3, $barrier['requiredPromotionAckCount']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier ack prefix shape'] = static function (TestRunner $t) use ($summary235): void {
    $acks = $summary235()['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['requiredPromotionAcks'];
    $t->same('page:', substr($acks[0], 0, 5));
    $t->same('recursive:', substr($acks[1], 0, 10));
    $t->same('window:', substr($acks[2], 0, 7));
    $t->same(69, strlen($acks[0]));
    $t->same(74, strlen($acks[1]));
    $t->same(71, strlen($acks[2]));
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier recursive and window metadata'] = static function (TestRunner $t) use ($summary235): void {
    $barrier = $summary235()['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier'];
    $t->same(['seed', 'seed:2'], $barrier['recursiveSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $barrier['recursiveEmittedLabels']);
    $t->same(['dense_rank'], $barrier['windowFunctions']);
    $t->same(0, $barrier['windowMetricCount']);
    $t->same(3, $barrier['currentPageAckCount']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier promotes after exact acknowledgements'] = static function (TestRunner $t) use ($summary235): void {
    $first = $summary235();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
    $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
    $again = $summary235($cursor);
    $t->same($first['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['barrierToken'], $again['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['barrierToken']);
    $t->same($first['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['promotedNextSourceCursor'], $again['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['promotedNextSourceCursor']);
    $t->same($first['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor'], $again['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['promotedNextSourceCursor']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier rejects stale barrier token'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['promotionBarrierTokenRecursiveWindowPromotionBarrier'] = str_repeat('2', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier rejects stale recursive token'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['recursiveTraceTokenRecursiveWindowPromotionBarrier'] = str_repeat('3', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier rejects missing promotion acknowledgement'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = array_slice($cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier rejects unexpected promotion acknowledgement'] = static function (TestRunner $t) use ($summary235): void {
    $cursor = $summary235()['cursor'];
    $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = [...$cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'], str_repeat('b', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary235($cursor));
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier executor parity'] = static function (TestRunner $t) use ($sql235, $currentTables235, $summary235): void {
    $t->same(SQLiteSelectSql::execute($sql235, $currentTables235), $summary235()['currentRows']);
};

$tests['compound select window recursive limit current source recursive-window-promotion-barrier non overlap'] = static function (TestRunner $t) use ($summary235): void {
    $plan = $summary235();
    $t->contains('extends accepted current-page-handoff', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-promotion-barrier-recursive-window-promotion-barrier', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-trace-and-window-frame-acks-recursive-window-promotion-barrier', $plan['replanReasons'], true));
};

foreach (range(1, 62) as $case) {
    $tests['compound select window recursive limit current source recursive-window-promotion-barrier generated promotion barrier ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveWindowPromotionBarrier($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
        $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveWindowPromotionBarrier($sql, $tables, $nextTables, $cursor);
        $barrier = $plan['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier'];

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $barrier['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $barrier['nextLabels']);
        $t->same(['plugin_' . $case], $barrier['nextOnlyLabels']);
        $t->same(3, $barrier['requiredPromotionAckCount']);
        $t->same('held-until-page-recursive-and-window-acks-match', $barrier['promotionState']);
        $t->same($barrier['barrierToken'], $again['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['barrierToken']);
        $t->same($plan['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor'], $again['recursiveWindowPromotionBarrierRecursiveWindowPromotionBarrier']['promotedNextSourceCursor']);
    };
}

return $tests;
