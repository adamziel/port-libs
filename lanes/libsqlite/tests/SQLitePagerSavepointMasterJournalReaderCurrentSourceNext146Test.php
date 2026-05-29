<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next146.sqlite';
$masterPath = '/srv/wp-content/database/wp-next146.sqlite-mj';
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/attached-next146.sqlite-journal\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next146 stale schema before master recovery'),
    2 => $page('next146 stale wp_options root before master recovery'),
    3 => $page('next146 stale autoload index before master recovery'),
    4 => $page('next146 stale plugin setting before master recovery'),
    5 => $page('next146 untouched comments page before master recovery'),
    6 => $page('next146 stale transient page before master recovery'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next146 recovered schema after master journal'),
    2 => $page('next146 recovered wp_options root after master journal'),
    3 => $page('next146 recovered autoload index after master journal'),
    4 => $page('next146 recovered plugin setting after master journal'),
    6 => $page('next146 recovered transient after master journal'),
];
$savepoint = [
    3 => $page('next146 savepoint before-image autoload index current source'),
    4 => $page('next146 savepoint before-image plugin setting current source'),
];
$sourceBefore = 'next146-before-hot-master-source';
$recoveredId = static fn (): string => 'master-savepoint-reader:' . hash('sha256', $masterPath . '|' . $databasePath . '-journal|/srv/wp-content/database/attached-next146.sqlite-journal');
$savepointId = static fn (): string => $recoveredId() . ':rollback-to:plugin-batch';
$readers = static fn (): array => [
    ['label' => 'stale-before-master', 'kind' => 'read', 'page_number' => 2, 'source_id' => $sourceBefore, 'epoch' => 4, 'pinned' => true],
    ['label' => 'after-master-before-savepoint', 'kind' => 'read', 'page_number' => 3, 'source_id' => $recoveredId(), 'epoch' => 5, 'pinned' => true],
    ['label' => 'wrong-epoch-savepoint', 'kind' => 'read', 'page_number' => 4, 'source_id' => $savepointId(), 'epoch' => 5, 'pinned' => true],
    ['label' => 'fresh-after-reopen', 'kind' => 'read', 'page_number' => 4, 'source_id' => $savepointId(), 'epoch' => 6, 'pinned' => false],
];
$nextWrites = [
    4 => $page('next146 rewritten plugin setting after reader reopen'),
    6 => $page('next146 rewritten transient after reader reopen'),
];

