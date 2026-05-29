<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions239 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions239 = [
    ...$currentOptions239,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables239 = ['wp_options' => $currentOptions239];
$nextTables239 = ['wp_options' => $nextOptions239];

$sql239 = <<<'SQL'
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

$summary239 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext239($sql239, $currentTables239, $nextTables239, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next239 status dependencies'] = static function (TestRunner $t) use ($summary239): void {
    $plan = $summary239();
    $t->same('compound-select-window-recursive-limit-current-source-next239-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-limit-resume-fence-current-source-next239', $plan['dependencies'], true));
    $t->contains('LIMIT/OFFSET resume promotion', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next239 limit fence labels'] = static function (TestRunner $t) use ($summary239): void {
    $fence = $summary239()['compoundLimitResumeFenceNext239'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $fence['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $fence['nextLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $fence['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next239 token lengths and counts'] = static function (TestRunner $t) use ($summary239): void {
    $fence = $summary239()['compoundLimitResumeFenceNext239'];
    $t->same(64, strlen($fence['resumeToken']));
    $t->same(64, strlen($fence['currentLimitSignature']));
    $t->same(64, strlen($fence['recursiveWindowSignature']));
    $t->same(3, $fence['requiredResumeAckCount']);
    $t->same(3, $fence['currentLimitCount']);
    $t->same(3, $fence['nextLimitCount']);
};

$tests['compound select window recursive limit current source next239 ack prefix shape'] = static function (TestRunner $t) use ($summary239): void {
    $acks = $summary239()['compoundLimitResumeFenceNext239']['requiredResumeAcks'];
    $t->same('limit:', substr($acks[0], 0, 6));
    $t->same('recursive-window:', substr($acks[1], 0, 17));
    $t->same('promotion:', substr($acks[2], 0, 10));
    $t->same(70, strlen($acks[0]));
    $t->same(81, strlen($acks[1]));
    $t->same(74, strlen($acks[2]));
};

$tests['compound select window recursive limit current source next239 recursive metadata'] = static function (TestRunner $t) use ($summary239): void {
    $fence = $summary239()['compoundLimitResumeFenceNext239'];
    $t->same(['seed', 'seed:2'], $fence['recursiveSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $fence['recursiveEmittedLabels']);
    $t->same('held-until-current-limit-recursive-window-signatures-match', $fence['resumeState']);
    $t->same('compound-recursive-window-next239-limit-resume-fence', $fence['yieldBoundary']);
};

$tests['compound select window recursive limit current source next239 cursor replay after exact acks'] = static function (TestRunner $t) use ($summary239): void {
    $first = $summary239();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $cursor['acknowledgedResumeAcksNext239'] = $cursor['requiredResumeAcksNext239'];
    $again = $summary239($cursor);
    $t->same($first['compoundLimitResumeFenceNext239']['resumeToken'], $again['compoundLimitResumeFenceNext239']['resumeToken']);
    $t->same($first['compoundLimitResumeFenceNext239']['nextSourceCursor'], $again['compoundLimitResumeFenceNext239']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next239 rejects stale resume token'] = static function (TestRunner $t) use ($summary239): void {
    $cursor = $summary239()['cursor'];
    $cursor['compoundLimitResumeTokenNext239'] = str_repeat('9', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary239($cursor));
};

$tests['compound select window recursive limit current source next239 rejects stale limit signature'] = static function (TestRunner $t) use ($summary239): void {
    $cursor = $summary239()['cursor'];
    $cursor['currentLimitSignatureNext239'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary239($cursor));
};

$tests['compound select window recursive limit current source next239 rejects stale recursive window signature'] = static function (TestRunner $t) use ($summary239): void {
    $cursor = $summary239()['cursor'];
    $cursor['recursiveWindowSignatureNext239'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary239($cursor));
};

$tests['compound select window recursive limit current source next239 rejects missing resume ack'] = static function (TestRunner $t) use ($summary239): void {
    $cursor = $summary239()['cursor'];
    $cursor['acknowledgedResumeAcksNext239'] = array_slice($cursor['requiredResumeAcksNext239'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary239($cursor));
};

$tests['compound select window recursive limit current source next239 rejects unexpected resume ack'] = static function (TestRunner $t) use ($summary239): void {
    $cursor = $summary239()['cursor'];
    $cursor['acknowledgedResumeAcksNext239'] = [...$cursor['requiredResumeAcksNext239'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary239($cursor));
};

$tests['compound select window recursive limit current source next239 executor parity'] = static function (TestRunner $t) use ($sql239, $currentTables239, $summary239): void {
    $t->same(SQLiteSelectSql::execute($sql239, $currentTables239), $summary239()['currentRows']);
};

$tests['compound select window recursive limit current source next239 non overlap'] = static function (TestRunner $t) use ($summary239): void {
    $plan = $summary239();
    $t->contains('extends accepted next235', $plan['non_overlap']);
    $t->true(in_array('compound-limit-resume-fence-next239', $plan['replanReasons'], true));
    $t->true(in_array('current-output-recursive-window-signature-next239', $plan['replanReasons'], true));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source next239 generated resume fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext239($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $cursor['acknowledgedResumeAcksNext239'] = $cursor['requiredResumeAcksNext239'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext239($sql, $tables, $nextTables, $cursor);
        $fence = $plan['compoundLimitResumeFenceNext239'];

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3:4', 'rewrite_' . $case], $fence['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $fence['nextLabels']);
        $t->same(['plugin_' . $case], $fence['nextOnlyLabels']);
        $t->same(3, $fence['requiredResumeAckCount']);
        $t->same('held-until-current-limit-recursive-window-signatures-match', $fence['resumeState']);
        $t->same($fence['resumeToken'], $again['compoundLimitResumeFenceNext239']['resumeToken']);
        $t->same($plan['compoundLimitResumeFenceNext239']['nextSourceCursor'], $again['compoundLimitResumeFenceNext239']['nextSourceCursor']);
    };
}

return $tests;
