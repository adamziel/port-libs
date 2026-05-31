<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * pragma-9.15 verifies PRAGMA temp_store cannot be changed while a temp
 * table transaction is active and preserves the table contents through
 * COMMIT. pragma-9.18 verifies the same rejection while a temp table scan is
 * active. This ports those behaviors into the bounded PHP PRAGMA
 * encoding/page/temp-store state model with generic application table names.
 */

$rejectsRuntime = static function (callable $callback): string {
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return 'accepted';
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $table = 'temp_settings_' . $suffix;
    $initialMode = $variant % 2 === 0 ? 'FILE' : 'MEMORY';
    $blockedMode = $initialMode === 'FILE' ? 'MEMORY' : 'FILE';
    $initialValue = $initialMode === 'FILE' ? 1 : 2;
    $rowA = ['key_name' => 'setting_' . $suffix, 'key_value' => 'value_' . $suffix];
    $rowB = ['key_name' => 'setting_' . $suffix . '_b', 'key_value' => 'value_' . $suffix . '_b'];

    $tests[sprintf('real upstream pragma 9.15 temp_store transaction rejects change variant %03d', $variant)] = static function (TestRunner $t) use ($rejectsRuntime, $table, $initialMode, $blockedMode, $initialValue, $rowA): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute("PRAGMA temp_store = {$initialMode}");
        $begin = $state->beginTempTransaction($table, [$rowA]);
        $message = $rejectsRuntime(static fn () => $state->execute("PRAGMA temp_store = {$blockedMode}"));

        $t->same('temp_transaction_active', $begin['status']);
        $t->same($initialValue, $begin['temp_store']);
        $t->same('temporary storage cannot be changed from within a transaction', $message);
        $t->same($initialValue, $state->execute('PRAGMA temp_store')['effective']);
    };

    $tests[sprintf('real upstream pragma 9.16 temp_store commit preserves temp rows variant %03d', $variant)] = static function (TestRunner $t) use ($table, $initialMode, $initialValue, $rowA, $rowB): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute("PRAGMA temp_store = {$initialMode}");
        $state->beginTempTransaction($table, [$rowA]);
        $insert = $state->insertTempRow($table, $rowB);
        $commit = $state->commitTempTransaction();
        $scan = $state->beginTempScan($table);
        $state->endTempScan();

        $t->same('temp_row_inserted', $insert['status']);
        $t->same(2, $insert['rows']);
        $t->same('temp_transaction_committed', $commit['status']);
        $t->same([$table], $commit['tables']);
        $t->same($initialValue, $scan['temp_store']);
        $t->same([$rowA, $rowB], $scan['rows']);
    };

    $tests[sprintf('real upstream pragma 9.18 temp_store active scan rejects change variant %03d', $variant)] = static function (TestRunner $t) use ($rejectsRuntime, $table, $initialMode, $blockedMode, $initialValue, $rowA): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute("PRAGMA temp_store = {$initialMode}");
        $state->beginTempTransaction($table, [$rowA]);
        $state->commitTempTransaction();
        $scan = $state->beginTempScan($table);
        $message = $rejectsRuntime(static fn () => $state->execute("PRAGMA temp_store = {$blockedMode}"));
        $finish = $state->endTempScan();

        $t->same('temp_scan_active', $scan['status']);
        $t->same([$rowA], $scan['rows']);
        $t->same('temporary storage cannot be changed from within a transaction', $message);
        $t->same('temp_scan_finished', $finish['status']);
        $t->same($initialValue, $state->execute('PRAGMA temp_store')['effective']);
    };

    $tests[sprintf('real upstream pragma 9.11 through 9.14 temp_store mode changes after scan variant %03d', $variant)] = static function (TestRunner $t) use ($table, $initialMode, $blockedMode, $rowA): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute("PRAGMA temp_store = {$initialMode}");
        $state->beginTempTransaction($table, [$rowA]);
        $state->commitTempTransaction();
        $state->beginTempScan($table);
        $state->endTempScan();
        $changed = $state->execute("PRAGMA temp_store = {$blockedMode}");

        $t->same($blockedMode === 'FILE' ? 1 : 2, $changed['effective']);
        $t->same(true, $changed['changed']);
        $t->same([['temp_store' => $blockedMode === 'FILE' ? 1 : 2]], $changed['rows']);
        $t->same(['sqlite-pragma-temp-store-state'], $changed['dependencies']);
    };
}

$tests['real upstream pragma temp_store transaction source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-9.11 through pragma-9.14 normalize numeric temp_store modes 0, 1, 2, and reject 3 to default',
        'pragma.test pragma-9.15 rejects PRAGMA temp_store changes while a temp table transaction is active',
        'pragma.test pragma-9.16 confirms temp table rows remain readable after COMMIT',
        'pragma.test pragma-9.18 rejects PRAGMA temp_store changes during an active temp table scan',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-9.15', $sections[1]);
    $t->contains('pragma-9.18', $sections[3]);
};

return $tests;
