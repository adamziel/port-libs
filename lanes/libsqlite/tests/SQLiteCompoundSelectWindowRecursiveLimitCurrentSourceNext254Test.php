<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions254 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions254 = [
    ...$currentOptions254,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables254 = ['wp_options' => $currentOptions254];
$nextTables254 = ['wp_options' => $nextOptions254];

$sql254 = <<<'SQL'
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

$summary254 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePromotionReceipt($sql254, $currentTables254, $nextTables254, $cursor);
$tests = [];

$tests['compound select window recursive limit current source promotion-receipt status dependencies'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $t->same('compound-select-window-recursive-limit-current-source-promotion-receipt-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-limit-receipt-promotion-receipt', $plan['dependencies'], true));
    $t->contains('compound/window/recursive receipt gate', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source promotion-receipt receipt tokens'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
    $t->same(64, strlen($receipt['compoundReceiptToken']));
    $t->same(64, strlen($receipt['recursiveWindowBoundaryToken']));
    $t->same(3, $receipt['requiredCompoundReceiptAckCount']);
};

$tests['compound select window recursive limit current source promotion-receipt final compound shape'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $receipt['compoundOperators']);
    $t->same(4, $receipt['finalLimit']);
    $t->same(1, $receipt['finalOffset']);
    $t->same('held-until-compound-recursive-window-limit-receipts', $receipt['nextExposure']);
};

$tests['compound select window recursive limit current source promotion-receipt current next labels'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $receipt['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $receipt['nextLabels']);
    $t->same(true, $receipt['labelBoundaryChanged']);
};

$tests['compound select window recursive limit current source promotion-receipt page frame receipt'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($receipt['currentPageFrame'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($receipt['nextPageFrame'], 'label'));
    $t->same([2, 2, 3, 3], array_column($receipt['currentPageFrame'], 'metric'));
    $t->same([2, 2, 3, 3], array_column($receipt['nextPageFrame'], 'metric'));
    $t->same(true, $receipt['windowFrameChanged']);
};

$tests['compound select window recursive limit current source promotion-receipt recursive lineage'] = static function (TestRunner $t) use ($summary254): void {
    $lineage = $summary254()['compoundWindowRecursiveLimitReceiptPromotionReceipt']['recursiveLineage'];
    $t->same(['seed'], $lineage['skipped']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $lineage['emitted']);
};

$tests['compound select window recursive limit current source promotion-receipt cursor fields'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $receipt = $plan['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
    $t->same($receipt['compoundReceiptToken'], $plan['cursor']['compoundReceiptTokenPromotionReceipt']);
    $t->same($receipt['recursiveWindowBoundaryToken'], $plan['cursor']['recursiveWindowBoundaryTokenPromotionReceipt']);
    $t->same($receipt['requiredCompoundReceiptAcks'], $plan['cursor']['requiredCompoundReceiptAcksPromotionReceipt']);
};

$tests['compound select window recursive limit current source promotion-receipt accepts complete cursor'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $cursor['acknowledgedNextPageAdmissionAcksNextPageAdmission'] = $plan['compoundCurrentSourceNextPageAdmissionNextPageAdmission']['requiredNextPageAdmissionAcks'];
    $cursor['acknowledgedCompoundReceiptAcksPromotionReceipt'] = $plan['compoundWindowRecursiveLimitReceiptPromotionReceipt']['requiredCompoundReceiptAcks'];
    $again = $summary254($cursor);
    $t->same($plan['compoundWindowRecursiveLimitReceiptPromotionReceipt']['compoundReceiptToken'], $again['compoundWindowRecursiveLimitReceiptPromotionReceipt']['compoundReceiptToken']);
};

$tests['compound select window recursive limit current source promotion-receipt rejects stale compound token'] = static function (TestRunner $t) use ($summary254): void {
    $cursor = $summary254()['cursor'];
    $cursor['compoundReceiptTokenPromotionReceipt'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source promotion-receipt rejects stale boundary token'] = static function (TestRunner $t) use ($summary254): void {
    $cursor = $summary254()['cursor'];
    $cursor['recursiveWindowBoundaryTokenPromotionReceipt'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source promotion-receipt rejects incomplete receipt acknowledgements'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCompoundReceiptAcksPromotionReceipt'] = array_slice($plan['compoundWindowRecursiveLimitReceiptPromotionReceipt']['requiredCompoundReceiptAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source promotion-receipt rejects unexpected receipt acknowledgement'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCompoundReceiptAcksPromotionReceipt'] = [...$plan['compoundWindowRecursiveLimitReceiptPromotionReceipt']['requiredCompoundReceiptAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source promotion-receipt executor parity'] = static function (TestRunner $t) use ($sql254, $currentTables254, $summary254): void {
    $t->same(SQLiteSelectSql::execute($sql254, $currentTables254), $summary254()['currentRows']);
};

$tests['compound select window recursive limit current source promotion-receipt non overlap'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $t->contains('extends accepted next-page-admission', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-limit-receipt-promotion-receipt', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-compound-window-recursive-receipts-promotion-receipt', $plan['replanReasons'], true));
};

foreach (range(1, 74) as $case) {
    $tests['compound select window recursive limit current source promotion-receipt generated receipt gate ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePromotionReceipt($sql, $tables, $nextTables);
        $receipt = $plan['compoundWindowRecursiveLimitReceiptPromotionReceipt'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
        $cursor['acknowledgedNextPageAdmissionAcksNextPageAdmission'] = $plan['compoundCurrentSourceNextPageAdmissionNextPageAdmission']['requiredNextPageAdmissionAcks'];
        $cursor['acknowledgedCompoundReceiptAcksPromotionReceipt'] = $receipt['requiredCompoundReceiptAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePromotionReceipt($sql, $tables, $nextTables, $cursor);

        $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $receipt['compoundOperators']);
        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $receipt['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $receipt['nextLabels']);
        $t->same(3, $receipt['requiredCompoundReceiptAckCount']);
        $t->same(true, $receipt['windowFrameChanged']);
        $t->same($receipt['compoundReceiptToken'], $again['compoundWindowRecursiveLimitReceiptPromotionReceipt']['compoundReceiptToken']);
    };
}

return $tests;
