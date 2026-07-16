<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalStatementRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$mainPath = '/wp-content/database/.ht.sqlite';
$metaPath = '/wp-content/database/wp_meta.sqlite';
$superPath = '/wp-content/database/wp-import-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$journalBytes = static function (array $pages, int $initialPageCount, int $nonce) use ($pageSize): string {
    $sectorSize = 512;
    $bytes = str_pad(
        SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize),
        $sectorSize,
        "\0"
    );
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$mainClean = [
    1 => $page('main clean sqlite header'),
    2 => $page('main clean wp_options siteurl'),
    3 => $page('main clean active plugins'),
    4 => $page('main clean transient statement'),
];
$mainDirty = $page('main dirty sqlite header')
    . $page('main dirty wp_options siteurl')
    . $page('main dirty active plugins')
    . $page('main dirty transient statement');
$metaClean = [
    1 => $page('meta clean sqlite header'),
    2 => $page('meta clean optionmeta index'),
];
$metaDirty = $page('meta dirty sqlite header') . $page('meta dirty optionmeta index');
$mainJournal = $journalBytes($mainClean, 4, 0x75010001);
$metaJournal = $journalBytes($metaClean, 2, 0x75010002);
$superBytes = $mainPath . "-journal\n" . $metaPath . "-journal\n";
$statementBeforeImage = $page('main clean transient before failed statement');
$nextBeforeImage = $page('main clean retry before next statement');

$makeStack = static function () use ($statementBeforeImage): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, str_repeat('outer-before-header', 16));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('plugin-options');
    $stack->beginStatementJournal('insert-transient');
    $stack->recordStatementPageImageWrite('insert-transient', 4, $statementBeforeImage);
    $stack->recordStatementWalFrameWrite('insert-transient', 2, 4, true);

    return $stack;
};

$databases = static fn (bool $metaReserved = false): array => [
    [
        'database_path' => $mainPath,
        'database_bytes' => $mainDirty,
        'journal_bytes' => $mainJournal,
    ],
    [
        'database_path' => $metaPath,
        'database_bytes' => $metaDirty,
        'journal_bytes' => $metaJournal,
        'reserved_lock' => $metaReserved,
    ],
];

$plan = static fn (bool $metaReserved = false): array => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext(
    $superPath,
    $superBytes,
    $databases($metaReserved),
    $pageSize,
    $makeStack(),
    'insert-transient',
    'retry-transient',
    5,
    $nextBeforeImage,
    true,
    $mainPath
);

$blocked = static fn (): array => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext(
    $superPath,
    null,
    $databases(),
    $pageSize,
    $makeStack(),
    'insert-transient',
    'retry-transient',
    5,
    $nextBeforeImage,
    false,
    $mainPath
);

$apply = static function () use ($superPath, $superBytes, $databases, $pageSize, $makeStack, $nextBeforeImage, $mainPath, $mainDirty, $metaPath, $metaDirty, $mainJournal, $metaJournal): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-master-stmt75-' . bin2hex(random_bytes(4));
    $localMain = $root . $mainPath;
    $localMeta = $root . $metaPath;
    if (!is_dir(dirname($localMain)) && !mkdir(dirname($localMain), 0777, true) && !is_dir(dirname($localMain))) {
        throw new RuntimeException('Unable to create master statement recovery fixture');
    }
    file_put_contents($localMain, $mainDirty);
    file_put_contents($localMain . '-journal', $mainJournal);
    file_put_contents($localMeta, $metaDirty);
    file_put_contents($localMeta . '-journal', $metaJournal);
    file_put_contents($root . $superPath, $mainPath . "-journal\n" . $metaPath . "-journal\n");

    $applied = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementRecovery(
        $superPath,
        $superBytes,
        $databases(),
        $pageSize,
        $makeStack(),
        'insert-transient',
        'retry-transient',
        5,
        $nextBeforeImage,
        true,
        $mainPath
    );

    return [
        'applied' => $applied,
        'main' => (string) file_get_contents($localMain),
        'meta' => (string) file_get_contents($localMeta),
        'main_journal_exists' => is_file($localMain . '-journal'),
        'meta_journal_exists' => is_file($localMeta . '-journal'),
        'super_exists' => is_file($root . $superPath),
    ];
};

