<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan;
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

$summary254 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan::compare($sql254, $currentTables254, $nextTables254, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next254 status dependencies'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $t->same('compound-select-window-recursive-limit-current-source-next254-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-limit-receipt-next254', $plan['dependencies'], true));
    $t->contains('compound/window/recursive receipt gate', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next254 receipt tokens'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptNext254'];
    $t->same(64, strlen($receipt['compoundReceiptToken']));
    $t->same(64, strlen($receipt['recursiveWindowBoundaryToken']));
    $t->same(3, $receipt['requiredCompoundReceiptAckCount']);
};

$tests['compound select window recursive limit current source next254 final compound shape'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptNext254'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $receipt['compoundOperators']);
    $t->same(4, $receipt['finalLimit']);
    $t->same(1, $receipt['finalOffset']);
    $t->same('held-until-compound-recursive-window-limit-receipts', $receipt['nextExposure']);
};

$tests['compound select window recursive limit current source next254 current next labels'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptNext254'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $receipt['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $receipt['nextLabels']);
    $t->same(true, $receipt['labelBoundaryChanged']);
};

$tests['compound select window recursive limit current source next254 page frame receipt'] = static function (TestRunner $t) use ($summary254): void {
    $receipt = $summary254()['compoundWindowRecursiveLimitReceiptNext254'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($receipt['currentPageFrame'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($receipt['nextPageFrame'], 'label'));
    $t->same([2, 2, 3, 3], array_column($receipt['currentPageFrame'], 'metric'));
    $t->same([2, 2, 3, 3], array_column($receipt['nextPageFrame'], 'metric'));
    $t->same(true, $receipt['windowFrameChanged']);
};

$tests['compound select window recursive limit current source next254 recursive lineage'] = static function (TestRunner $t) use ($summary254): void {
    $lineage = $summary254()['compoundWindowRecursiveLimitReceiptNext254']['recursiveLineage'];
    $t->same(['seed'], $lineage['skipped']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $lineage['emitted']);
};

$tests['compound select window recursive limit current source next254 cursor fields'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $receipt = $plan['compoundWindowRecursiveLimitReceiptNext254'];
    $t->same($receipt['compoundReceiptToken'], $plan['cursor']['compoundReceiptTokenNext254']);
    $t->same($receipt['recursiveWindowBoundaryToken'], $plan['cursor']['recursiveWindowBoundaryTokenNext254']);
    $t->same($receipt['requiredCompoundReceiptAcks'], $plan['cursor']['requiredCompoundReceiptAcksNext254']);
};

$tests['compound select window recursive limit current source next254 accepts complete cursor'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
    $cursor['acknowledgedCompoundReceiptAcksNext254'] = $plan['compoundWindowRecursiveLimitReceiptNext254']['requiredCompoundReceiptAcks'];
    $again = $summary254($cursor);
    $t->same($plan['compoundWindowRecursiveLimitReceiptNext254']['compoundReceiptToken'], $again['compoundWindowRecursiveLimitReceiptNext254']['compoundReceiptToken']);
};

$tests['compound select window recursive limit current source next254 rejects stale compound token'] = static function (TestRunner $t) use ($summary254): void {
    $cursor = $summary254()['cursor'];
    $cursor['compoundReceiptTokenNext254'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source next254 rejects stale boundary token'] = static function (TestRunner $t) use ($summary254): void {
    $cursor = $summary254()['cursor'];
    $cursor['recursiveWindowBoundaryTokenNext254'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source next254 rejects incomplete receipt acknowledgements'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCompoundReceiptAcksNext254'] = array_slice($plan['compoundWindowRecursiveLimitReceiptNext254']['requiredCompoundReceiptAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source next254 rejects unexpected receipt acknowledgement'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCompoundReceiptAcksNext254'] = [...$plan['compoundWindowRecursiveLimitReceiptNext254']['requiredCompoundReceiptAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary254($cursor));
};

$tests['compound select window recursive limit current source next254 executor parity'] = static function (TestRunner $t) use ($sql254, $currentTables254, $summary254): void {
    $t->same(SQLiteSelectSql::execute($sql254, $currentTables254), $summary254()['currentRows']);
};

$tests['compound select window recursive limit current source next254 non overlap'] = static function (TestRunner $t) use ($summary254): void {
    $plan = $summary254();
    $t->contains('extends accepted next250', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-limit-receipt-next254', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-compound-window-recursive-receipts-next254', $plan['replanReasons'], true));
};

foreach (range(1, 74) as $case) {
    $tests['compound select window recursive limit current source next254 generated receipt gate ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan::compare($sql, $tables, $nextTables);
        $receipt = $plan['compoundWindowRecursiveLimitReceiptNext254'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
        $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
        $cursor['acknowledgedCompoundReceiptAcksNext254'] = $receipt['requiredCompoundReceiptAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan::compare($sql, $tables, $nextTables, $cursor);

        $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $receipt['compoundOperators']);
        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $receipt['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $receipt['nextLabels']);
        $t->same(3, $receipt['requiredCompoundReceiptAckCount']);
        $t->same(true, $receipt['windowFrameChanged']);
        $t->same($receipt['compoundReceiptToken'], $again['compoundWindowRecursiveLimitReceiptNext254']['compoundReceiptToken']);
    };
}

return $tests;
