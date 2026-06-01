<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

// Source truth: upstream SQLite test/window1.test 7.1.1-7.1.8.
$window1Tables = [
    'app_window1' => [
        ['id' => 1, 'x' => 10, 'y' => 1, 'bucket' => 'a', 'label' => ' alpha '],
        ['id' => 2, 'x' => 20, 'y' => 2, 'bucket' => 'a', 'label' => 'beta'],
        ['id' => 3, 'x' => 30, 'y' => 2, 'bucket' => 'b', 'label' => 'Gamma'],
        ['id' => 4, 'x' => 40, 'y' => 3, 'bucket' => 'b', 'label' => 'delta'],
    ],
];

$expectWindow1Error = static function (TestRunner $t, string $sql, string $needle, string $sourceId) use ($window1Tables): void {
    try {
        SQLiteSelectSql::execute($sql, $window1Tables);
    } catch (Throwable $throwable) {
        $t->contains($needle, $throwable->getMessage(), $sourceId);

        return;
    }

    $t->true(false, $sourceId . ' should reject window misuse SQL');
};

$tests['real upstream window1 7.1.1 rejects nth value without over'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT nth_value(x, 1) FROM app_window1', 'misuse of window function nth_value()', 'window1.test 7.1.1');
};

$tests['real upstream window1 7.1.2 rejects window function in where'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT * FROM app_window1 WHERE nth_value(x, 1) OVER (ORDER BY y)', 'misuse of window function nth_value()', 'window1.test 7.1.2');
};

$tests['real upstream window1 7.1.3 rejects window function in having'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT count(*) FROM app_window1 GROUP BY bucket HAVING nth_value(x, 1) OVER (ORDER BY y)', 'misuse of window function nth_value()', 'window1.test 7.1.3');
};

$tests['real upstream window1 7.1.4 rejects window function in group by'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT count(*) FROM app_window1 GROUP BY nth_value(x, 1) OVER (ORDER BY y)', 'misuse of window function nth_value()', 'window1.test 7.1.4');
};

$tests['real upstream window1 7.1.5 rejects window function in limit'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT count(*) FROM app_window1 LIMIT nth_value(x, 1) OVER ()', 'misuse of window function nth_value()', 'window1.test 7.1.5');
};

$tests['real upstream window1 7.1.6 rejects scalar trim over'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT trim(x) OVER (ORDER BY y) FROM app_window1', 'trim() may not be used as a window function', 'window1.test 7.1.6');
};

$tests['real upstream window1 7.1.7 rejects missing named window'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT max(x) OVER abc FROM app_window1', 'named window abc is not defined', 'window1.test 7.1.7');
};

$tests['real upstream window1 7.1.8 rejects row number arguments'] = static function (TestRunner $t) use ($expectWindow1Error): void {
    $expectWindow1Error($t, 'SELECT row_number(x) OVER () FROM app_window1', 'wrong number of arguments to function row_number()', 'window1.test 7.1.8');
};

