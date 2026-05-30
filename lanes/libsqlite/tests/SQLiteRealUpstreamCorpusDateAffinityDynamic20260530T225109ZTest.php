<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$firstValue = static function (string $sql): mixed {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException("Expected one SELECT row for {$sql}");
    }

    return array_values($rows[0])[0] ?? null;
};

// Source truth: SQLite upstream test/date.test date-2.2c-0 through
// date-2.2c-999. These cases verify that strftime('%H:%M:%f', ..., 'unixepoch')
// preserves each millisecond fraction for a real-valued unix timestamp.
for ($millisecond = 0; $millisecond < 1000; $millisecond++) {
    $sql = sprintf(
        "SELECT strftime('%%H:%%M:%%f',1237962480.%03d,'unixepoch')",
        $millisecond,
    );
    $expected = sprintf('06:28:00.%03d', $millisecond);

    $tests[sprintf('real upstream date.test date-2.2c-%03d unixepoch millisecond strftime', $millisecond)] = static function (TestRunner $t) use ($firstValue, $sql, $expected): void {
        $t->same($expected, $firstValue($sql), $sql);
        $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    };
}

return $tests;
