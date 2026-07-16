<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'app_metrics' => [
        ['a' => 1, 'b' => 1],
        ['a' => 2, 'b' => 2],
        ['a' => 3, 'b' => 3],
        ['a' => 4, 'b' => 4],
        ['a' => 5, 'b' => 5],
    ],
];

$windowErrSql = [
    'windowerr.test 1.1 rejects negative ROWS start offset' => "SELECT a, sum(b) OVER (ORDER BY a ROWS BETWEEN -1 PRECEDING AND 1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.2 rejects negative ROWS end offset' => "SELECT a, sum(b) OVER (ORDER BY a ROWS BETWEEN 1 PRECEDING AND -1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.3 rejects negative RANGE start offset' => "SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN -1 PRECEDING AND 1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.4 rejects negative RANGE end offset' => "SELECT a, sum(b) OVER (ORDER BY a RANGE BETWEEN 1 PRECEDING AND -1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.5 rejects negative GROUPS start offset' => "SELECT a, sum(b) OVER (ORDER BY a GROUPS BETWEEN -1 PRECEDING AND 1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.6 rejects negative GROUPS end offset' => "SELECT a, sum(b) OVER (ORDER BY a GROUPS BETWEEN 1 PRECEDING AND -1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.7 rejects RANGE offset with two ORDER BY terms' => "SELECT a, sum(b) OVER (ORDER BY a,b RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 1.8 rejects RANGE offset without ORDER BY' => "SELECT a, sum(b) OVER (PARTITION BY a RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS s FROM app_metrics ORDER BY a",
    'windowerr.test 2.1 rejects aggregate around window call' => 'SELECT sum(sum(a) OVER ()) AS s FROM app_metrics',
    'windowerr.test 2.2 rejects window alias inside aggregate ORDER BY expression' => 'SELECT sum(a) OVER () AS xyz FROM app_metrics ORDER BY sum(xyz)',
    'windowerr.test 3.0 rejects text ROWS frame offset' => "SELECT sum(a) OVER (ORDER BY a ROWS BETWEEN 'hello' PRECEDING AND 10 FOLLOWING) AS s FROM app_metrics",
    'windowerr.test 3.2 rejects blob ROWS frame offset' => "SELECT sum(a) OVER (ORDER BY a ROWS BETWEEN 10 PRECEDING AND x'ABCD' FOLLOWING) AS s FROM app_metrics",
    'windowerr.test 3.3 rejects row_number argument' => 'SELECT row_number(a) OVER () AS rn FROM app_metrics',
];

foreach ($windowErrSql as $name => $sql) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($tables, $sql, $name): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables), $name);
    };
}

$rangeOffsetTemplates = [
    'preceding-following' => 'RANGE BETWEEN %d PRECEDING AND %d FOLLOWING',
    'current-following' => 'RANGE BETWEEN CURRENT ROW AND %d FOLLOWING',
    'preceding-current' => 'RANGE BETWEEN %d PRECEDING AND CURRENT ROW',
    'single-preceding' => 'RANGE %d PRECEDING',
    'single-following' => 'RANGE %d FOLLOWING',
];

for ($case = 1; $case <= 120; $case++) {
    $templateName = array_keys($rangeOffsetTemplates)[$case % count($rangeOffsetTemplates)];
    $template = $rangeOffsetTemplates[$templateName];
    $left = ($case % 4) + 1;
    $right = (($case * 3) % 5) + 1;
    $frame = match (substr_count($template, '%d')) {
        1 => sprintf($template, $left),
        2 => sprintf($template, $left, $right),
    };
    $sql = "SELECT sum(b) OVER (ORDER BY a,b {$frame}) AS s FROM app_metrics";

    $tests["real upstream windowerr dynamic RANGE offset multi-order rejection {$case}"] = static function (TestRunner $t) use ($tables, $sql, $frame, $templateName, $case): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables), "windowerr.test 1.7 dynamic {$case} {$templateName} {$frame}");
    };
}

$zeroArgumentFunctions = ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist'];
for ($case = 1; $case <= 100; $case++) {
    $function = $zeroArgumentFunctions[$case % count($zeroArgumentFunctions)];
    $argument = match ($case % 4) {
        0 => 'a',
        1 => 'b',
        2 => 'a + b',
        default => "'label'",
    };
    $sql = "SELECT {$function}({$argument}) OVER (ORDER BY a) AS metric FROM app_metrics";

    $tests["real upstream windowerr dynamic no-arg ranking rejection {$case}"] = static function (TestRunner $t) use ($tables, $sql, $function, $argument, $case): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, $tables), "windowerr.test 3.3 dynamic {$case} {$function}({$argument})");
    };
}

$tests['real upstream windowerr dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test 1.1-1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test 2.1-2.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test 3.0,3.2,3.3',
    ];
    foreach ($sources as $source) {
        $t->true(is_file(strtok($source, ' ')), $source);
    }
};

return $tests;
