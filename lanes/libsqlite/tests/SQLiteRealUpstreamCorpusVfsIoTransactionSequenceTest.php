<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$baseSteps = [
    ['name' => 'io-2.2 rollback journal insert', 'pages_written' => 2, 'pages_touched' => 1, 'journal_created' => true],
    ['name' => 'io-2.3 atomic insert', 'pages_written' => 2, 'pages_touched' => 1],
    ['name' => 'io-2.5 multi-page transaction', 'pages_written' => 3, 'pages_touched' => 2],
    ['name' => 'io-2.6 append-page transaction', 'pages_written' => 3, 'pages_touched' => 1, 'appends_page' => true],
];

$corpusCases = [
    'io-2.2 normal rollback journal writes two pages and four syncs' => [[$baseSteps[0]], [], [2, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-2.3 atomic write avoids journal and keeps one database sync' => [[$baseSteps[1]], ['atomic'], [2, 1, 0, true, ['database']]],
    'io-2.3 atomic4k marker alone keeps ordinary no-journal commit shape' => [[$baseSteps[1]], ['atomic4k'], [2, 1, 0, false, ['database']]],
    'io-2.5 atomic falls back when multiple pages are touched' => [[$baseSteps[2]], ['atomic'], [3, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-2.6 atomic falls back when transaction appends a page' => [[$baseSteps[3]], ['atomic'], [3, 4, 1, false, ['journal-header', 'journal-pages', 'directory', 'database']]],
    'io-3 sequential device skips journal page sync' => [[$baseSteps[0]], ['sequential'], [2, 3, 1, false, ['journal-header', 'directory', 'database']]],
    'io-4 safe append skips journal header sync' => [[$baseSteps[0]], ['safe_append'], [2, 3, 1, false, ['journal-pages', 'directory', 'database']]],
    'io-3 and io-4 combined keep only directory and database syncs' => [[$baseSteps[0]], ['sequential', 'safe_append'], [2, 2, 1, false, ['directory', 'database']]],
];

foreach ($corpusCases as $name => [$steps, $flags, $expected]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($steps, $flags, $expected): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence($steps, $flags);
        $step = $plan['steps'][0];

        $t->same('ok', $plan['status']);
        $t->same(1, $plan['count']);
        $t->same($expected[0], $plan['write_total']);
        $t->same($expected[1], $plan['sync_total']);
        $t->same($expected[2], $plan['journal_creates']);
        $t->same($expected[0], $step['writes']);
        $t->same($expected[1], $step['syncs']);
        $t->same($expected[2] === 1, $step['journal_created']);
        $t->same($expected[3], $step['atomic_write']);
        $t->same($expected[4], $step['sync_reasons']);
        $t->same(true, in_array('real-upstream-corpus-io-test', $plan['dependencies'], true));
        $t->same(true, in_array('io.test io-2.3', $plan['upstream'], true));
    };
}

$sequenceCases = [
    'io-2.2 then io-2.3 keeps honest totals without merging behavior' => [[$baseSteps[0], $baseSteps[1]], ['atomic'], 4, 5, 1],
    'io-2.5 then io-2.6 records two real journal creations' => [[$baseSteps[2], $baseSteps[3]], ['atomic'], 6, 8, 2],
    'io-3 sequential sequence reduces both journal page syncs' => [[$baseSteps[0], $baseSteps[2]], ['sequential'], 5, 6, 2],
    'io-4 safe append sequence reduces both journal header syncs' => [[$baseSteps[0], $baseSteps[2]], ['safe_append'], 5, 6, 2],
    'io-3 and io-4 combined sequence records minimal rollback syncs' => [[$baseSteps[0], $baseSteps[2]], ['sequential', 'safe_append'], 5, 4, 2],
];

foreach ($sequenceCases as $name => [$steps, $flags, $writes, $syncs, $journals]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($steps, $flags, $writes, $syncs, $journals): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence($steps, $flags);

        $t->same(count($steps), $plan['count']);
        $t->same($writes, $plan['write_total']);
        $t->same($syncs, $plan['sync_total']);
        $t->same($journals, $plan['journal_creates']);
        $t->same(array_column($steps, 'name'), array_column($plan['steps'], 'name'));
        $t->same(range(0, count($steps) - 1), array_column($plan['steps'], 'ordinal'));
        $t->same(true, in_array('io.test io-2.5.1-2.5.3', $plan['upstream'], true));
        $t->same(true, in_array('io.test io-4.*', $plan['upstream'], true));
    };
}

$flagCases = [
    'duplicate capability flags are normalized' => [['atomic', 'ATOMIC', 'safe_append'], ['atomic', 'safe_append']],
    'blank capability flags are ignored' => [['', ' ', 'sequential'], ['sequential']],
    'powersafe overwrite is preserved as a capability marker' => [['powersafe_overwrite'], ['powersafe_overwrite']],
];

foreach ($flagCases as $name => [$flags, $expectedFlags]) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static function (TestRunner $t) use ($baseSteps, $flags, $expectedFlags): void {
        $plan = SQLiteVfsIoTransactionSequencePlan::transactionSequence([$baseSteps[0]], $flags);

        $t->same($expectedFlags, $plan['steps'][0]['flags']);
        $t->same('ok', $plan['steps'][0]['status']);
        $t->same('io-2.2 rollback journal insert', $plan['steps'][0]['name']);
    };
}

$guardCases = [
    'rejects empty transaction sequence' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([]),
    'rejects unknown capability flag' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 1]], ['networked']),
    'rejects zero pages written' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 0]]),
    'rejects zero page size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::transactionSequence([['pages_written' => 1]], [], 0),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs io transaction sequence ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
