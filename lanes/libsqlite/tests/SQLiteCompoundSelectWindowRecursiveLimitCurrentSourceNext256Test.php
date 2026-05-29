<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions256 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions256 = [
    ...$currentOptions256,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables256 = ['wp_options' => $currentOptions256];
$nextTables256 = ['wp_options' => $nextOptions256];

$sql256 = <<<'SQL'
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

$summary256 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext256($sql256, $currentTables256, $nextTables256, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next256 status dependencies'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $t->same('compound-select-window-recursive-limit-current-source-next256-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-current-limit-resume-next256', $plan['dependencies'], true));
    $t->contains('current final-page resume receipt', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next256 inherits next248 promotion'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $t->same(['plugin_prime'], $plan['compoundNextSourcePromotionFenceNext248']['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $plan['compoundNextSourcePromotionFenceNext248']['currentOnlyLabels']);
    $t->same($plan['compoundNextSourcePromotionFenceNext248']['nextPromotionToken'], $plan['compoundCurrentLimitResumeFenceNext256']['nextPromotionToken']);
};

$tests['compound select window recursive limit current source next256 current final page signature'] = static function (TestRunner $t) use ($summary256): void {
    $fence = $summary256()['compoundCurrentLimitResumeFenceNext256'];
    $t->same(64, strlen($fence['currentLimitResumeToken']));
    $t->same(64, strlen($fence['currentLimitPageSignature']));
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $fence['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $fence['nextLabels']);
};

$tests['compound select window recursive limit current source next256 current frames and receipt count'] = static function (TestRunner $t) use ($summary256): void {
    $fence = $summary256()['compoundCurrentLimitResumeFenceNext256'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($fence['currentFrames'], 'label'));
    $t->same([2, 2, 3, 3], array_column($fence['currentFrames'], 'metric'));
    $t->same(5, $fence['requiredCurrentLimitResumeReceiptCount']);
    $t->same(5, count($fence['requiredCurrentLimitResumeReceipts']));
};

$tests['compound select window recursive limit current source next256 recursive exhaustion receipt metadata'] = static function (TestRunner $t) use ($summary256): void {
    $fence = $summary256()['compoundCurrentLimitResumeFenceNext256'];
    $t->same(['seed'], $fence['recursiveSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $fence['recursiveEmittedLabels']);
    $t->same(0, $fence['recursiveLimitRemaining']);
    $t->same(0, $fence['recursiveOffsetRemaining']);
};

$tests['compound select window recursive limit current source next256 cursor carries resume receipts'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $fence = $plan['compoundCurrentLimitResumeFenceNext256'];
    $t->same($fence['currentLimitResumeToken'], $plan['cursor']['currentLimitResumeTokenNext256']);
    $t->same($fence['currentLimitPageSignature'], $plan['cursor']['currentLimitPageSignatureNext256']);
    $t->same($fence['requiredCurrentLimitResumeReceipts'], $plan['cursor']['requiredCurrentLimitResumeReceiptsNext256']);
    $t->same('held-until-current-limit-resume-receipts-match', $plan['cursor']['currentLimitResumeExposureNext256']);
};

$tests['compound select window recursive limit current source next256 accepts exact resume receipts'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
    $cursor['acknowledgedCurrentLimitResumeReceiptsNext256'] = $plan['compoundCurrentLimitResumeFenceNext256']['requiredCurrentLimitResumeReceipts'];
    $again = $summary256($cursor);
    $t->same($plan['compoundCurrentLimitResumeFenceNext256']['currentLimitResumeToken'], $again['compoundCurrentLimitResumeFenceNext256']['currentLimitResumeToken']);
};

$tests['compound select window recursive limit current source next256 rejects stale resume token'] = static function (TestRunner $t) use ($summary256): void {
    $cursor = $summary256()['cursor'];
    $cursor['currentLimitResumeTokenNext256'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary256($cursor));
};

$tests['compound select window recursive limit current source next256 rejects stale page signature'] = static function (TestRunner $t) use ($summary256): void {
    $cursor = $summary256()['cursor'];
    $cursor['currentLimitPageSignatureNext256'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary256($cursor));
};

$tests['compound select window recursive limit current source next256 rejects missing resume receipt'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentLimitResumeReceiptsNext256'] = array_slice($plan['compoundCurrentLimitResumeFenceNext256']['requiredCurrentLimitResumeReceipts'], 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $summary256($cursor));
};

$tests['compound select window recursive limit current source next256 rejects unexpected resume receipt'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentLimitResumeReceiptsNext256'] = [...$plan['compoundCurrentLimitResumeFenceNext256']['requiredCurrentLimitResumeReceipts'], str_repeat('c', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary256($cursor));
};

$tests['compound select window recursive limit current source next256 executor parity'] = static function (TestRunner $t) use ($sql256, $currentTables256, $summary256): void {
    $t->same(SQLiteSelectSql::execute($sql256, $currentTables256), $summary256()['currentRows']);
};

$tests['compound select window recursive limit current source next256 non overlap'] = static function (TestRunner $t) use ($summary256): void {
    $plan = $summary256();
    $t->contains('extends accepted next248', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-current-limit-resume-receipt-next256', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-current-limit-page-and-recursive-exhaustion-match-next256', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next256 generated resume receipt ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext256($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedNextPromotionReceiptsNext248'] = $plan['compoundNextSourcePromotionFenceNext248']['requiredNextPromotionReceipts'];
        $cursor['acknowledgedCurrentLimitResumeReceiptsNext256'] = $plan['compoundCurrentLimitResumeFenceNext256']['requiredCurrentLimitResumeReceipts'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext256($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $plan['compoundCurrentLimitResumeFenceNext256']['currentLabels']);
        $t->same([2, 2, 3, 3], array_column($plan['compoundCurrentLimitResumeFenceNext256']['currentFrames'], 'metric'));
        $t->same(5, $plan['compoundCurrentLimitResumeFenceNext256']['requiredCurrentLimitResumeReceiptCount']);
        $t->same(0, $plan['compoundCurrentLimitResumeFenceNext256']['recursiveLimitRemaining']);
        $t->same($plan['compoundCurrentLimitResumeFenceNext256']['currentLimitResumeToken'], $again['compoundCurrentLimitResumeFenceNext256']['currentLimitResumeToken']);
        $t->same('held-until-current-limit-resume-receipts-match', $again['cursor']['currentLimitResumeExposureNext256']);
    };
}

return $tests;
