<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
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

$summary = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue($sql, $currentTables, $nextTables, $cursor);
$tests = [];

$tests['compound select window recursive limit current source current-source-dequeue status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-dequeue-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-limit-offset-current-source-dequeue',
        'sqlite-select-sql-rank-row-number-window-intersect-except-current-source-dequeue',
        'sqlite-compound-current-source-dequeue-token-fence',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source current-source-dequeue compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'label'], $compound['orderColumns']);
    $t->same([4, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source current-source-dequeue current and next rows'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same([2, 2, 3, 3], array_column($plan['currentRows'], 'metric'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 2, 3, 3], array_column($plan['nextRows'], 'metric'));
};

$tests['compound select window recursive limit current source current-source-dequeue recursive dequeue'] = static function (TestRunner $t) use ($summary): void {
    $queue = $summary()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $queue['currentEmittedLabels']);
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source current-source-dequeue window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['rank', 'row_number'], $windows['functions']);
    $t->same([0, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([1, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5, 6], array_map('intval', $windows['currentMetrics']));
    $t->same([1, 2, 2, 3, 3, 4, 4, 5, 5, 6], array_map('intval', $windows['nextMetrics']));
};

$tests['compound select window recursive limit current source current-source-dequeue source window trace'] = static function (TestRunner $t) use ($summary): void {
    $window = $summary()['sourceWindow'];
    $t->same(['seed:2'], $window['currentSkippedLabels']);
    $t->same(['seed:2'], $window['nextSkippedLabels']);
    $t->same(['blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $window['currentTruncatedLabels']);
    $t->same(['rewrite_rules', 'seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $window['nextTruncatedLabels']);
    $t->same(['plugin_prime'], $window['nextOnlyAdmittedLabels']);
    $t->same(['rewrite_rules'], $window['currentOnlyAdmittedLabels']);
    $t->true($window['intersectExceptBoundaryChanged']);
};

$tests['compound select window recursive limit current source current-source-dequeue dequeue fence'] = static function (TestRunner $t) use ($summary): void {
    $dequeue = $summary()['currentSourceDequeue'];
    $t->same(64, strlen($dequeue['currentDequeueToken']));
    $t->same(6, $dequeue['requiredAckCount']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $dequeue['currentEmittedLabels']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $dequeue['currentFinalPageLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $dequeue['nextFinalPageLabels']);
    $t->same('held-until-current-recursive-dequeue-acks', $dequeue['nextExposure']);
    $t->same('compound-window-current-recursive-dequeue-fences-next-source', $dequeue['yieldBoundary']);
};

$tests['compound select window recursive limit current source current-source-dequeue cursor accepts dequeue acks'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcks'] = $plan['currentSourceDequeue']['requiredCurrentDequeueAcks'];
    $again = $summary($cursor);
    $t->same($plan['currentSourceDequeue']['currentDequeueToken'], $again['currentSourceDequeue']['currentDequeueToken']);
    $t->same('held-until-current-recursive-dequeue-acks', $again['cursor']['nextExposure']);
};

$tests['compound select window recursive limit current source current-source-dequeue rejects stale token'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['currentToken'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit current source current-source-dequeue rejects stale dequeue token'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['currentDequeueToken'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit current source current-source-dequeue rejects missing dequeue ack'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcks'] = array_slice($plan['currentSourceDequeue']['requiredCurrentDequeueAcks'], 0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit current source current-source-dequeue executor parity'] = static function (TestRunner $t) use ($sql, $currentTables, $summary): void {
    $t->same(SQLiteSelectSql::execute($sql, $currentTables), $summary()['currentRows']);
};

$tests['compound select window recursive limit current source current-source-dequeue replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->contains('avoids accepted final-order-resume', $plan['non_overlap']);
    $t->true(in_array('compound-union-all-intersect-except-rank-current-source-dequeue', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-dequeue-before-window-compound-current-source', $plan['replanReasons'], true));
    $t->true(in_array('next-source-row-number-shift-held-by-current-dequeue-acks', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source current-source-dequeue rejects missing intersect'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric, label LIMIT 4 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit current source current-source-dequeue rejects missing row number'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 130) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options) EXCEPT SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options ORDER BY metric, label LIMIT 4 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source current-source-dequeue generated dequeue fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $cursor['acknowledgedCurrentDequeueAcks'] = $plan['currentSourceDequeue']['requiredCurrentDequeueAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['nextRows'], 'label'));
        $t->same(6, $plan['currentSourceDequeue']['requiredAckCount']);
        $t->same(['plugin_' . $case], $plan['currentSourceDequeue']['nextOnlyLabels']);
        $t->same('seed_' . $case . ':2', $plan['currentSourceDequeue']['currentEmittedLabels'][0]);
        $t->same($plan['currentSourceDequeue']['currentDequeueToken'], $again['currentSourceDequeue']['currentDequeueToken']);
    };
}

return $tests;
