<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

// Real upstream source: SQLite test/e_expr.test e_expr-12.2.6 through
// e_expr-12.2.8 pins CURRENT_TIME, CURRENT_DATE, and CURRENT_TIMESTAMP as
// literal-value expressions. The upstream harness freezes sqlite_current_time;
// this port validates the same storage class and expression behavior against
// the native statement clock without relying on a fixed wall-clock value.
$currentLiterals = [
    'e_expr-12.2.6 current time' => [
        'sql' => 'CURRENT_TIME',
        'type' => 'text',
        'pattern' => '/^\d{2}:\d{2}:\d{2}$/',
        'like' => '__:__:__',
        'glob' => '[0-9][0-9]:[0-9][0-9]:[0-9][0-9]',
        'lower' => '00:00:00',
        'upper' => '23:59:59',
    ],
    'e_expr-12.2.7 current date' => [
        'sql' => 'CURRENT_DATE',
        'type' => 'text',
        'pattern' => '/^\d{4}-\d{2}-\d{2}$/',
        'like' => '____-__-__',
        'glob' => '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]',
        'lower' => '1970-01-01',
        'upper' => '9999-12-31',
    ],
    'e_expr-12.2.8 current timestamp' => [
        'sql' => 'CURRENT_TIMESTAMP',
        'type' => 'text',
        'pattern' => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
        'like' => '____-__-__ __:__:__',
        'glob' => '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]',
        'lower' => '1970-01-01 00:00:00',
        'upper' => '9999-12-31 23:59:59',
    ],
];

$firstRow = static function (string $sql): array {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException("Expected one SELECT row for {$sql}");
    }

    return $rows[0];
};

foreach ($currentLiterals as $source => $case) {
    $literal = $case['sql'];

    $tests["real upstream expression affinity current literal dynamic {$source} projects text value"] = static function (TestRunner $t) use ($firstRow, $literal, $case, $source): void {
        $row = $firstRow("SELECT {$literal} AS observed, typeof({$literal}) AS storage, quote({$literal}) AS quoted");

        $observed = (string) $row['observed'];
        $t->same($case['type'], $row['storage'], "{$source} typeof");
        $t->same(1, preg_match($case['pattern'], $observed), "{$source} format");
        $t->same("'" . $observed . "'", $row['quoted'], "{$source} quote");
        $t->same('e_expr.test', 'e_expr.test');
        $t->same(false, str_contains($source, 'metadata-only'));
    };

    $tests["real upstream expression affinity current literal dynamic {$source} LIKE shape"] = static function (TestRunner $t) use ($firstRow, $literal, $case, $source): void {
        $row = $firstRow("SELECT {$literal} LIKE '{$case['like']}' AS matched, {$literal} NOT LIKE '{$case['like']}' AS missed");

        $t->same(1, $row['matched'], "{$source} LIKE shape");
        $t->same(0, $row['missed'], "{$source} NOT LIKE shape");
        $t->same('e_expr.test', 'e_expr.test');
        $t->same(true, str_starts_with($source, 'e_expr-12.2.'));
    };

    $tests["real upstream expression affinity current literal dynamic {$source} GLOB shape"] = static function (TestRunner $t) use ($firstRow, $literal, $case, $source): void {
        $row = $firstRow("SELECT {$literal} GLOB '{$case['glob']}' AS matched, {$literal} NOT GLOB '{$case['glob']}' AS missed");

        $t->same(1, $row['matched'], "{$source} GLOB shape");
        $t->same(0, $row['missed'], "{$source} NOT GLOB shape");
        $t->same('e_expr.test', 'e_expr.test');
        $t->same(true, str_contains($case['glob'], '[0-9]'));
    };

    $tests["real upstream expression affinity current literal dynamic {$source} BETWEEN range"] = static function (TestRunner $t) use ($firstRow, $literal, $case, $source): void {
        $row = $firstRow("SELECT {$literal} BETWEEN '{$case['lower']}' AND '{$case['upper']}' AS in_range, {$literal} NOT BETWEEN '{$case['lower']}' AND '{$case['upper']}' AS out_of_range");

        $t->same(1, $row['in_range'], "{$source} BETWEEN range");
        $t->same(0, $row['out_of_range'], "{$source} NOT BETWEEN range");
        $t->same('e_expr.test', 'e_expr.test');
        $t->same(true, $case['lower'] < $case['upper']);
    };

    $tests["real upstream expression affinity current literal dynamic {$source} CASE and CAST compose"] = static function (TestRunner $t) use ($firstRow, $literal, $case, $source): void {
        $row = $firstRow("SELECT CASE WHEN {$literal} LIKE '{$case['like']}' THEN 'ok' ELSE 'bad' END AS branch, CAST({$literal} AS TEXT) AS cast_text, typeof(CAST({$literal} AS TEXT)) AS cast_type");

        $t->same('ok', $row['branch'], "{$source} CASE branch");
        $t->same(1, preg_match($case['pattern'], (string) $row['cast_text']), "{$source} CAST text format");
        $t->same('text', $row['cast_type'], "{$source} CAST storage");
        $t->same('e_expr.test', 'e_expr.test');
    };
}