$cases = [
    'status recovered' => [static fn (): mixed => $plan()['status'], 'recovered'],
    'reason names master before statement' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovered_before_statement_subjournal_retry'],
    'primary path preserved' => [static fn (): mixed => $plan()['primary_database_path'], $mainPath],
    'super status complete' => [static fn (): mixed => $plan()['super_recovery']['status'], 'super_journal_hot_recovery_complete'],
    'super recovered count' => [static fn (): mixed => $plan()['super_recovery']['recovered_count'], 2],
    'super blocked count' => [static fn (): mixed => $plan()['super_recovery']['blocked_count'], 0],
    'statement current name' => [static fn (): mixed => $plan()['statement_recovery']['current_statement'], 'insert-transient'],
    'statement next name' => [static fn (): mixed => $plan()['statement_recovery']['next_statement'], 'retry-transient'],
    'statement savepoint' => [static fn (): mixed => $plan()['statement_recovery']['savepoint'], 'plugin-options'],
    'statement rollback frame' => [static fn (): mixed => $plan()['statement_recovery']['rollback_to_wal_frame'], 1],
    'statement next frame' => [static fn (): mixed => $plan()['statement_recovery']['next_wal_frame_index'], 2],
    'statement next page' => [static fn (): mixed => $plan()['statement_recovery']['next_page_number'], 5],
    'statement next commit frame' => [static fn (): mixed => $plan()['statement_recovery']['next_commit_frame'], true],
    'statement restored page' => [static fn (): mixed => $plan()['statement_recovery']['rollback_restored_page_numbers'], [4]],
    'statement discarded frame' => [static fn (): mixed => array_column($plan()['statement_recovery']['rollback_discarded_wal_frames'], 'frame_index'), [2]],
    'statement journals after rollback empty' => [static fn (): mixed => $plan()['statement_recovery']['statement_journals_after_rollback'], []],
    'statement journals after next name' => [static fn (): mixed => $plan()['statement_recovery']['statement_journals_after_next'][0]['name'], 'retry-transient'],
    'statement journals after next savepoint' => [static fn (): mixed => $plan()['statement_recovery']['statement_journals_after_next'][0]['savepoint'], 'plugin-options'],
    'pending page numbers after next' => [static fn (): mixed => $plan()['statement_recovery']['pending_page_numbers_after_next'], [1, 5]],
    'pending wal frames after next' => [static fn (): mixed => $plan()['statement_recovery']['pending_wal_frame_indexes_after_next'], [1, 2]],
    'main current has dirty statement' => [static fn (): mixed => str_contains($plan()['current_database_bytes'], 'main dirty transient statement'), true],
    'main next has clean siteurl' => [static fn (): mixed => str_contains($plan()['next_database_bytes'], 'main clean wp_options siteurl'), true],
    'main next before statement still journal clean transient' => [static fn (): mixed => str_contains($plan()['next_database_bytes'], 'main clean transient statement'), true],
    'statement database restores failed statement page' => [static fn (): mixed => str_contains((string) $plan()['statement_database_bytes'], 'main clean transient before failed statement'), true],
    'statement database keeps master recovered page' => [static fn (): mixed => str_contains((string) $plan()['statement_database_bytes'], 'main clean active plugins'), true],
    'statement database excludes dirty siteurl' => [static fn (): mixed => str_contains((string) $plan()['statement_database_bytes'], 'main dirty wp_options siteurl'), false],
    'statement database excludes dirty statement' => [static fn (): mixed => str_contains((string) $plan()['statement_database_bytes'], 'main dirty transient statement'), false],
    'main journal action delete' => [static fn (): mixed => $plan()['super_recovery']['journal_actions'][$mainPath . '-journal'], 'delete_journal_after_recovery'],
    'meta journal action delete' => [static fn (): mixed => $plan()['super_recovery']['journal_actions'][$metaPath . '-journal'], 'delete_journal_after_recovery'],
    'operation count includes statement recovery' => [static fn (): mixed => count($plan()['operations']), 14],
    'last write reason statement recovery' => [static fn (): mixed => $plan()['operations'][10]['reason'], 'restore_statement_subjournal_after_master_recovery'],
    'last sync reason statement recovery' => [static fn (): mixed => $plan()['operations'][12]['reason'], 'sync_statement_recovery_after_master_journal'],
    'last directory sync reason' => [static fn (): mixed => $plan()['operations'][13]['reason'], 'persist_statement_recovery_after_master_journal'],
    'payload has statement image' => [static fn (): mixed => array_key_exists($mainPath . '#statement-recovery75', $plan()['payloads']), true],
    'dependency master statement' => [static fn (): mixed => in_array('sqlite-master-journal-statement-recovery-current-next75', $plan()['dependencies'], true), true],
    'dependency statement subjournal' => [static fn (): mixed => in_array('sqlite-statement-subjournal-after-hot-master-recovery', $plan()['dependencies'], true), true],
    'dependency super recovery' => [static fn (): mixed => in_array('sqlite-super-journal-hot-recovery', $plan()['dependencies'], true), true],
    'partial status with reserved meta lock' => [static fn (): mixed => $plan(true)['status'], 'partial'],
    'partial recovered count' => [static fn (): mixed => $plan(true)['super_recovery']['recovered_count'], 1],
    'partial blocked count' => [static fn (): mixed => $plan(true)['super_recovery']['blocked_count'], 1],
    'partial main still statement recovered' => [static fn (): mixed => str_contains((string) $plan(true)['statement_database_bytes'], 'main clean transient before failed statement'), true],
    'blocked status without super journal' => [static fn (): mixed => $blocked()['status'], 'blocked'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'master_journal_recovery_blocked_before_statement_rollback'],
    'blocked statement null' => [static fn (): mixed => $blocked()['statement_recovery'], null],
    'blocked operations empty' => [static fn (): mixed => $blocked()['operations'], []],
    'blocked dependencies include marker' => [static fn (): mixed => in_array('sqlite-master-journal-statement-recovery-current-next75', $blocked()['dependencies'], true), true],
    'vfs apply status' => [static fn (): mixed => $apply()['applied']['status'], 'applied'],
    'vfs apply atomic' => [static fn (): mixed => $apply()['applied']['atomic'], true],
    'vfs operation count' => [static fn (): mixed => $apply()['applied']['applied'], 14],
    'vfs durable syncs' => [static fn (): mixed => $apply()['applied']['durable_syncs'], 3],
    'vfs directory syncs' => [static fn (): mixed => $apply()['applied']['directory_syncs'], 2],
    'vfs deletes journals and super' => [static fn (): mixed => [$apply()['main_journal_exists'], $apply()['meta_journal_exists'], $apply()['super_exists']], [false, false, false]],
    'vfs main has statement rollback page' => [static fn (): mixed => str_contains($apply()['main'], 'main clean transient before failed statement'), true],
    'vfs main has master recovered page' => [static fn (): mixed => str_contains($apply()['main'], 'main clean wp_options siteurl'), true],
    'vfs main excludes dirty page' => [static fn (): mixed => str_contains($apply()['main'], 'main dirty transient statement'), false],
    'vfs meta recovered page' => [static fn (): mixed => str_contains($apply()['meta'], 'meta clean optionmeta index'), true],
    'vfs dependency marker' => [static fn (): mixed => in_array('sqlite-master-journal-statement-recovery-current-next75', $apply()['applied']['dependencies'], true), true],
    'vfs recovery status carried' => [static fn (): mixed => $apply()['applied']['recovery']['status'], 'recovered'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal statement recovery current next75 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty statement rejected' => static fn () => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext($superPath, $superBytes, $databases(), $pageSize, $makeStack(), '', 'next', 5, $nextBeforeImage),
    'empty next statement rejected' => static fn () => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext($superPath, $superBytes, $databases(), $pageSize, $makeStack(), 'insert-transient', '', 5, $nextBeforeImage),
    'zero next page rejected' => static fn () => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext($superPath, $superBytes, $databases(), $pageSize, $makeStack(), 'insert-transient', 'next', 0, $nextBeforeImage),
    'bad next image rejected' => static fn () => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext($superPath, $superBytes, $databases(), $pageSize, $makeStack(), 'insert-transient', 'next', 5, 'short'),
    'missing primary rejected' => static fn () => SQLitePagerMasterJournalStatementRecoveryPlan::currentNext($superPath, $superBytes, $databases(), $pageSize, $makeStack(), 'insert-transient', 'next', 5, $nextBeforeImage, false, '/missing.sqlite'),
    'read only writer rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir(), true))->applyMasterJournalStatementRecovery($superPath, $superBytes, $databases(), $pageSize, $makeStack(), 'insert-transient', 'next', 5, $nextBeforeImage),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal statement recovery current next75 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
