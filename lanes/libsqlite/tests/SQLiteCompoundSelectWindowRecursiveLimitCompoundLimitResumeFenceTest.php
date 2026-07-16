<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsCompoundLimitResumeFence = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptionsCompoundLimitResumeFence = [
    ...$currentOptionsCompoundLimitResumeFence,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTablesCompoundLimitResumeFence = ['wp_options' => $currentOptionsCompoundLimitResumeFence];
$nextTablesCompoundLimitResumeFence = ['wp_options' => $nextOptionsCompoundLimitResumeFence];

$sqlCompoundLimitResumeFence = <<<'SQL'
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

$summaryCompoundLimitResumeFence = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundLimitResumeFence($sqlCompoundLimitResumeFence, $currentTablesCompoundLimitResumeFence, $nextTablesCompoundLimitResumeFence, $cursor);
$tests = [];

$tests['compound select window recursive limit current source compound-limit-resume-fence status dependencies'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $plan = $summaryCompoundLimitResumeFence();
    $t->same('compound-select-window-recursive-limit-current-source-compound-limit-resume-fence-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-limit-resume-fence-current-source', $plan['dependencies'], true));
    $t->contains('LIMIT/OFFSET resume promotion', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence limit fence labels'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $fence = $summaryCompoundLimitResumeFence()['compoundLimitResumeFence'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $fence['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $fence['nextLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $fence['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence token lengths and counts'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $fence = $summaryCompoundLimitResumeFence()['compoundLimitResumeFence'];
    $t->same(64, strlen($fence['resumeToken']));
    $t->same(64, strlen($fence['currentLimitSignature']));
    $t->same(64, strlen($fence['recursiveWindowSignature']));
    $t->same(3, $fence['requiredResumeAckCount']);
    $t->same(3, $fence['currentLimitCount']);
    $t->same(3, $fence['nextLimitCount']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence ack prefix shape'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $acks = $summaryCompoundLimitResumeFence()['compoundLimitResumeFence']['requiredResumeAcks'];
    $t->same('limit:', substr($acks[0], 0, 6));
    $t->same('recursive-window:', substr($acks[1], 0, 17));
    $t->same('promotion:', substr($acks[2], 0, 10));
    $t->same(70, strlen($acks[0]));
    $t->same(81, strlen($acks[1]));
    $t->same(74, strlen($acks[2]));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence recursive metadata'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $fence = $summaryCompoundLimitResumeFence()['compoundLimitResumeFence'];
    $t->same(['seed', 'seed:2'], $fence['recursiveSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $fence['recursiveEmittedLabels']);
    $t->same('held-until-current-limit-recursive-window-signatures-match', $fence['resumeState']);
    $t->same('compound-recursive-window-compound-limit-resume-fence-limit-resume-fence', $fence['yieldBoundary']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence cursor replay after exact acks'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $first = $summaryCompoundLimitResumeFence();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
    $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
    $cursor['acknowledgedResumeAcks'] = $cursor['requiredResumeAcks'];
    $again = $summaryCompoundLimitResumeFence($cursor);
    $t->same($first['compoundLimitResumeFence']['resumeToken'], $again['compoundLimitResumeFence']['resumeToken']);
    $t->same($first['compoundLimitResumeFence']['nextSourceCursor'], $again['compoundLimitResumeFence']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence rejects stale resume token'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $cursor = $summaryCompoundLimitResumeFence()['cursor'];
    $cursor['compoundLimitResumeToken'] = str_repeat('9', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryCompoundLimitResumeFence($cursor));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence rejects stale limit signature'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $cursor = $summaryCompoundLimitResumeFence()['cursor'];
    $cursor['currentLimitSignature'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryCompoundLimitResumeFence($cursor));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence rejects stale recursive window signature'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $cursor = $summaryCompoundLimitResumeFence()['cursor'];
    $cursor['recursiveWindowSignature'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryCompoundLimitResumeFence($cursor));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence rejects missing resume ack'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $cursor = $summaryCompoundLimitResumeFence()['cursor'];
    $cursor['acknowledgedResumeAcks'] = array_slice($cursor['requiredResumeAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summaryCompoundLimitResumeFence($cursor));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence rejects unexpected resume ack'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $cursor = $summaryCompoundLimitResumeFence()['cursor'];
    $cursor['acknowledgedResumeAcks'] = [...$cursor['requiredResumeAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summaryCompoundLimitResumeFence($cursor));
};

$tests['compound select window recursive limit current source compound-limit-resume-fence executor parity'] = static function (TestRunner $t) use ($sqlCompoundLimitResumeFence, $currentTablesCompoundLimitResumeFence, $summaryCompoundLimitResumeFence): void {
    $t->same(SQLiteSelectSql::execute($sqlCompoundLimitResumeFence, $currentTablesCompoundLimitResumeFence), $summaryCompoundLimitResumeFence()['currentRows']);
};

$tests['compound select window recursive limit current source compound-limit-resume-fence non overlap'] = static function (TestRunner $t) use ($summaryCompoundLimitResumeFence): void {
    $plan = $summaryCompoundLimitResumeFence();
    $t->contains('extends accepted recursive-window-promotion-barrier', $plan['non_overlap']);
    $t->true(in_array('compound-limit-resume-fence', $plan['replanReasons'], true));
    $t->true(in_array('current-output-recursive-window-signature', $plan['replanReasons'], true));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source compound-limit-resume-fence generated resume fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundLimitResumeFence($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
        $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
        $cursor['acknowledgedResumeAcks'] = $cursor['requiredResumeAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundLimitResumeFence($sql, $tables, $nextTables, $cursor);
        $fence = $plan['compoundLimitResumeFence'];

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $fence['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $fence['nextLabels']);
        $t->same(['plugin_' . $case], $fence['nextOnlyLabels']);
        $t->same(3, $fence['requiredResumeAckCount']);
        $t->same('held-until-current-limit-recursive-window-signatures-match', $fence['resumeState']);
        $t->same($fence['resumeToken'], $again['compoundLimitResumeFence']['resumeToken']);
        $t->same($plan['compoundLimitResumeFence']['nextSourceCursor'], $again['compoundLimitResumeFence']['nextSourceCursor']);
    };
}

return $tests;