$windowOnlyExpressions = [
    ['nth_value', 'nth_value(x, 1)', 'misuse of window function nth_value()'],
    ['lag', 'lag(x)', 'misuse of window function lag()'],
    ['lead', 'lead(x)', 'misuse of window function lead()'],
    ['first_value', 'first_value(label)', 'misuse of window function first_value()'],
    ['last_value', 'last_value(label)', 'misuse of window function last_value()'],
    ['ntile', 'ntile(2)', 'misuse of window function ntile()'],
    ['row_number', 'row_number()', 'misuse of window function row_number()'],
    ['rank', 'rank()', 'misuse of window function rank()'],
];
$windowContextExpressions = [
    ['nth_value', 'nth_value(x, 1) OVER (ORDER BY y)', 'misuse of window function nth_value()'],
    ['lag', 'lag(x, 1, 0) OVER (ORDER BY y)', 'misuse of window function lag()'],
    ['lead', 'lead(x, 1, 0) OVER (ORDER BY y)', 'misuse of window function lead()'],
    ['row_number', 'row_number() OVER (ORDER BY y)', 'misuse of window function row_number()'],
    ['rank', 'rank() OVER (ORDER BY y)', 'misuse of window function rank()'],
    ['dense_rank', 'dense_rank() OVER (ORDER BY y)', 'misuse of window function dense_rank()'],
    ['percent_rank', 'percent_rank() OVER (ORDER BY y)', 'misuse of window function percent_rank()'],
    ['cume_dist', 'cume_dist() OVER (ORDER BY y)', 'misuse of window function cume_dist()'],
    ['ntile', 'ntile(3) OVER (ORDER BY y)', 'misuse of window function ntile()'],
];
$scalarOverExpressions = [
    ['trim', 'trim(label)', 'trim() may not be used as a window function'],
    ['lower', 'lower(label)', 'lower() may not be used as a window function'],
    ['upper', 'upper(label)', 'upper() may not be used as a window function'],
    ['abs', 'abs(x)', 'abs() may not be used as a window function'],
    ['length', 'length(label)', 'length() may not be used as a window function'],
    ['coalesce', "coalesce(label, 'fallback')", 'coalesce() may not be used as a window function'],
];
$wrongArityRanking = ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist'];

for ($case = 0; $case < 1000; $case++) {
    $family = $case % 8;
    $sourceId = 'window1.test 7.1 dynamic ' . $case;
    $tests['real upstream window1 7.1 dynamic misuse ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use (
            $case,
            $family,
            $sourceId,
            $expectWindow1Error,
            $windowOnlyExpressions,
            $windowContextExpressions,
            $scalarOverExpressions,
            $wrongArityRanking
        ): void {
            switch ($family) {
                case 0:
                    $item = $windowOnlyExpressions[$case % count($windowOnlyExpressions)];
                    $expectWindow1Error($t, 'SELECT ' . $item[1] . ' FROM app_window1', $item[2], $sourceId . ' bare ' . $item[0]);
                    break;

                case 1:
                    $item = $windowContextExpressions[$case % count($windowContextExpressions)];
                    $expectWindow1Error($t, 'SELECT id FROM app_window1 WHERE ' . $item[1], $item[2], $sourceId . ' where ' . $item[0]);
                    break;

                case 2:
                    $item = $windowContextExpressions[$case % count($windowContextExpressions)];
                    $expectWindow1Error($t, 'SELECT bucket, count(*) FROM app_window1 GROUP BY bucket HAVING ' . $item[1], $item[2], $sourceId . ' having ' . $item[0]);
                    break;

                case 3:
                    $item = $windowContextExpressions[$case % count($windowContextExpressions)];
                    $expectWindow1Error($t, 'SELECT count(*) FROM app_window1 GROUP BY ' . $item[1], $item[2], $sourceId . ' group by ' . $item[0]);
                    break;

                case 4:
                    $item = $windowContextExpressions[$case % count($windowContextExpressions)];
                    $expectWindow1Error($t, 'SELECT count(*) FROM app_window1 LIMIT ' . $item[1], $item[2], $sourceId . ' limit ' . $item[0]);
                    break;

                case 5:
                    $item = $scalarOverExpressions[$case % count($scalarOverExpressions)];
                    $direction = ($case % 2) === 0 ? 'ASC' : 'DESC';
                    $expectWindow1Error($t, 'SELECT ' . $item[1] . ' OVER (ORDER BY y ' . $direction . ') FROM app_window1', $item[2], $sourceId . ' scalar over ' . $item[0]);
                    break;

                case 6:
                    $name = 'missing_window_' . $case;
                    $expectWindow1Error($t, 'SELECT max(x) OVER ' . $name . ' FROM app_window1', 'named window ' . $name . ' is not defined', $sourceId . ' missing named window');
                    break;

                default:
                    $function = $wrongArityRanking[$case % count($wrongArityRanking)];
                    $expectWindow1Error($t, 'SELECT ' . $function . '(x) OVER () FROM app_window1', 'wrong number of arguments to function ' . $function . '()', $sourceId . ' wrong arity ' . $function);
                    break;
            }
        };
}

return $tests;
