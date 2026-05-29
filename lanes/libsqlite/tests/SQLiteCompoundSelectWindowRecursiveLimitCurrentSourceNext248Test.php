<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions248 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions248 = [
    ...$currentOptions248,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables248 = ['wp_options' => $currentOptions248];
$nextTables248 = ['wp_options' => $nextOptions248];

$sql248 = <<<'SQL'
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

$summary248 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext248($sql248, $currentTables248, $nextTables248, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next248 status dependencies'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $t->same('compound-select-window-recursive-limit-current-source-next248-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-next-source-promotion-next248', $plan['dependencies'], true));
    $t->contains('next-source promotion receipt', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next248 preserves inherited rowsets'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $plan['compound']['operators']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
};

$tests['compound select window recursive limit current source next248 promotion token shape'] = static function (TestRunner $t) use ($summary248): void {
    $promotion = $summary248()['compoundNextSourcePromotionFenceNext248'];
    $t->same(64, strlen($promotion['nextPromotionToken']));
    $t->same(64, strlen($promotion['nextDeltaSignature']));
    $t->same(2, $promotion['requiredNextPromotionReceiptCount']);
    $t->same(2, count($promotion['requiredNextPromotionReceipts']));
};

$tests['compound select window recursive limit current source next248 delta labels'] = static function (TestRunner $t) use ($summary248): void {
    $promotion = $summary248()['compoundNextSourcePromotionFenceNext248'];
    $t->same(['plugin_prime'], $promotion['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $promotion['currentOnlyLabels']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $promotion['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $promotion['nextLabels']);
};

$tests['compound select window recursive limit current source next248 next frames preserve metrics'] = static function (TestRunner $t) use ($summary248): void {
    $frames = $summary248()['compoundNextSourcePromotionFenceNext248']['nextFrames'];
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($frames, 'label'));
    $t->same([2, 2, 3, 3], array_column($frames, 'metric'));
    $t->same([1, 2, 3, 4], array_column($frames, 'ordinal'));
    $t->same([5, 3, 2, 4], array_column($frames, 'id'));
};

$tests['compound select window recursive limit current source next248 promotion binds inherited replay tokens'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $promotion = $plan['compoundNextSourcePromotionFenceNext248'];
    $t->same($plan['compoundWindowReplayFenceNext243']['windowReplayToken'], $promotion['windowReplayToken']);
    $t->same($plan['compoundWindowReplayFenceNext243']['currentReplaySignature'], $promotion['currentReplaySignature']);
    $t->same($plan['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken'], $promotion['spilloverDrainToken']);
};

$tests['compound select window recursive limit current source next248 cursor carries promotion receipt'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $cursor = $plan['cursor'];
    $promotion = $plan['compoundNextSourcePromotionFenceNext248'];
    $t->same($promotion['nextPromotionToken'], $cursor['nextPromotionTokenNext248']);
    $t->same($promotion['nextDeltaSignature'], $cursor['nextDeltaSignatureNext248']);
    $t->same($promotion['requiredNextPromotionReceipts'], $cursor['requiredNextPromotionReceiptsNext248']);
    $t->same('held-until-next-source-promotion-receipts-match', $cursor['nextPromotionExposureNext248']);
};

$tests['compound select window recursive limit current source next248 accepts exact promotion receipts'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
    $again = $summary248($cursor);
    $t->same($plan['compoundNextSourcePromotionFenceNext248']['nextPromotionToken'], $again['compoundNextSourcePromotionFenceNext248']['nextPromotionToken']);
    $t->same($plan['compoundNextSourcePromotionFenceNext248']['nextDeltaSignature'], $again['compoundNextSourcePromotionFenceNext248']['nextDeltaSignature']);
};

$tests['compound select window recursive limit current source next248 rejects stale promotion token'] = static function (TestRunner $t) use ($summary248): void {
    $cursor = $summary248()['cursor'];
    $cursor['nextPromotionTokenNext248'] = str_repeat('d', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary248($cursor));
};

$tests['compound select window recursive limit current source next248 rejects stale delta signature'] = static function (TestRunner $t) use ($summary248): void {
    $cursor = $summary248()['cursor'];
    $cursor['nextDeltaSignatureNext248'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary248($cursor));
};

$tests['compound select window recursive limit current source next248 rejects missing receipt'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedNextPromotionReceiptsNext248'] = array_slice($plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'], 0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => $summary248($cursor));
};

$tests['compound select window recursive limit current source next248 rejects unexpected receipt'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedNextPromotionReceiptsNext248'] = [...$plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'], str_repeat('f', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary248($cursor));
};

$tests['compound select window recursive limit current source next248 executor parity'] = static function (TestRunner $t) use ($sql248, $currentTables248, $summary248): void {
    $t->same(SQLiteSelectSql::execute($sql248, $currentTables248), $summary248()['currentRows']);
};

$tests['compound select window recursive limit current source next248 non overlap'] = static function (TestRunner $t) use ($summary248): void {
    $plan = $summary248();
    $t->contains('extends accepted next243', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-next-source-promotion-receipt-next248', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-current-replay-and-next-delta-receipts-match-next248', $plan['replanReasons'], true));
};

foreach (range(1, 70) as $case) {
    $tests['compound select window recursive limit current source next248 generated promotion receipt ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext248($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext248($sql, $tables, $nextTables, $cursor);

        $t->same(['plugin_' . $case], $plan['compoundNextSourcePromotionFenceNext248']['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $plan['compoundNextSourcePromotionFenceNext248']['currentOnlyLabels']);
        $t->same([2, 2, 3, 3], array_column($plan['compoundNextSourcePromotionFenceNext248']['nextFrames'], 'metric'));
        $t->same(2, $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceiptCount']);
        $t->same($plan['compoundNextSourcePromotionFenceNext248']['nextPromotionToken'], $again['compoundNextSourcePromotionFenceNext248']['nextPromotionToken']);
        $t->same('held-until-next-source-promotion-receipts-match', $again['cursor']['nextPromotionExposureNext248']);
    };
}

return $tests;