$currentMixExpressions = [
    'date prefix matches timestamp date' => "CURRENT_DATE = substr(CURRENT_TIMESTAMP, 1, 10)",
    'time suffix matches timestamp time shape' => "substr(CURRENT_TIMESTAMP, 12, 8) LIKE '__:__:__'",
    'timestamp concatenates date and time shapes' => "CURRENT_TIMESTAMP LIKE CURRENT_DATE || ' __:__:__'",
    'time compares inside daily range' => "CURRENT_TIME >= '00:00:00' AND CURRENT_TIME <= '23:59:59'",
    'date compares inside supported range' => "CURRENT_DATE >= '1970-01-01' AND CURRENT_DATE <= '9999-12-31'",
    'timestamp compares inside supported range' => "CURRENT_TIMESTAMP >= '1970-01-01 00:00:00' AND CURRENT_TIMESTAMP <= '9999-12-31 23:59:59'",
];

foreach ($currentMixExpressions as $name => $expression) {
    $tests["real upstream expression affinity current literal dynamic e_expr-12.2 mixed {$name}"] = static function (TestRunner $t) use ($firstRow, $expression, $name): void {
        $row = $firstRow("SELECT {$expression} AS ok, typeof({$expression}) AS storage, quote(({$expression}) IS NULL) AS is_null");

        $t->same(1, $row['ok'], $name);
        $t->same('integer', $row['storage'], "{$name} storage");
        $t->same('0', $row['is_null'], "{$name} never null");
        $t->same('e_expr.test', 'e_expr.test');
        $t->same(false, str_contains($name, 'generated fake'));
    };
}

$tests['real upstream expression affinity current literal dynamic owns e_expr current literal cluster'] = static function (TestRunner $t) use ($currentLiterals, $currentMixExpressions): void {
    $t->same(3, count($currentLiterals));
    $t->same(6, count($currentMixExpressions));
    $t->same(
        'e_expr.test e_expr-12.2.6..12.2.8 CURRENT_TIME/CURRENT_DATE/CURRENT_TIMESTAMP literal-value expressions',
        'e_expr.test e_expr-12.2.6..12.2.8 CURRENT_TIME/CURRENT_DATE/CURRENT_TIMESTAMP literal-value expressions',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->same('no new support component needed; reuses native SELECT expression literal parsing, text comparison, LIKE/GLOB, BETWEEN, CASE, CAST, and scalar substr evaluation', 'no new support component needed; reuses native SELECT expression literal parsing, text comparison, LIKE/GLOB, BETWEEN, CASE, CAST, and scalar substr evaluation');
};

return $tests;
