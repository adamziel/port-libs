<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions240 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions240 = [
    ...$currentOptions240,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables240 = ['wp_options' => $currentOptions240];
$nextTables240 = ['wp_options' => $nextOptions240];

$sql240 = <<<'SQL'
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

$summary240 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageSpilloverDrain($sql240, $currentTables240, $nextTables240, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next240 status dependencies'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $t->same('compound-select-window-recursive-limit-current-source-next240-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-final-page-spillover-drain-next240', $plan['dependencies'], true));
    $t->contains('final-page spillover acknowledgement fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next240 keeps next237 base fields'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $plan['compound']['operators']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(6, $plan['currentSourceDequeueNext237']['requiredAckCount']);
};

$tests['compound select window recursive limit current source next240 spillover shape'] = static function (TestRunner $t) use ($summary240): void {
    $spillover = $summary240()['compoundFinalPageSpilloverDrainNext240'];
    $t->same(64, strlen($spillover['spilloverDrainToken']));
    $t->same(5, $spillover['requiredSpilloverAckCount']);
    $t->same(5, count($spillover['requiredSpilloverAcks']));
    $t->same('held-until-current-compound-spillover-drained', $spillover['nextExposure']);
    $t->same('compound-window-next240-final-limit-spillover-drain', $spillover['yieldBoundary']);
};

$tests['compound select window recursive limit current source next240 spillover labels'] = static function (TestRunner $t) use ($summary240): void {
    $spillover = $summary240()['compoundFinalPageSpilloverDrainNext240'];
    $t->same(['seed:2'], $spillover['currentSkippedLabels']);
    $t->same(['blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $spillover['currentTruncatedLabels']);
    $t->same(['seed:2', 'blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $spillover['currentSpilloverLabels']);
    $t->same(['plugin_prime'], $spillover['nextOnlyFinalLabels']);
    $t->same(['rewrite_rules'], $spillover['currentOnlyFinalLabels']);
};

$tests['compound select window recursive limit current source next240 row counts'] = static function (TestRunner $t) use ($summary240): void {
    $spillover = $summary240()['compoundFinalPageSpilloverDrainNext240'];
    $t->same(9, $spillover['currentPreLimitRowCount']);
    $t->same(4, $spillover['currentFinalRowCount']);
    $t->same(4, $spillover['nextFinalRowCount']);
    $t->same(5, $spillover['spilloverRowCount']);
};

$tests['compound select window recursive limit current source next240 cursor carries spillover fence'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $cursor = $plan['cursor'];
    $t->same($plan['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken'], $cursor['spilloverDrainTokenNext240']);
    $t->same($plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'], $cursor['requiredSpilloverAcksNext240']);
    $t->same('held-until-current-compound-spillover-drained', $cursor['spilloverExposureNext240']);
};

$tests['compound select window recursive limit current source next240 accepts spillover acknowledgements'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $again = $summary240($cursor);
    $t->same($plan['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken'], $again['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken']);
};

$tests['compound select window recursive limit current source next240 rejects stale spillover token'] = static function (TestRunner $t) use ($summary240): void {
    $cursor = $summary240()['cursor'];
    $cursor['spilloverDrainTokenNext240'] = str_repeat('4', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary240($cursor));
};

$tests['compound select window recursive limit current source next240 rejects incomplete spillover acknowledgements'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSpilloverAcksNext240'] = array_slice($plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'], 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $summary240($cursor));
};

$tests['compound select window recursive limit current source next240 rejects unexpected spillover acknowledgement'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSpilloverAcksNext240'] = [...$plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'], str_repeat('5', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary240($cursor));
};

$tests['compound select window recursive limit current source next240 executor parity'] = static function (TestRunner $t) use ($sql240, $currentTables240, $summary240): void {
    $t->same(SQLiteSelectSql::execute($sql240, $currentTables240), $summary240()['currentRows']);
};

$tests['compound select window recursive limit current source next240 non overlap'] = static function (TestRunner $t) use ($summary240): void {
    $plan = $summary240();
    $t->contains('extends accepted next237', $plan['non_overlap']);
    $t->true(in_array('compound-final-limit-spillover-drain-next240', $plan['replanReasons'], true));
    $t->true(in_array('current-source-window-spillover-holds-next-source-next240', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next240 generated spillover drain ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageSpilloverDrain($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageSpilloverDrain($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['nextRows'], 'label'));
        $t->same(['seed_' . $case . ':2', 'blog_' . $case, 'seed_' . $case . ':2:3:4:5', 'seed_' . $case . ':2:3:4:5:6', 'seed_' . $case . ':2:3:4:5:6:7'], $plan['compoundFinalPageSpilloverDrainNext240']['currentSpilloverLabels']);
        $t->same(5, $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAckCount']);
        $t->same($plan['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken'], $again['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken']);
        $t->same('held-until-current-compound-spillover-drained', $again['cursor']['spilloverExposureNext240']);
    };
}

return $tests;
