<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions249 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions249 = [
    ...$currentOptions249,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables249 = ['wp_options' => $currentOptions249];
$nextTables249 = ['wp_options' => $nextOptions249];

$sql249 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              rank() OVER (ORDER BY score DESC) AS metric
         FROM q
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
         FROM wp_options
        WHERE autoload = 'yes'
  )
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY metric, label
 LIMIT 4 OFFSET 1
SQL;

$summary249 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext249($sql249, $currentTables249, $nextTables249, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next249 status dependencies'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $t->same('compound-select-window-recursive-limit-current-source-next249-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-promotion-epoch-next249', $plan['dependencies'], true));
    $t->contains('recursive-lineage/window-metric epoch fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next249 keeps next245 snapshot'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $t->same(2, $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTicketCount']);
    $t->same(['plugin_prime'], $plan['compoundNextSourcePromotionSnapshotNext245']['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $plan['compoundNextSourcePromotionSnapshotNext245']['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next249 epoch token shape'] = static function (TestRunner $t) use ($summary249): void {
    $epoch = $summary249()['compoundRecursiveWindowPromotionEpochNext249'];
    $t->same(64, strlen($epoch['promotionEpochToken']));
    $t->same(64, strlen($epoch['recursiveLineageToken']));
    $t->same(64, strlen($epoch['windowMetricToken']));
    $t->same(3, $epoch['requiredPromotionEpochAckCount']);
    $t->same('epoch:', substr($epoch['requiredPromotionEpochAcks'][0], 0, 6));
    $t->same('lineage:', substr($epoch['requiredPromotionEpochAcks'][1], 0, 8));
    $t->same('metrics:', substr($epoch['requiredPromotionEpochAcks'][2], 0, 8));
};

$tests['compound select window recursive limit current source next249 labels and metrics'] = static function (TestRunner $t) use ($summary249): void {
    $epoch = $summary249()['compoundRecursiveWindowPromotionEpochNext249'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $epoch['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $epoch['nextLabels']);
    $t->same([2, 2, 3, 3], $epoch['currentWindowMetrics']);
    $t->same([2, 2, 3, 3], $epoch['nextWindowMetrics']);
    $t->same(false, $epoch['windowMetricsChanged']);
};

$tests['compound select window recursive limit current source next249 recursive lineage'] = static function (TestRunner $t) use ($summary249): void {
    $lineage = $summary249()['compoundRecursiveWindowPromotionEpochNext249']['recursiveLineage'];
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $lineage['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $lineage['nextEmittedLabels']);
    $t->same(['seed'], $lineage['currentSkippedLabels']);
    $t->same(['seed'], $lineage['nextSkippedLabels']);
    $t->same(true, $summary249()['compoundRecursiveWindowPromotionEpochNext249']['lineageChanged']);
};

$tests['compound select window recursive limit current source next249 promotion tokens are bound'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $epoch = $plan['compoundRecursiveWindowPromotionEpochNext249'];
    $promotion = $plan['compoundNextSourcePromotionSnapshotNext245'];
    $t->same($promotion['promotionSnapshotToken'], $epoch['promotionSnapshotToken']);
    $t->same($promotion['nextSourceDeltaToken'], $epoch['nextSourceDeltaToken']);
    $t->same(2, $epoch['changedRowCount']);
    $t->same(['plugin_prime'], $epoch['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $epoch['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next249 cursor carries epoch'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $cursor = $plan['cursor'];
    $epoch = $plan['compoundRecursiveWindowPromotionEpochNext249'];
    $t->same($epoch['promotionEpochToken'], $cursor['promotionEpochTokenNext249']);
    $t->same($epoch['recursiveLineageToken'], $cursor['recursiveLineageTokenNext249']);
    $t->same($epoch['windowMetricToken'], $cursor['windowMetricTokenNext249']);
    $t->same($epoch['requiredPromotionEpochAcks'], $cursor['requiredPromotionEpochAcksNext249']);
    $t->same('held-until-recursive-lineage-window-metrics-and-promotion-epoch-match', $cursor['nextExposureNext249']);
};

$tests['compound select window recursive limit current source next249 accepts exact epoch acknowledgements'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
    $cursor['acknowledgedPromotionEpochAcksNext249'] = $plan['compoundRecursiveWindowPromotionEpochNext249']['requiredPromotionEpochAcks'];
    $again = $summary249($cursor);
    $t->same($plan['compoundRecursiveWindowPromotionEpochNext249']['promotionEpochToken'], $again['compoundRecursiveWindowPromotionEpochNext249']['promotionEpochToken']);
    $t->same($plan['compoundRecursiveWindowPromotionEpochNext249']['windowMetricToken'], $again['compoundRecursiveWindowPromotionEpochNext249']['windowMetricToken']);
};

$tests['compound select window recursive limit current source next249 rejects stale epoch token'] = static function (TestRunner $t) use ($summary249): void {
    $cursor = $summary249()['cursor'];
    $cursor['promotionEpochTokenNext249'] = str_repeat('1', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary249($cursor));
};

$tests['compound select window recursive limit current source next249 rejects stale lineage token'] = static function (TestRunner $t) use ($summary249): void {
    $cursor = $summary249()['cursor'];
    $cursor['recursiveLineageTokenNext249'] = str_repeat('2', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary249($cursor));
};

$tests['compound select window recursive limit current source next249 rejects stale metric token'] = static function (TestRunner $t) use ($summary249): void {
    $cursor = $summary249()['cursor'];
    $cursor['windowMetricTokenNext249'] = str_repeat('3', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary249($cursor));
};

$tests['compound select window recursive limit current source next249 rejects missing epoch ack'] = static function (TestRunner $t) use ($summary249): void {
    $cursor = $summary249()['cursor'];
    $cursor['acknowledgedPromotionEpochAcksNext249'] = array_slice($cursor['requiredPromotionEpochAcksNext249'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary249($cursor));
};

$tests['compound select window recursive limit current source next249 rejects unexpected epoch ack'] = static function (TestRunner $t) use ($summary249): void {
    $cursor = $summary249()['cursor'];
    $cursor['acknowledgedPromotionEpochAcksNext249'] = [...$cursor['requiredPromotionEpochAcksNext249'], 'metrics:' . str_repeat('4', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary249($cursor));
};

$tests['compound select window recursive limit current source next249 executor parity'] = static function (TestRunner $t) use ($sql249, $currentTables249, $summary249): void {
    $t->same(SQLiteSelectSql::execute($sql249, $currentTables249), $summary249()['currentRows']);
};

$tests['compound select window recursive limit current source next249 non overlap'] = static function (TestRunner $t) use ($summary249): void {
    $plan = $summary249();
    $t->contains('extends accepted next245', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-promotion-epoch-next249', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-recursive-lineage-and-window-metrics-next249', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit current source next249 generated promotion epoch ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY metric, label LIMIT 4 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext249($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
        $cursor['acknowledgedPromotionEpochAcksNext249'] = $plan['compoundRecursiveWindowPromotionEpochNext249']['requiredPromotionEpochAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext249($sql, $tables, $nextTables, $cursor);
        $epoch = $plan['compoundRecursiveWindowPromotionEpochNext249'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $epoch['nextLabels']);
        $t->same(['plugin_' . $case], $epoch['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $epoch['currentOnlyLabels']);
        $t->same([2, 2, 3, 3], $epoch['currentWindowMetrics']);
        $t->same([2, 2, 3, 3], $epoch['nextWindowMetrics']);
        $t->same(3, $epoch['requiredPromotionEpochAckCount']);
        $t->same($epoch['promotionEpochToken'], $again['compoundRecursiveWindowPromotionEpochNext249']['promotionEpochToken']);
        $t->same('held-until-recursive-lineage-window-metrics-and-promotion-epoch-match', $again['cursor']['nextExposureNext249']);
    };
}

return $tests;
