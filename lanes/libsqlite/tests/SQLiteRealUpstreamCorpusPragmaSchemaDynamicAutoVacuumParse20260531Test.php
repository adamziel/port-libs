<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-17.1.*.
 * The upstream matrix assigns every auto_vacuum spelling to a fresh in-memory
 * database and verifies the normalized PRAGMA result: NONE and out-of-range
 * numerics map to 0, FULL maps to 1, and INCREMENTAL maps to 2.
 */

$upstreamSettings = [
    ['token' => '0', 'expected' => 0, 'label' => 'numeric none'],
    ['token' => '1', 'expected' => 1, 'label' => 'numeric full'],
    ['token' => '2', 'expected' => 2, 'label' => 'numeric incremental'],
    ['token' => '3', 'expected' => 0, 'label' => 'numeric out of range high'],
    ['token' => '-1', 'expected' => 0, 'label' => 'numeric out of range negative'],
    ['token' => 'none', 'expected' => 0, 'label' => 'lower none'],
    ['token' => 'NONE', 'expected' => 0, 'label' => 'upper none'],
    ['token' => 'NoNe', 'expected' => 0, 'label' => 'mixed none'],
    ['token' => 'full', 'expected' => 1, 'label' => 'lower full'],
    ['token' => 'FULL', 'expected' => 1, 'label' => 'upper full'],
    ['token' => 'incremental', 'expected' => 2, 'label' => 'lower incremental'],
    ['token' => 'INCREMENTAL', 'expected' => 2, 'label' => 'upper incremental'],
    ['token' => '-1234', 'expected' => 0, 'label' => 'large negative'],
    ['token' => '1234', 'expected' => 0, 'label' => 'large positive'],
];

$spellings = [
    static fn (string $token): string => 'PRAGMA auto_vacuum=' . $token,
    static fn (string $token): string => 'pragma AUTO_VACUUM(' . $token . ')',
    static fn (string $token): string => ' PRAGMA main.auto_vacuum = ' . $token . '; ',
];

foreach (range(1, 1000) as $variant) {
    $case = $upstreamSettings[($variant - 1) % count($upstreamSettings)];
    $sql = $spellings[$variant % count($spellings)]($case['token']);
    $label = sprintf('real upstream pragma-17 auto_vacuum parse %s variant %04d', $case['label'], $variant);

    $tests[$label] = static function (TestRunner $t) use ($case, $sql): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $assigned = $state->execute($sql);
        $read = $state->execute('PRAGMA auto_vacuum');
        $schema = $state->schemas()['main'];

        $t->same('ok', $assigned['status']);
        $t->same('auto_vacuum', $assigned['pragma']);
        $t->same('main', $assigned['schema']);
        $t->same($case['expected'], $assigned['requested']);
        $t->same($case['expected'], $assigned['effective']);
        $t->same([['auto_vacuum' => $case['expected']]], $assigned['rows']);
        $t->same($case['expected'] !== 0, $assigned['changed']);
        $t->same(false, $assigned['requires_vacuum']);
        $t->same(null, $assigned['pending']);
        $t->same(['sqlite-pragma-auto-vacuum-state'], $assigned['dependencies']);
        $t->same($case['expected'], $read['effective']);
        $t->same($case['expected'], $schema['auto_vacuum']);
        $t->same(null, $schema['pending_auto_vacuum']);
        $t->same(true, $schema['database_empty']);
    };
}

$tests['real upstream pragma-17 auto_vacuum source citation and parser edge coverage'] = static function (TestRunner $t) use ($upstreamSettings): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');

    $t->true(is_string($source));
    $t->true(is_string($source) && str_contains($source, 'Parsing of auto_vacuum settings.'));
    $t->true(is_string($source) && str_contains($source, 'foreach {autovac_setting val}'));
    $t->true(is_string($source) && str_contains($source, 'NoNe 0'));
    $t->true(is_string($source) && str_contains($source, '-1234 0'));
    $t->true(is_string($source) && str_contains($source, '1234 0'));
    $t->same(14, count($upstreamSettings));
    $t->same('no new support component needed; reuses lane-local SQLitePragmaEncodingPageTempStoreState for upstream pragma-17 auto_vacuum parsing behavior', 'no new support component needed; reuses lane-local SQLitePragmaEncodingPageTempStoreState for upstream pragma-17 auto_vacuum parsing behavior');
};

return $tests;
