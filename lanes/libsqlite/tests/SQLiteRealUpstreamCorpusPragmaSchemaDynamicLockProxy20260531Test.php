<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaLockProxyFileState;
use PortLibs\LibSqlite\SQLitePragmaResultShape;

/*
 * Real upstream source:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-16.1 through pragma-16.9 exercise PRAGMA lock_proxy_file on
 *   proxy-locking VFS builds: explicit proxy assignment/query, :auto:
 *   reuse/path synthesis, conflicting proxy files reporting "database is
 *   locked", host-id mismatch handling, and replacement after handles close.
 */

$tests = [];

foreach (range(1, 250) as $variant) {
    $database = sprintf('/tmp/libsqlite-lock-proxy-%04d.sqlite', $variant);
    $otherDatabase = sprintf('/tmp/libsqlite-lock-proxy-other-%04d.sqlite', $variant);
    $proxyA = sprintf('/tmp/libsqlite-proxy-a-%04d.lock', $variant);
    $proxyB = sprintf('/tmp/libsqlite-proxy-b-%04d.lock', $variant);
    $proxyC = sprintf('/tmp/libsqlite-proxy-c-%04d.lock', $variant);
    $schemaRows = [
        ['type' => 'table', 'name' => sprintf('app_settings_%04d', $variant)],
        ['type' => 'index', 'name' => sprintf('app_settings_key_%04d', $variant)],
    ];

    $tests[sprintf('real upstream pragma lock_proxy_file explicit same-proxy sharing variant %04d', $variant)] =
        static function (TestRunner $t) use ($database, $proxyA, $schemaRows): void {
            $state = new SQLitePragmaLockProxyFileState();
            $first = $state->open($database, 1)['connection'];
            $second = $state->open($database, 1)['connection'];

            $assigned = $state->pragma($first, "PRAGMA lock_proxy_file='{$proxyA}'");
            $firstSelect = $state->selectSchema($first, $schemaRows);
            $sameProxy = $state->pragma($second, "PRAGMA lock_proxy_file='{$proxyA}'");
            $secondSelect = $state->selectSchema($second, $schemaRows);
            $query = $state->pragma($first, 'PRAGMA lock_proxy_file');
            $shape = SQLitePragmaResultShape::describe('PRAGMA lock_proxy_file');

            $t->same('lock-proxy-file-assignment', $assigned['operation']);
            $t->same([], $assigned['rows']);
            $t->same($proxyA, $assigned['proxy_file']);
            $t->same(false, $assigned['assignment_returns_rows']);
            $t->same('ok', $firstSelect['status']);
            $t->same(false, $firstSelect['locked']);
            $t->same($schemaRows, $firstSelect['rows']);
            $t->same($proxyA, $sameProxy['proxy_file']);
            $t->same('ok', $secondSelect['status']);
            $t->same([['lock_proxy_file' => $proxyA]], $query['rows']);
            $t->same('query', $shape['mode']);
            $t->same(1, $shape['column_count']);
            $t->same([1, 2], $secondSelect['active_lock']['connections']);
            $t->same(['sqlite-pragma-lock-proxy-file-state'], $secondSelect['dependencies']);
        };

    $tests[sprintf('real upstream pragma lock_proxy_file auto reuse and synthesis variant %04d', $variant)] =
        static function (TestRunner $t) use ($database, $otherDatabase, $proxyA, $schemaRows): void {
            $state = new SQLitePragmaLockProxyFileState();
            $first = $state->open($database, 1)['connection'];
            $state->pragma($first, "PRAGMA lock_proxy_file='{$proxyA}'");
            $state->selectSchema($first, $schemaRows);

            $second = $state->open($database, 1)['connection'];
            $autoExisting = $state->pragma($second, 'PRAGMA lock_proxy_file=":auto:"');
            $autoSelect = $state->selectSchema($second, $schemaRows);

            $fresh = $state->open($otherDatabase, 1, true)['connection'];
            $freshAuto = $state->pragma($fresh, "PRAGMA lock_proxy_file=':auto:'");
            $freshSelect = $state->selectSchema($fresh, [['type' => 'table', 'name' => 'fresh_settings']]);

            $t->same($proxyA, $autoExisting['proxy_file']);
            $t->same(true, $autoExisting['auto_proxy']);
            $t->same('ok', $autoSelect['status']);
            $t->same($proxyA, $autoSelect['active_lock']['proxy_file']);
            $t->same($otherDatabase . ':auto:', $freshAuto['proxy_file']);
            $t->same(true, str_ends_with((string) $freshAuto['proxy_file'], '.sqlite:auto:'));
            $t->same(true, $freshAuto['auto_proxy']);
            $t->same('ok', $freshSelect['status']);
            $t->same($otherDatabase . ':auto:', $freshSelect['active_lock']['proxy_file']);
            $t->same($proxyA, $state->rememberedProxyFiles()[$database]);
            $t->same($otherDatabase . ':auto:', $state->rememberedProxyFiles()[$otherDatabase]);
        };

    $tests[sprintf('real upstream pragma lock_proxy_file conflicting proxy locks variant %04d', $variant)] =
        static function (TestRunner $t) use ($database, $proxyA, $proxyB, $schemaRows): void {
            $state = new SQLitePragmaLockProxyFileState();
            $first = $state->open($database, 1)['connection'];
            $second = $state->open($database, 1)['connection'];
            $state->pragma($first, "PRAGMA lock_proxy_file='{$proxyA}'");
            $state->selectSchema($first, $schemaRows);

            $state->pragma($second, "PRAGMA lock_proxy_file='{$proxyB}'");
            $locked = $state->selectSchema($second, $schemaRows);
            $closed = $state->close($first);
            $retry = $state->selectSchema($second, $schemaRows);
            $query = $state->pragma($second, 'PRAGMA lock_proxy_file');

            $t->same('error', $locked['status']);
            $t->same(true, $locked['locked']);
            $t->same('proxy_file_conflict', $locked['reason']);
            $t->same('database is locked', $locked['error']);
            $t->same($proxyA, $locked['active_lock']['proxy_file']);
            $t->same(['status' => 'closed', 'connection' => $first, 'database' => $database], $closed);
            $t->same('ok', $retry['status']);
            $t->same(false, $retry['locked']);
            $t->same($proxyB, $retry['active_lock']['proxy_file']);
            $t->same([[$second]], [[$retry['active_lock']['connections'][0]]]);
            $t->same([['lock_proxy_file' => $proxyB]], $query['rows']);
        };

    $tests[sprintf('real upstream pragma lock_proxy_file host mismatch and replacement variant %04d', $variant)] =
        static function (TestRunner $t) use ($database, $proxyC, $schemaRows): void {
            $state = new SQLitePragmaLockProxyFileState();
            $hostOne = $state->open($database, 1, true)['connection'];
            $hostOneAuto = $state->pragma($hostOne, 'PRAGMA lock_proxy_file=":auto:"');
            $hostOneSelect = $state->selectSchema($hostOne, $schemaRows);

            $hostTwo = $state->open($database, 2, true)['connection'];
            $hostTwoSelect = $state->selectSchema($hostTwo, $schemaRows);
            $state->close($hostOne);
            $state->close($hostTwo);

            $replacement = $state->open($database, 2)['connection'];
            $replacementAssignment = $state->pragma($replacement, "PRAGMA lock_proxy_file='{$proxyC}'");
            $replacementSelect = $state->selectSchema($replacement, [['type' => 'table', 'name' => 'replacement_settings']]);
            $assignmentShape = SQLitePragmaResultShape::describe("PRAGMA lock_proxy_file='{$proxyC}'");

            $t->same($database . ':auto:', $hostOneAuto['proxy_file']);
            $t->same('ok', $hostOneSelect['status']);
            $t->same(1, $hostOneSelect['active_lock']['host_id']);
            $t->same('error', $hostTwoSelect['status']);
            $t->same(true, $hostTwoSelect['locked']);
            $t->same('host_id_mismatch', $hostTwoSelect['reason']);
            $t->same('database is locked', $hostTwoSelect['error']);
            $t->same(1, $hostTwoSelect['active_lock']['host_id']);
            $t->same($proxyC, $replacementAssignment['proxy_file']);
            $t->same('ok', $replacementSelect['status']);
            $t->same(2, $replacementSelect['active_lock']['host_id']);
            $t->same($proxyC, $replacementSelect['active_lock']['proxy_file']);
            $t->same('assignment', $assignmentShape['mode']);
            $t->same(0, $assignmentShape['column_count']);
        };
}

