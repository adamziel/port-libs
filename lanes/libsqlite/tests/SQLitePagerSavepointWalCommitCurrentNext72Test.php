<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';
$databaseBytes = $page('db-header-before-commit') . $page('db-siteurl-before-commit') . $page('db-plugin-before-commit') . $page('db-index-before-commit');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x72817281;
    $salt2 = 0x90919091;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 72, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'retained-schema-frame'],
    [2, 4, 'retained-siteurl-commit'],
    [3, 0, 'plugin-draft-setting'],
    [4, 4, 'plugin-index-commit'],
    [2, 4, 'nested-rolled-back-siteurl'],
    [3, 4, 'nested-rolled-back-plugin'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('db-header-before-commit'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db-siteurl-before-commit'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(3, $page('db-plugin-before-commit'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordPageImageWrite(4, $page('db-index-before-commit'));
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->savepoint('single-plugin-row');
    $stack->recordWalFrameWrite(5, 2);
    $stack->recordWalFrameWrite(6, 3, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $currentReader = null, ?int $nextReader = null, array $pages = [1, 2, 3, 4]): array =>
    SQLiteWalSavepointCheckpointPlan::commitCurrentAfterRollbackTo(
        $makeStack(),
        'plugin-settings',
        $wal,
        $walBytes,
        $databaseBytes,
        $pages,
        $mode,
        $currentReader,
        $nextReader
    );

$apply = static function (string $mode = 'restart', ?int $currentReader = null, ?int $nextReader = null, array $pages = [1, 2, 3, 4]) use ($databasePath, $databaseBytes, $wal, $walBytes, $makeStack): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-savepoint-commit-current72-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    $localWal = $localDatabase . '-wal';
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create temporary SQLite WAL commit-current fixture');
    }
    file_put_contents($localDatabase, $databaseBytes);
    file_put_contents($localWal, $walBytes . 'stale-rolled-back-tail');

    $applied = (new SQLiteVfsFileWriter($root))->applySavepointCommitCurrent(
        $makeStack(),
        'plugin-settings',
        $wal,
        $walBytes,
        $databaseBytes,
        $databasePath,
        $pages,
        $mode,
        $currentReader,
        $nextReader
    );

    return [
        'applied' => $applied,
        'database' => (string) file_get_contents($localDatabase),
        'wal' => is_file($localWal) ? (string) file_get_contents($localWal) : '',
    ];
};

$restart = static fn (): array => $plan('restart');
$truncate = static fn (): array => $plan('truncate');
$fullPinned = static fn (): array => $plan('full', 1);
$singlePage = static fn (): array => $plan('restart', null, null, [2]);
$appliedRestart = static fn (): array => $apply('restart');
$appliedTruncate = static fn (): array => $apply('truncate');

