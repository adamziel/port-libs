<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
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

$summary = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustionFence($sql, $currentTables, $nextTables, $cursor);
$tests = [];

$tests['compound select window recursive limit recursive limit exhaustion fence status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-exhaustion-fence-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-limit-window-yield-fence', $plan['dependencies'], true));
    $t->contains('recursive LIMIT exhaustion fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit recursive limit exhaustion fence fence labels'] = static function (TestRunner $t) use ($summary): void {
    $fence = $summary()['recursiveLimitExhaustionFence'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $fence['currentResultLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $fence['nextResultLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyLabels']);
};

$tests['compound select window recursive limit recursive limit exhaustion fence fence token shape'] = static function (TestRunner $t) use ($summary): void {
    $fence = $summary()['recursiveLimitExhaustionFence'];
    $t->same(64, strlen($fence['recursiveLimitFenceToken']));
    $t->same(64, strlen($fence['currentRecursiveWindowToken']));
    $t->same(64, strlen($fence['nextRecursiveWindowToken']));
    $t->same(3, $fence['requiredRecursiveLimitAckCount']);
    $t->same('recursive-current:', substr($fence['requiredRecursiveLimitAcks'][0], 0, 18));
    $t->same('recursive-next:', substr($fence['requiredRecursiveLimitAcks'][1], 0, 15));
    $t->same('recursive-limit-fence:', substr($fence['requiredRecursiveLimitAcks'][2], 0, 22));
};

$tests['compound select window recursive limit recursive limit exhaustion fence fence counts'] = static function (TestRunner $t) use ($summary): void {
    $fence = $summary()['recursiveLimitExhaustionFence'];
    $t->same(7, $fence['currentRecursiveTraceCount']);
    $t->same(7, $fence['nextRecursiveTraceCount']);
    $t->same(8, $fence['currentPreLimitCount']);
    $t->same(9, $fence['nextPreLimitCount']);
    $t->same('held-until-recursive-limit-current-and-next-window-acks-match', $fence['resumeState']);
    $t->same('compound-window-recursive-limit-exhaustion-before-next-source', $fence['yieldBoundary']);
};

$tests['compound select window recursive limit recursive limit exhaustion fence resume after all acknowledgements'] = static function (TestRunner $t) use ($summary): void {
    $first = $summary();
    $cursor = $first['cursor'];
    if (isset($cursor['requiredCurrentAcksNext232'])) {
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    }
    if (isset($cursor['requiredPromotionAcksNext235'])) {
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    }
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
    $cursor['acknowledgedRecursiveLimitAcks'] = $cursor['requiredRecursiveLimitAcks'];
    $again = $summary($cursor);
    $t->same($first['recursiveLimitExhaustionFence']['recursiveLimitFenceToken'], $again['recursiveLimitExhaustionFence']['recursiveLimitFenceToken']);
    $t->same($first['recursiveLimitExhaustionFence']['nextSourceCursor'], $again['recursiveLimitExhaustionFence']['nextSourceCursor']);
};

$tests['compound select window recursive limit recursive limit exhaustion fence rejects stale fence token'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['recursiveLimitFenceToken'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit recursive limit exhaustion fence rejects stale current recursive token'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['currentRecursiveWindowToken'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit recursive limit exhaustion fence rejects stale next recursive token'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['nextRecursiveWindowToken'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit recursive limit exhaustion fence rejects missing recursive ack'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['acknowledgedRecursiveLimitAcks'] = array_slice($cursor['requiredRecursiveLimitAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit recursive limit exhaustion fence rejects unexpected recursive ack'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['acknowledgedRecursiveLimitAcks'] = [...$cursor['requiredRecursiveLimitAcks'], 'recursive-limit-fence:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};

$tests['compound select window recursive limit recursive limit exhaustion fence executor parity'] = static function (TestRunner $t) use ($sql, $currentTables, $summary): void {
    $t->same(SQLiteSelectSql::execute($sql, $currentTables), $summary()['currentRows']);
};

$tests['compound select window recursive limit recursive limit exhaustion fence non overlap'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->contains('extends accepted resume admission', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-limit-exhaustion-fence', $plan['replanReasons'], true));
    $t->true(in_array('compound-window-yielded-next-source-held', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit recursive limit exhaustion fence generated recursive limit fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        if (isset($cursor['requiredCurrentAcksNext232'])) {
            $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        }
        if (isset($cursor['requiredPromotionAcksNext235'])) {
            $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        }
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
        $cursor['acknowledgedRecursiveLimitAcks'] = $cursor['requiredRecursiveLimitAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustionFence($sql, $tables, $nextTables, $cursor);
        $fence = $plan['recursiveLimitExhaustionFence'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $fence['nextResultLabels']);
        $t->same(['plugin_' . $case], $fence['nextOnlyLabels']);
        $t->same(3, $fence['requiredRecursiveLimitAckCount']);
        $t->same(7, $fence['currentRecursiveTraceCount']);
        $t->same(7, $fence['nextRecursiveTraceCount']);
        $t->same('held-until-recursive-limit-current-and-next-window-acks-match', $fence['resumeState']);
        $t->same($fence['recursiveLimitFenceToken'], $again['recursiveLimitExhaustionFence']['recursiveLimitFenceToken']);
    };
}

return $tests;
