<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions244 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions244 = [
    ...$currentOptions244,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables244 = ['wp_options' => $currentOptions244];
$nextTables244 = ['wp_options' => $nextOptions244];

$sql244 = <<<'SQL'
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

$summary244 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustionFence($sql244, $currentTables244, $nextTables244, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next244 status dependencies'] = static function (TestRunner $t) use ($summary244): void {
    $plan = $summary244();
    $t->same('compound-select-window-recursive-limit-current-source-next244-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-limit-window-yield-fence-next244', $plan['dependencies'], true));
    $t->contains('recursive LIMIT exhaustion fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next244 fence labels'] = static function (TestRunner $t) use ($summary244): void {
    $fence = $summary244()['recursiveLimitExhaustionFenceNext244'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $fence['currentResultLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $fence['nextResultLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyLabels']);
};

$tests['compound select window recursive limit current source next244 fence token shape'] = static function (TestRunner $t) use ($summary244): void {
    $fence = $summary244()['recursiveLimitExhaustionFenceNext244'];
    $t->same(64, strlen($fence['recursiveLimitFenceToken']));
    $t->same(64, strlen($fence['currentRecursiveWindowToken']));
    $t->same(64, strlen($fence['nextRecursiveWindowToken']));
    $t->same(3, $fence['requiredRecursiveLimitAckCount']);
    $t->same('recursive-current:', substr($fence['requiredRecursiveLimitAcks'][0], 0, 18));
    $t->same('recursive-next:', substr($fence['requiredRecursiveLimitAcks'][1], 0, 15));
    $t->same('recursive-limit-fence:', substr($fence['requiredRecursiveLimitAcks'][2], 0, 22));
};

$tests['compound select window recursive limit current source next244 fence counts'] = static function (TestRunner $t) use ($summary244): void {
    $fence = $summary244()['recursiveLimitExhaustionFenceNext244'];
    $t->same(7, $fence['currentRecursiveTraceCount']);
    $t->same(7, $fence['nextRecursiveTraceCount']);
    $t->same(8, $fence['currentPreLimitCount']);
    $t->same(9, $fence['nextPreLimitCount']);
    $t->same('held-until-recursive-limit-current-and-next-window-acks-match', $fence['resumeState']);
    $t->same('compound-window-recursive-limit-next244-exhaustion-before-next-source', $fence['yieldBoundary']);
};

$tests['compound select window recursive limit current source next244 resume after all acknowledgements'] = static function (TestRunner $t) use ($summary244): void {
    $first = $summary244();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
    $cursor['acknowledgedRecursiveLimitAcksNext244'] = $cursor['requiredRecursiveLimitAcksNext244'];
    $again = $summary244($cursor);
    $t->same($first['recursiveLimitExhaustionFenceNext244']['recursiveLimitFenceToken'], $again['recursiveLimitExhaustionFenceNext244']['recursiveLimitFenceToken']);
    $t->same($first['recursiveLimitExhaustionFenceNext244']['nextSourceCursor'], $again['recursiveLimitExhaustionFenceNext244']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next244 rejects stale fence token'] = static function (TestRunner $t) use ($summary244): void {
    $cursor = $summary244()['cursor'];
    $cursor['recursiveLimitFenceTokenNext244'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary244($cursor));
};

$tests['compound select window recursive limit current source next244 rejects stale current recursive token'] = static function (TestRunner $t) use ($summary244): void {
    $cursor = $summary244()['cursor'];
    $cursor['currentRecursiveWindowTokenNext244'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary244($cursor));
};

$tests['compound select window recursive limit current source next244 rejects stale next recursive token'] = static function (TestRunner $t) use ($summary244): void {
    $cursor = $summary244()['cursor'];
    $cursor['nextRecursiveWindowTokenNext244'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary244($cursor));
};

$tests['compound select window recursive limit current source next244 rejects missing recursive ack'] = static function (TestRunner $t) use ($summary244): void {
    $cursor = $summary244()['cursor'];
    $cursor['acknowledgedRecursiveLimitAcksNext244'] = array_slice($cursor['requiredRecursiveLimitAcksNext244'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary244($cursor));
};

$tests['compound select window recursive limit current source next244 rejects unexpected recursive ack'] = static function (TestRunner $t) use ($summary244): void {
    $cursor = $summary244()['cursor'];
    $cursor['acknowledgedRecursiveLimitAcksNext244'] = [...$cursor['requiredRecursiveLimitAcksNext244'], 'recursive-limit-fence:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary244($cursor));
};

$tests['compound select window recursive limit current source next244 executor parity'] = static function (TestRunner $t) use ($sql244, $currentTables244, $summary244): void {
    $t->same(SQLiteSelectSql::execute($sql244, $currentTables244), $summary244()['currentRows']);
};

$tests['compound select window recursive limit current source next244 non overlap'] = static function (TestRunner $t) use ($summary244): void {
    $plan = $summary244();
    $t->contains('extends accepted next241', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-limit-exhaustion-fence-next244', $plan['replanReasons'], true));
    $t->true(in_array('compound-window-yielded-next-source-held-next244', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next244 generated recursive limit fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustionFence($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
        $cursor['acknowledgedRecursiveLimitAcksNext244'] = $cursor['requiredRecursiveLimitAcksNext244'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustionFence($sql, $tables, $nextTables, $cursor);
        $fence = $plan['recursiveLimitExhaustionFenceNext244'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $fence['nextResultLabels']);
        $t->same(['plugin_' . $case], $fence['nextOnlyLabels']);
        $t->same(3, $fence['requiredRecursiveLimitAckCount']);
        $t->same(7, $fence['currentRecursiveTraceCount']);
        $t->same(7, $fence['nextRecursiveTraceCount']);
        $t->same('held-until-recursive-limit-current-and-next-window-acks-match', $fence['resumeState']);
        $t->same($fence['recursiveLimitFenceToken'], $again['recursiveLimitExhaustionFenceNext244']['recursiveLimitFenceToken']);
    };
}

return $tests;
