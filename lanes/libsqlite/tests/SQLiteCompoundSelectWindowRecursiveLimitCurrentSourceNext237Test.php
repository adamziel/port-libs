<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions237 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions237 = [
    ...$currentOptions237,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables237 = ['wp_options' => $currentOptions237];
$nextTables237 = ['wp_options' => $nextOptions237];

$sql237 = <<<'SQL'
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

$summary237 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue($sql237, $currentTables237, $nextTables237, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next237 status dependencies'] = static function (TestRunner $t) use ($summary237): void {
    $plan = $summary237();
    $t->same('compound-select-window-recursive-limit-current-source-next237-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-limit-offset-next237',
        'sqlite-select-sql-rank-row-number-window-intersect-except-next237',
        'sqlite-compound-current-source-dequeue-token-fence-next237',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next237 compound metadata'] = static function (TestRunner $t) use ($summary237): void {
    $compound = $summary237()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'label'], $compound['orderColumns']);
    $t->same([4, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next237 current and next rows'] = static function (TestRunner $t) use ($summary237): void {
    $plan = $summary237();
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same([2, 2, 3, 3], array_column($plan['currentRows'], 'metric'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 2, 3, 3], array_column($plan['nextRows'], 'metric'));
};

$tests['compound select window recursive limit current source next237 recursive dequeue'] = static function (TestRunner $t) use ($summary237): void {
    $queue = $summary237()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $queue['currentEmittedLabels']);
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next237 window metadata'] = static function (TestRunner $t) use ($summary237): void {
    $windows = $summary237()['windows'];
    $t->same(['rank', 'row_number'], $windows['functions']);
    $t->same([0, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5, 6], array_map('intval', $windows['currentMetrics']));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5, 5, 6], array_map('intval', $windows['nextMetrics']));
};

$tests['compound select window recursive limit current source next237 source window trace'] = static function (TestRunner $t) use ($summary237): void {
    $window = $summary237()['sourceWindow'];
    $t->same(['seed:2'], $window['currentSkippedLabels']);
    $t->same(['seed:2'], $window['nextSkippedLabels']);
    $t->same(['blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $window['currentTruncatedLabels']);
    $t->same(['rewrite_rules', 'seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $window['nextTruncatedLabels']);
    $t->same(['plugin_prime'], $window['nextOnlyAdmittedLabels']);
    $t->same(['rewrite_rules'], $window['currentOnlyAdmittedLabels']);
    $t->true($window['intersectExceptBoundaryChanged']);
};

$tests['compound select window recursive limit current source next237 dequeue fence'] = static function (TestRunner $t) use ($summary237): void {
    $dequeue = $summary237()['currentSourceDequeueNext237'];
    $t->same(64, strlen($dequeue['currentDequeueToken']));
    $t->same(6, $dequeue['requiredAckCount']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $dequeue['currentEmittedLabels']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $dequeue['currentFinalPageLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $dequeue['nextFinalPageLabels']);
    $t->same('held-until-current-recursive-dequeue-acks', $dequeue['nextExposure']);
    $t->same('compound-window-next237-current-recursive-dequeue-fences-next-source', $dequeue['yieldBoundary']);
};

$tests['compound select window recursive limit current source next237 cursor accepts dequeue acks'] = static function (TestRunner $t) use ($summary237): void {
    $plan = $summary237();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $again = $summary237($cursor);
    $t->same($plan['currentSourceDequeueNext237']['currentDequeueToken'], $again['currentSourceDequeueNext237']['currentDequeueToken']);
    $t->same('held-until-current-recursive-dequeue-acks', $again['cursor']['nextExposureNext237']);
};

$tests['compound select window recursive limit current source next237 rejects stale token'] = static function (TestRunner $t) use ($summary237): void {
    $cursor = $summary237()['cursor'];
    $cursor['currentToken'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary237($cursor));
};

$tests['compound select window recursive limit current source next237 rejects stale dequeue token'] = static function (TestRunner $t) use ($summary237): void {
    $cursor = $summary237()['cursor'];
    $cursor['currentDequeueTokenNext237'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary237($cursor));
};

$tests['compound select window recursive limit current source next237 rejects missing dequeue ack'] = static function (TestRunner $t) use ($summary237): void {
    $plan = $summary237();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = array_slice($plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'], 0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $summary237($cursor));
};

$tests['compound select window recursive limit current source next237 executor parity'] = static function (TestRunner $t) use ($sql237, $currentTables237, $summary237): void {
    $t->same(SQLiteSelectSql::execute($sql237, $currentTables237), $summary237()['currentRows']);
};

$tests['compound select window recursive limit current source next237 replan reasons'] = static function (TestRunner $t) use ($summary237): void {
    $plan = $summary237();
    $t->contains('avoids accepted next233', $plan['non_overlap']);
    $t->true(in_array('compound-union-all-intersect-except-rank-current-source-next237', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-dequeue-before-window-compound-next237', $plan['replanReasons'], true));
    $t->true(in_array('next-source-row-number-shift-held-by-current-dequeue-acks-next237', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next237 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables237): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric, label LIMIT 4 OFFSET 1",
        $currentTables237,
        $currentTables237,
    ));
};

$tests['compound select window recursive limit current source next237 rejects missing row number'] = static function (TestRunner $t) use ($currentTables237): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options) EXCEPT SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric, label LIMIT 4 OFFSET 1",
        $currentTables237,
        $currentTables237,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next237 generated dequeue fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['nextRows'], 'label'));
        $t->same(6, $plan['currentSourceDequeueNext237']['requiredAckCount']);
        $t->same(['plugin_' . $case], $plan['currentSourceDequeueNext237']['nextOnlyLabels']);
        $t->same('seed_' . $case . ':2', $plan['currentSourceDequeueNext237']['currentEmittedLabels'][0]);
        $t->same($plan['currentSourceDequeueNext237']['currentDequeueToken'], $again['currentSourceDequeueNext237']['currentDequeueToken']);
    };
}

return $tests;
