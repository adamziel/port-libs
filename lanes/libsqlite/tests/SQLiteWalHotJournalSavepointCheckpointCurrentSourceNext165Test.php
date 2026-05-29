<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next165.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next165 clean schema before failed plugin import'),
    2 => $page('next165 clean wp_options root before failed plugin import'),
    3 => $page('next165 clean active_plugins before failed plugin import'),
    4 => $page('next165 clean autoload index before failed plugin import'),
    5 => $page('next165 clean transient timeout before failed plugin import'),
];
$dirtyDatabase = $page('next165 dirty schema from failed plugin import')
    . $page('next165 dirty wp_options root from failed plugin import')
    . $page('next165 dirty active_plugins from failed plugin import')
    . $page('next165 dirty autoload index from failed plugin import')
    . $page('next165 dirty transient timeout from failed plugin import');

$makeJournal = static function (array $pages, int $nonce = 0x16516501) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 165) use ($pageSize, $page): string {
    $salt1 = 0x16516501;
    $salt2 = 0x16516502;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next165 retained schema draft before publish'],
    [2, 5, 'next165 retained wp_options commit before publish'],
    [3, 0, 'next165 discarded active_plugins draft'],
    [4, 5, 'next165 discarded autoload index commit'],
    [5, 5, 'next165 discarded transient timeout commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next165');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next165');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 5, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = null, array $pages = [1, 2, 3, 4, 5], bool $reserved = false, ?string $database = null, ?string $journal = null, ?string $walInput = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan(
    $databasePath,
    $database ?? $dirtyDatabase,
    $journal ?? $journalBytes,
    $makeStack(),
    'plugin-batch-next165',
    $wal,
    $walInput ?? $walBytes,
    $pages,
    $mode,
    $reader,
    $reserved
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$baseReader = static fn (): array => $plan('restart', 0);
$single = static fn (): array => $plan('restart', null, [2]);
$blocked = static fn (): array => $plan('restart', null, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next165'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'publish_checkpoint_uses_hot_journal_savepoint_current_source_before_wal_reset'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-batch-next165'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'page numbers' => [static fn (): mixed => $restart()['page_numbers'], [1, 2, 3, 4, 5]],
    'publish admitted' => [static fn (): mixed => $restart()['publish_admitted'], true],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'current busy' => [static fn (): mixed => $restart()['current_checkpoint_busy'], true],
    'released busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'current wal action' => [static fn (): mixed => $restart()['current_checkpoint_wal_action'], 'preserve_wal'],
    'released wal action' => [static fn (): mixed => $restart()['released_checkpoint_wal_action'], 'restart_wal'],
    'current database length' => [static fn (): mixed => $restart()['current_database_bytes_length'], 5 * $pageSize],
    'released database length' => [static fn (): mixed => $restart()['released_database_bytes_length'], 5 * $pageSize],
    'current wal length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released wal length' => [static fn (): mixed => $restart()['released_wal_bytes_length'], 32],
    'current database sha length' => [static fn (): mixed => strlen($restart()['current_database_sha256']), 64],
    'released database sha length' => [static fn (): mixed => strlen($restart()['released_database_sha256']), 64],
    'current wal sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'released wal sha length' => [static fn (): mixed => strlen($restart()['released_wal_sha256']), 64],
    'current and released db match' => [static fn (): mixed => $restart()['current_database_sha256'] === $restart()['released_database_sha256'], true],
    'current and released wal differ' => [static fn (): mixed => $restart()['current_wal_sha256'] !== $restart()['released_wal_sha256'], true],
    'pinned reader pages' => [static fn (): mixed => $restart()['pinned_reader_page_numbers'], [1, 2]],
    'stale blocked pages' => [static fn (): mixed => $restart()['stale_publish_blocked_page_numbers'], [3, 4, 5]],
    'all released database' => [static fn (): mixed => $restart()['all_released_pages_from_database'], true],
    'operation ops' => [static fn (): mixed => $restart()['operation_ops'], ['write', 'truncate', 'write', 'sync', 'delete', 'write', 'write', 'sync']],
    'operation current publish' => [static fn (): mixed => in_array('publish_hot_journal_savepoint_current_checkpoint_database_next165', $restart()['operation_reasons'], true), true],
    'operation preserve wal' => [static fn (): mixed => in_array('preserve_retained_wal_for_pinned_reader_next165', $restart()['operation_reasons'], true), true],
    'operation delete journal' => [static fn (): mixed => in_array('delete_hot_journal_after_current_source_checkpoint_next165', $restart()['operation_reasons'], true), true],
    'operation restart wal' => [static fn (): mixed => in_array('restart_wal_after_savepoint_release_next165', $restart()['operation_reasons'], true), true],
    'payload key count' => [static fn (): mixed => count($restart()['payload_keys']), 4],
    'payload database key' => [static fn (): mixed => $restart()['payload_keys'][0], $databasePath . '#next165-current-checkpoint'],
    'payload wal key' => [static fn (): mixed => $restart()['payload_keys'][1], $databasePath . '-wal#next165-current-reader'],
    'payload released key' => [static fn (): mixed => $restart()['payload_keys'][2], $databasePath . '#next165-released-checkpoint'],
    'payload released wal key' => [static fn (): mixed => $restart()['payload_keys'][3], $databasePath . '-wal#next165-released-reader'],
    'payload count' => [static fn (): mixed => count($restart()['payloads']), 4],
    'current payload length' => [static fn (): mixed => strlen($restart()['payloads'][$databasePath . '#next165-current-checkpoint']), 5 * $pageSize],
    'current wal payload length' => [static fn (): mixed => strlen($restart()['payloads'][$databasePath . '-wal#next165-current-reader']), 32 + (2 * (24 + $pageSize))],
    'released wal payload length' => [static fn (): mixed => strlen($restart()['payloads'][$databasePath . '-wal#next165-released-reader']), 32],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row one label' => [static fn (): mixed => $restart()['rows'][0]['current_checkpoint_label'], 'next165 retained schema draft before publish'],
    'row two label' => [static fn (): mixed => $restart()['rows'][1]['current_checkpoint_label'], 'next165 retained wp_options commit before publish'],
    'row three released label' => [static fn (): mixed => $restart()['rows'][2]['released_checkpoint_label'], 'next165 clean active_plugins before failed plugin import'],
    'row one pinned' => [static fn (): mixed => $restart()['rows'][0]['reader_pinned'], true],
    'row three not pinned' => [static fn (): mixed => $restart()['rows'][2]['reader_pinned'], false],
    'row five stale blocked' => [static fn (): mixed => $restart()['rows'][4]['stale_publish_blocked'], true],
    'publish transitions' => [static fn (): mixed => $restart()['publish_transitions'], [
        'database>wal>database',
        'database>wal>database',
        'database>database>database',
        'database>database>database',
        'database>database>database',
    ]],
    'publish digest length' => [static fn (): mixed => strlen($restart()['publish_digest']), 64],
    'base status' => [static fn (): mixed => $restart()['base_plan']['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next162'],
    'base discarded indexes' => [static fn (): mixed => $restart()['base_plan']['discarded_frame_indexes'], [3, 4, 5]],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next165', $restart()['dependencies'], true), true],
    'dependency publish' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-publish-sequence', $restart()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-current-source-checkpoint-publish', $restart()['dependencies'], true), true],
    'dependency closure text' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap text' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'extends next162'), true],
    'truncate released action' => [static fn (): mixed => $truncate()['released_checkpoint_wal_action'], 'truncate_wal'],
    'truncate operation op' => [static fn (): mixed => $truncate()['operation_ops'][6], 'truncate'],
    'truncate released wal length' => [static fn (): mixed => $truncate()['released_wal_bytes_length'], 0],
    'base reader pinned pages' => [static fn (): mixed => $baseReader()['pinned_reader_page_numbers'], [1, 2]],
    'base reader current wal length' => [static fn (): mixed => $baseReader()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'single page pinned' => [static fn (): mixed => $single()['pinned_reader_page_numbers'], [2]],
    'single transitions' => [static fn (): mixed => $single()['publish_transitions'], ['database>wal>database']],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next165'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'database_has_reserved_lock'],
    'blocked admitted false' => [static fn (): mixed => $blocked()['publish_admitted'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next165 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan('', $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next165', $wal, $walBytes, [1]),
    'empty database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, '', $journalBytes, $makeStack(), 'plugin-batch-next165', $wal, $walBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, $dirtyDatabase, '', $makeStack(), 'plugin-batch-next165', $wal, $walBytes, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), '', $wal, $walBytes, [1]),
    'empty wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next165', $wal, '', [1]),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next165', $wal, $walBytes, []),
    'bad mode rejected' => static fn () => $plan('passive'),
    'zero page rejected' => static fn () => $plan('restart', null, [0]),
    'string page rejected' => static fn () => $plan('restart', null, ['1']),
    'unaligned database rejected' => static fn () => $plan('restart', null, [1], false, 'short'),
    'wal mismatch rejected' => static fn () => $plan('restart', null, [1], false, null, null, substr_replace($walBytes, 'x', 96, 1)),
    'reader past retained rejected' => static fn () => $plan('restart', 3),
    'missing savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next165Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'missing-next165', $wal, $walBytes, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next165 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