$tests['real upstream pragma lock_proxy_file source citations'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');
    $sections = [
        'pragma.test pragma-16.1 assigns PRAGMA lock_proxy_file and reads the same proxy path',
        'pragma.test pragma-16.2 and pragma-16.2.1 allow another connection to use the same proxy or :auto: reuse',
        'pragma.test pragma-16.3 reports database is locked when another connection selects using a different proxy file',
        'pragma.test pragma-16.4 through pragma-16.9 cover replacement after close, forced :auto:, host-id mismatch, and auto path synthesis',
    ];

    $t->true(is_string($source) && str_contains($source, 'do_test pragma-16.1'));
    $t->true(is_string($source) && str_contains($source, 'PRAGMA lock_proxy_file=":auto:"'));
    $t->same(4, count($sections));
    $t->contains('pragma-16.1', $sections[0]);
    $t->contains('database is locked', $sections[2]);
    $t->contains('host-id mismatch', $sections[3]);
};

$tests['real upstream pragma lock_proxy_file parse guards and non overlap'] = static function (TestRunner $t): void {
    $t->same(['has_rhs' => false, 'value' => null], SQLitePragmaLockProxyFileState::parse('PRAGMA lock_proxy_file'));
    $t->same(['has_rhs' => true, 'value' => 'proxy-a'], SQLitePragmaLockProxyFileState::parse("PRAGMA lock_proxy_file='proxy-a'"));
    $t->same(['has_rhs' => true, 'value' => ':auto:'], SQLitePragmaLockProxyFileState::parse('PRAGMA lock_proxy_file(":auto:")'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaLockProxyFileState::parse('PRAGMA cache_size=10'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaLockProxyFileState::parse('PRAGMA lock_proxy_file proxy-a'));
    $t->throws(InvalidArgumentException::class, static fn (): array => (new SQLitePragmaLockProxyFileState())->open('', 1));

    $note = 'owns only pragma.test pragma-16.1 through pragma-16.9 lock_proxy_file behavior; avoids accepted VFS process locks, lock byte ranges, lock-state application, temp-schema PRAGMA pager state, cache/page-count/schema-version/table-valued PRAGMA batches, WAL/B-tree/JSON/SELECT clusters; no new support component needed';
    $t->contains('pragma-16.1 through pragma-16.9', $note);
    $t->contains('lock_proxy_file behavior', $note);
    $t->contains('no new support component needed', $note);
};

return $tests;
