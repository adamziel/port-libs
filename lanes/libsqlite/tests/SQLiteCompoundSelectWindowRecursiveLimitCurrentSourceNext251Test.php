<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Plan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions251 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions251 = [
    ...$currentOptions251,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables251 = ['wp_options' => $currentOptions251];
$nextTables251 = ['wp_options' => $nextOptions251];

$sql251 = <<<'SQL'
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

$summary251 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Plan::compare($sql251, $currentTables251, $nextTables251, $cursor);
$completeCursor251 = static function (array $plan): array {
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
    $cursor['acknowledgedDeltaAuditReceiptsNext251'] = $plan['compoundNextSourceDeltaAuditFenceNext251']['requiredDeltaAuditReceipts'];

    return $cursor;
};
$tests = [];

$tests['compound select window recursive limit current source next251 status dependencies'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $t->same('compound-select-window-recursive-limit-current-source-next251-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-next-source-delta-audit-next251', $plan['dependencies'], true));
    $t->contains('operator/final-page delta audit fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next251 preserves next248 rowsets'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(['plugin_prime'], $plan['compoundNextSourceDeltaAuditFenceNext251']['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $plan['compoundNextSourceDeltaAuditFenceNext251']['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next251 audit token shape'] = static function (TestRunner $t) use ($summary251): void {
    $audit = $summary251()['compoundNextSourceDeltaAuditFenceNext251'];
    $t->same(64, strlen($audit['nextDeltaAuditToken']));
    $t->same(64, strlen($audit['nextDeltaAuditSignature']));
    $t->same(11, $audit['requiredDeltaAuditReceiptCount']);
    $t->same(11, count($audit['requiredDeltaAuditReceipts']));
};

$tests['compound select window recursive limit current source next251 operator trace'] = static function (TestRunner $t) use ($summary251): void {
    $trace = $summary251()['compoundNextSourceDeltaAuditFenceNext251']['operatorTrace'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], array_column($trace, 'operator'));
    $t->same([1, 2, 3], array_column($trace, 'ordinal'));
};

$tests['compound select window recursive limit current source next251 final page rows'] = static function (TestRunner $t) use ($summary251): void {
    $rows = $summary251()['compoundNextSourceDeltaAuditFenceNext251']['finalPageRows'];
    $t->same(['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next'], array_column($rows, 'source'));
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4', 'plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([2, 3, 3, 4, 5, 3, 2, 4], array_column($rows, 'id'));
};

$tests['compound select window recursive limit current source next251 audit binds next248 promotion'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $audit = $plan['compoundNextSourceDeltaAuditFenceNext251'];
    $promotion = $plan['compoundNextSourcePromotionFenceNext248'];
    $t->same($promotion['nextPromotionToken'], $audit['promotionToken']);
    $t->same($promotion['nextDeltaSignature'], $audit['nextDeltaSignature']);
    $t->same($promotion['requiredNextPromotionReceiptCount'] + 9, $audit['requiredDeltaAuditReceiptCount']);
};

$tests['compound select window recursive limit current source next251 cursor carries audit receipts'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $audit = $plan['compoundNextSourceDeltaAuditFenceNext251'];
    $cursor = $plan['cursor'];
    $t->same($audit['nextDeltaAuditToken'], $cursor['nextDeltaAuditTokenNext251']);
    $t->same($audit['nextDeltaAuditSignature'], $cursor['nextDeltaAuditSignatureNext251']);
    $t->same($audit['requiredDeltaAuditReceipts'], $cursor['requiredDeltaAuditReceiptsNext251']);
    $t->same('held-until-compound-operator-final-page-audit-matches', $cursor['deltaAuditExposureNext251']);
};

$tests['compound select window recursive limit current source next251 accepts exact audit receipts'] = static function (TestRunner $t) use ($summary251, $completeCursor251): void {
    $plan = $summary251();
    $again = $summary251($completeCursor251($plan));
    $t->same($plan['compoundNextSourceDeltaAuditFenceNext251']['nextDeltaAuditToken'], $again['compoundNextSourceDeltaAuditFenceNext251']['nextDeltaAuditToken']);
    $t->same($plan['compoundNextSourceDeltaAuditFenceNext251']['nextDeltaAuditSignature'], $again['compoundNextSourceDeltaAuditFenceNext251']['nextDeltaAuditSignature']);
};

$tests['compound select window recursive limit current source next251 rejects stale audit token'] = static function (TestRunner $t) use ($summary251): void {
    $cursor = $summary251()['cursor'];
    $cursor['nextDeltaAuditTokenNext251'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary251($cursor));
};

$tests['compound select window recursive limit current source next251 rejects stale audit signature'] = static function (TestRunner $t) use ($summary251): void {
    $cursor = $summary251()['cursor'];
    $cursor['nextDeltaAuditSignatureNext251'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary251($cursor));
};

$tests['compound select window recursive limit current source next251 rejects missing audit receipt'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedDeltaAuditReceiptsNext251'] = array_slice($plan['compoundNextSourceDeltaAuditFenceNext251']['requiredDeltaAuditReceipts'], 0, -1);
    $t->throws(InvalidArgumentException::class, static fn () => $summary251($cursor));
};

$tests['compound select window recursive limit current source next251 rejects unexpected audit receipt'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedDeltaAuditReceiptsNext251'] = [...$plan['compoundNextSourceDeltaAuditFenceNext251']['requiredDeltaAuditReceipts'], str_repeat('c', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary251($cursor));
};

$tests['compound select window recursive limit current source next251 rejects non list audit receipts'] = static function (TestRunner $t) use ($summary251): void {
    $cursor = $summary251()['cursor'];
    $cursor['acknowledgedDeltaAuditReceiptsNext251'] = 'not-a-list';
    $t->throws(InvalidArgumentException::class, static fn () => $summary251($cursor));
};

$tests['compound select window recursive limit current source next251 executor parity'] = static function (TestRunner $t) use ($sql251, $currentTables251, $summary251): void {
    $t->same(SQLiteSelectSql::execute($sql251, $currentTables251), $summary251()['currentRows']);
};

$tests['compound select window recursive limit current source next251 non overlap'] = static function (TestRunner $t) use ($summary251): void {
    $plan = $summary251();
    $t->contains('layers an operator and final-page ordinal audit', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-next-source-delta-audit-next251', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-compound-operator-and-final-page-audit-next251', $plan['replanReasons'], true));
};

foreach (range(1, 70) as $case) {
    $tests['compound select window recursive limit current source next251 generated delta audit ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Plan::compare($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
        $cursor['acknowledgedDeltaAuditReceiptsNext251'] = $plan['compoundNextSourceDeltaAuditFenceNext251']['requiredDeltaAuditReceipts'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Plan::compare($sql, $tables, $nextTables, $cursor);

        $audit = $plan['compoundNextSourceDeltaAuditFenceNext251'];
        $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], array_column($audit['operatorTrace'], 'operator'));
        $t->same(['plugin_' . $case], $audit['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $audit['currentOnlyLabels']);
        $t->same(11, $audit['requiredDeltaAuditReceiptCount']);
        $t->same($audit['nextDeltaAuditToken'], $again['compoundNextSourceDeltaAuditFenceNext251']['nextDeltaAuditToken']);
        $t->same('held-until-compound-operator-final-page-audit-matches', $again['cursor']['deltaAuditExposureNext251']);
    };
}

return $tests;