$plan = static fn (
    ?array $masterRecovered = null,
    ?array $savepointBefore = null,
    ?array $readerSnapshots = null,
    ?array $readPages = null,
    ?array $writePages = null,
    mixed $masterBytes = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $savepointName = null,
    ?string $source = null,
    int $epoch = 4,
    ?string $path = null,
    ?string $masterJournalPath = null,
): array => SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $masterBytes === '__default__' ? $currentMaster : $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $masterRecovered ?? $recovered,
    $savepointName ?? 'plugin-batch',
    $savepointBefore ?? $savepoint,
    $readerSnapshots ?? $readers(),
    $readPages ?? [1, 2, 3, 4, 5, 6],
    $writePages ?? $nextWrites,
    $source ?? $sourceBefore,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-savepoint-master-journal-reader-current-source-next146'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'savepoint_rollback_after_master_journal_recovery_reopens_stale_readers'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/attached-next146.sqlite-journal']],
    'input source id' => [static fn (): mixed => $plan()['input_source']['id'], $sourceBefore],
    'input epoch' => [static fn (): mixed => $plan()['input_source']['epoch'], 4],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], $recoveredId()],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 5],
    'savepoint source id' => [static fn (): mixed => $plan()['savepoint_source']['id'], $savepointId()],
    'savepoint epoch' => [static fn (): mixed => $plan()['savepoint_source']['epoch'], 6],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 4, 6]],
    'savepoint pages' => [static fn (): mixed => $plan()['savepoint_rollback_page_numbers'], [3, 4]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'blocked readers' => [static fn (): mixed => $plan()['blocked_reader_labels'], ['stale-before-master', 'after-master-before-savepoint', 'wrong-epoch-savepoint']],
    'admitted readers' => [static fn (): mixed => $plan()['admitted_reader_labels'], ['fresh-after-reopen']],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 4],
    'reader stale reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_predates_master_journal_recovery'],
    'reader after master reason' => [static fn (): mixed => $plan()['reader_rows'][1]['reason'], 'reader_predates_savepoint_rollback_source'],
    'reader wrong epoch reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'reader_epoch_predates_savepoint_rollback'],
    'reader admitted reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'reader_matches_savepoint_current_source'],
    'reader current prefix savepoint' => [static fn (): mixed => $plan()['reader_rows'][3]['current_prefix'], 'next146 savepoint before-image plugin setting current source'],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read one recovered schema' => [static fn (): mixed => $plan()['next_reads'][0]['prefix'], 'next146 recovered schema after master journal'],
    'read two recovered root' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next146 recovered wp_options root after master journal'],
    'read three savepoint before' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next146 savepoint before-image autoload index current source'],
    'read four savepoint before' => [static fn (): mixed => $plan()['next_reads'][3]['prefix'], 'next146 savepoint before-image plugin setting current source'],
    'read five untouched' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next146 untouched comments page before master recovery'],
    'read six recovered transient' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next146 recovered transient after master journal'],
    'read source id' => [static fn (): mixed => $plan()['next_reads'][3]['source_id'], $savepointId()],
    'read epoch' => [static fn (): mixed => $plan()['next_reads'][3]['epoch'], 6],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write four before savepoint' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next146 savepoint before-image plugin setting current source'],
    'write four after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next146 rewritten plugin setting after reader reopen'],
    'write six before recovered' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next146 recovered transient after master journal'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_savepoint_current_source'], true],
    'final source page three savepoint' => [static fn (): mixed => $plan()['final_sources'][3], 'savepoint-rollback-current-source'],
    'final source page four write' => [static fn (): mixed => $plan()['final_sources'][4], 'next-write-after-savepoint-reader-reopen'],
    'final prefix page six write' => [static fn (): mixed => $plan()['final_prefixes'][6], 'next146 rewritten transient after reader reopen'],
    'final bytes include plugin rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten plugin setting'), true],
    'final bytes exclude stale plugin page' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale plugin setting'), false],
    'operation first master read' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_source'],
    'operation restore page one' => [static fn (): mixed => $plan()['operations'][1]['op'], 'restore_master_journal_page_before_reader_check'],
    'operation savepoint rollback page three' => [static fn (): mixed => $plan()['operations'][6]['op'], 'rollback_to_savepoint_before_reader_reopen'],
    'operation block first reader' => [static fn (): mixed => $plan()['operations'][8]['op'], 'block_stale_reader_after_savepoint_master_journal_source_check'],
    'operation admit reader' => [static fn (): mixed => $plan()['operations'][11]['op'], 'admit_reader_after_savepoint_master_journal_source_check'],
    'operation next read' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_reader_uses_savepoint_master_journal_current_source'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][18]['op'], 'capture_next_write_before_image_after_reader_reopen'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-master-journal-reader-current-source-next146', $plan()['dependencies'], true), true],
    'dependency hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'dependency savepoint cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next138', $plan()['dependencies'], true), true],
    'all fresh readers no reopen' => [static fn (): mixed => $plan(null, null, [['label' => 'fresh', 'page_number' => 1, 'source_id' => $savepointId(), 'epoch' => 6]], [1], [])['requires_reader_reopen'], false],
    'duplicate master members collapsed' => [static fn (): mixed => $plan(null, null, null, [1], [], $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/attached-next146.sqlite-journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint master journal reader current source next146 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, null, null, null, 4, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, null, null, null, 4, null, ''),
    'missing current master rejected' => static fn () => $plan(null, null, null, null, null, null),
    'wrong current master rejected' => static fn () => $plan(null, null, null, null, null, '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, null, '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, '__default__', $databaseBytes . 'x'),
    'empty savepoint rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, null, null, ''),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, '__default__', null, null, null, null, 0),
    'empty readers rejected' => static fn () => $plan(null, null, []),
    'empty next work rejected' => static fn () => $plan(null, null, null, [], []),
    'empty recovered rejected' => static fn () => $plan([]),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'empty savepoint images rejected' => static fn () => $plan(null, []),
    'zero savepoint page rejected' => static fn () => $plan(null, [0 => $savepoint[3]]),
    'short savepoint image rejected' => static fn () => $plan(null, [3 => 'short']),
    'bad reader page rejected' => static fn () => $plan(null, null, [['page_number' => 0, 'source_id' => $savepointId(), 'epoch' => 6]]),
    'empty reader source rejected' => static fn () => $plan(null, null, [['page_number' => 1, 'source_id' => '', 'epoch' => 6]]),
    'bad reader epoch rejected' => static fn () => $plan(null, null, [['page_number' => 1, 'source_id' => $savepointId(), 'epoch' => 0]]),
    'bad read page rejected' => static fn () => $plan(null, null, null, [0], []),
    'zero write page rejected' => static fn () => $plan(null, null, null, [], [0 => $nextWrites[4]]),
    'short write image rejected' => static fn () => $plan(null, null, null, [], [4 => 'short']),
    'recovered outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'savepoint outside database rejected' => static fn () => $plan(null, [7 => $page('outside')]),
    'reader outside database rejected' => static fn () => $plan(null, null, [['page_number' => 7, 'source_id' => $savepointId(), 'epoch' => 6]]),
    'read outside database rejected' => static fn () => $plan(null, null, null, [7], []),
    'write outside database rejected' => static fn () => $plan(null, null, null, [], [7 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint master journal reader current source next146 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