$cases = [
    'restart status ready' => [static fn (): mixed => $restart()['status'], 'ready'],
    'restart savepoint name' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings'],
    'restart mode normalized' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart rollback frame' => [static fn (): mixed => $restart()['rollback_to_frame'], 2],
    'restart retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'restart discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'restart discarded frame indexes' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'frame_index'), [3, 4, 5, 6]],
    'restart discarded pages' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'page_number'), [3, 4, 2, 3]],
    'restart discarded commits' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'commit_frame'), [false, true, false, true]],
    'restart discarded frame names' => [static fn (): mixed => array_column($restart()['discarded_wal_frames'], 'frame_name'), ['plugin-settings', 'plugin-settings', 'single-plugin-row', 'single-plugin-row']],
    'restart committed frame names' => [static fn (): mixed => $restart()['committed_frame_names'], ['wp-import', 'plugin-settings']],
    'restart committed page numbers' => [static fn (): mixed => $restart()['committed_page_numbers'], [1, 2]],
    'restart released savepoint count' => [static fn (): mixed => $restart()['released_savepoint_count'], 1],
    'restart transaction closed' => [static fn (): mixed => $restart()['transaction_active_after'], false],
    'restart current reader frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal'],
    'restart checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint_busy'], false],
    'restart checkpoint reason' => [static fn (): mixed => $restart()['checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'restart current frame indexes' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, 2, null, null]],
    'restart next frame indexes' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null, null]],
    'restart current keeps retained wal' => [static fn (): mixed => $restart()['current_reader_kept_retained_wal'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_database'], true],
    'restart images match after retained prefix checkpoint' => [static fn (): mixed => $restart()['images_match'], true],
    'restart current sees retained siteurl' => [static fn (): mixed => str_contains($restart()['current_reader_images'][1], 'retained-siteurl-commit'), true],
    'restart current excludes rolled back siteurl' => [static fn (): mixed => str_contains($restart()['current_reader_images'][1], 'nested-rolled-back-siteurl'), false],
    'restart next database sees retained siteurl' => [static fn (): mixed => str_contains($restart()['next_reader_images'][1], 'retained-siteurl-commit'), true],
    'restart next database excludes rolled back plugin' => [static fn (): mixed => str_contains($restart()['next_reader_images'][2], 'nested-rolled-back-plugin'), false],
    'restart durable wal header only' => [static fn (): mixed => $restart()['current_durable']['wal_bytes_length'], 32],
    'restart durable database length' => [static fn (): mixed => strlen($restart()['current_durable']['database_bytes']), 2048],
    'restart dependency marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-commit-current-next72', $restart()['dependencies'], true), true],
    'restart application dependency marker' => [static fn (): mixed => in_array('application-import-savepoint-commit-current-next72', $restart()['dependencies'], true), true],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal'],
    'truncate durable wal empty' => [static fn (): mixed => $truncate()['current_durable']['wal_bytes_length'], 0],
    'truncate next reader frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate next sources database' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'full pinned status busy' => [static fn (): mixed => $fullPinned()['status'], 'busy'],
    'full pinned reason' => [static fn (): mixed => $fullPinned()['checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'full pinned wal preserved' => [static fn (): mixed => $fullPinned()['wal_action'], 'preserve_wal'],
    'full pinned next keeps wal' => [static fn (): mixed => $fullPinned()['next_reader_sources'][0], 'wal'],
    'single page current row count' => [static fn (): mixed => count($singlePage()['current_reader']), 1],
    'single page next row count' => [static fn (): mixed => count($singlePage()['next_reader']), 1],
    'single page number' => [static fn (): mixed => $singlePage()['next_reader'][0]['page_number'], 2],
    'vfs restart status' => [static fn (): mixed => $appliedRestart()['applied']['status'], 'applied'],
    'vfs restart operation count' => [static fn (): mixed => $appliedRestart()['applied']['applied'], 7],
    'vfs restart database write reason' => [static fn (): mixed => $appliedRestart()['applied']['operations'][0]['reason'], 'apply_savepoint_commit_current_database_image'],
    'vfs restart wal write reason' => [static fn (): mixed => $appliedRestart()['applied']['operations'][3]['reason'], 'apply_savepoint_commit_current_wal_state'],
    'vfs restart directory sync reason' => [static fn (): mixed => $appliedRestart()['applied']['operations'][6]['reason'], 'persist_savepoint_commit_current_sidecars'],
    'vfs restart durable sync count' => [static fn (): mixed => $appliedRestart()['applied']['durable_syncs'], 2],
    'vfs restart directory sync count' => [static fn (): mixed => $appliedRestart()['applied']['directory_syncs'], 1],
    'vfs restart atomic' => [static fn (): mixed => $appliedRestart()['applied']['atomic'], true],
    'vfs restart commit plan attached' => [static fn (): mixed => $appliedRestart()['applied']['commit_current']['savepoint'], 'plugin-settings'],
    'vfs restart database includes retained siteurl' => [static fn (): mixed => str_contains($appliedRestart()['database'], 'retained-siteurl-commit'), true],
    'vfs restart database excludes rolled back siteurl' => [static fn (): mixed => str_contains($appliedRestart()['database'], 'nested-rolled-back-siteurl'), false],
    'vfs restart wal removes stale tail' => [static fn (): mixed => str_contains($appliedRestart()['wal'], 'stale-rolled-back-tail'), false],
    'vfs restart wal header only' => [static fn (): mixed => strlen($appliedRestart()['wal']), 32],
    'vfs restart dependency marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-commit-current-vfs-apply72', $appliedRestart()['applied']['dependencies'], true), true],
    'vfs truncate wal empty' => [static fn (): mixed => strlen($appliedTruncate()['wal']), 0],
    'vfs truncate database includes retained header' => [static fn (): mixed => str_contains($appliedTruncate()['database'], 'retained-schema-frame'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint wal commit current next72 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty pages rejected' => static fn () => $plan('restart', null, null, []),
    'missing savepoint rejected' => static fn () => SQLiteWalSavepointCheckpointPlan::commitCurrentAfterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [1]),
    'bad mode rejected' => static fn () => $plan('invalid'),
    'non integer page rejected' => static fn () => $plan('restart', null, null, ['2']),
    'empty database path rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applySavepointCommitCurrent($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, '', [1]),
    'read only writer rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir(), true))->applySavepointCommitCurrent($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, $databasePath, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint wal commit current next72 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
