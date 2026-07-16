<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempFileOpenLifecycle;

$plan = static fn (array $ops, array $options = []): array => SQLiteVfsTempFileOpenLifecycle::tempFileOpenLifecycleSequence(
    $ops,
    $options + ['temp_dir' => '/tmp/wp-cache', 'connection_id' => 'WP Import 7'],
);

$opened = static fn (): array => $plan(['open(journal)']);
$twoOpen = static fn (): array => $plan(['open(journal)', 'open(sorter)']);
$closed = static fn (): array => $plan(['open(journal)', 'close(temp-wp-import-7-1)']);
$committed = static fn (): array => $plan(['open(journal)', 'commit']);
$memory = static fn (): array => $plan(['open(journal)'], ['temp_store' => 'memory']);
$fallback = static fn (): array => $plan(['open(journal)'], ['directory_writable' => false]);
$custom = static fn (): array => $plan([
    ['op' => 'open', 'suffix' => 'stmt-journal', 'delete_on_close' => false, 'exclusive' => false],
]);

return [
    'vfs tempfile lifecycle temp file open lifecycle sequence open status' => static fn (TestRunner $t) => $t->same('temp-open', $opened()['events'][0]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence result status follows open' => static fn (TestRunner $t) => $t->same('temp-open', $opened()['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence normalizes connection id' => static fn (TestRunner $t) => $t->same('temp-wp-import-7-1', $opened()['events'][0]['handle']),
    'vfs tempfile lifecycle temp file open lifecycle sequence path uses temp dir' => static fn (TestRunner $t) => $t->same('/tmp/wp-cache/sqlite-wp-import-7-000001.journal', $opened()['events'][0]['path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence handle path matches event path' => static fn (TestRunner $t) => $t->same($opened()['events'][0]['path'], $opened()['current']['handles']['temp-wp-import-7-1']['path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence basename is deterministic' => static fn (TestRunner $t) => $t->same('sqlite-wp-import-7-000001.journal', $opened()['current']['handles']['temp-wp-import-7-1']['basename']),
    'vfs tempfile lifecycle temp file open lifecycle sequence suffix gains dot' => static fn (TestRunner $t) => $t->same('.journal', $opened()['current']['handles']['temp-wp-import-7-1']['suffix']),
    'vfs tempfile lifecycle temp file open lifecycle sequence delete on close default true' => static fn (TestRunner $t) => $t->same(true, $opened()['events'][0]['delete_on_close']),
    'vfs tempfile lifecycle temp file open lifecycle sequence exclusive default true' => static fn (TestRunner $t) => $t->same(true, $opened()['events'][0]['exclusive']),
    'vfs tempfile lifecycle temp file open lifecycle sequence open is not memory by default' => static fn (TestRunner $t) => $t->same(false, $opened()['events'][0]['memory']),
    'vfs tempfile lifecycle temp file open lifecycle sequence journal path equals temp path' => static fn (TestRunner $t) => $t->same('/tmp/wp-cache/sqlite-wp-import-7-000001.journal', $opened()['current']['handles']['temp-wp-import-7-1']['journal_path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence wal path empty' => static fn (TestRunner $t) => $t->same('', $opened()['current']['handles']['temp-wp-import-7-1']['wal_path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence shm path empty' => static fn (TestRunner $t) => $t->same('', $opened()['current']['handles']['temp-wp-import-7-1']['shm_path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence shared memory false' => static fn (TestRunner $t) => $t->same(false, $opened()['current']['handles']['temp-wp-import-7-1']['shared_memory']),
    'vfs tempfile lifecycle temp file open lifecycle sequence open flags include temp db' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_TEMP_DB', $opened()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence open flags include readwrite' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_READWRITE', $opened()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence open flags include create' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_CREATE', $opened()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence open flags include deleteonclose' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_DELETEONCLOSE', $opened()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence open flags include exclusive' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_EXCLUSIVE', $opened()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence dependencies include lifecycle' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-tempfile-open-lifecycle', $opened()['dependencies'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence dependencies include deleteonclose' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-xopen-deleteonclose', $opened()['dependencies'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence dependencies include exclusive lock' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-exclusive-lock', $opened()['dependencies'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence current sequence advances' => static fn (TestRunner $t) => $t->same(1, $opened()['current']['sequence']),
    'vfs tempfile lifecycle temp file open lifecycle sequence current last open set' => static fn (TestRunner $t) => $t->same('temp-wp-import-7-1', $opened()['current']['last_open']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next open count one' => static fn (TestRunner $t) => $t->same(1, $opened()['next']['open_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next pending delete one' => static fn (TestRunner $t) => $t->same(1, $opened()['next']['pending_delete_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next requires directory write' => static fn (TestRunner $t) => $t->same(true, $opened()['next']['requires_directory_write']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next path list contains handle path' => static fn (TestRunner $t) => $t->same(['/tmp/wp-cache/sqlite-wp-import-7-000001.journal'], $opened()['next']['paths']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next uses no wal' => static fn (TestRunner $t) => $t->same(false, $opened()['next']['uses_wal']),
    'vfs tempfile lifecycle temp file open lifecycle sequence next uses no shm' => static fn (TestRunner $t) => $t->same(false, $opened()['next']['uses_shm']),
    'vfs tempfile lifecycle temp file open lifecycle sequence second open sequence' => static fn (TestRunner $t) => $t->same('temp-wp-import-7-2', $twoOpen()['events'][1]['handle']),
    'vfs tempfile lifecycle temp file open lifecycle sequence second open suffix' => static fn (TestRunner $t) => $t->same('.sorter', $twoOpen()['current']['handles']['temp-wp-import-7-2']['suffix']),
    'vfs tempfile lifecycle temp file open lifecycle sequence two opens next count' => static fn (TestRunner $t) => $t->same(2, $twoOpen()['next']['open_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence two opens pending delete count' => static fn (TestRunner $t) => $t->same(2, $twoOpen()['next']['pending_delete_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence close status' => static fn (TestRunner $t) => $t->same('closed', $closed()['events'][1]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence close deletes file temp' => static fn (TestRunner $t) => $t->same(true, $closed()['events'][1]['deleted']),
    'vfs tempfile lifecycle temp file open lifecycle sequence close removes handle' => static fn (TestRunner $t) => $t->same(0, $closed()['next']['open_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence close clears pending delete' => static fn (TestRunner $t) => $t->same(0, $closed()['next']['pending_delete_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence close no directory write needed after deletion' => static fn (TestRunner $t) => $t->same(false, $closed()['next']['requires_directory_write']),
    'vfs tempfile lifecycle temp file open lifecycle sequence missing close reports missing handle' => static fn (TestRunner $t) => $t->same('missing-handle', $plan(['close(nope)'])['events'][0]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence commit defers close' => static fn (TestRunner $t) => $t->same('deferred-close', $committed()['events'][1]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence commit leaves open handle' => static fn (TestRunner $t) => $t->same(['temp-wp-import-7-1'], $committed()['events'][1]['open_handles']),
    'vfs tempfile lifecycle temp file open lifecycle sequence commit reports pending delete' => static fn (TestRunner $t) => $t->same(1, $committed()['events'][1]['delete_on_close_pending']),
    'vfs tempfile lifecycle temp file open lifecycle sequence rollback defers close' => static fn (TestRunner $t) => $t->same('deferred-close', $plan(['open(journal)', 'rollback'])['events'][1]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence memory temp status' => static fn (TestRunner $t) => $t->same('memory-temp-open', $memory()['events'][0]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence memory temp has empty path' => static fn (TestRunner $t) => $t->same('', $memory()['events'][0]['path']),
    'vfs tempfile lifecycle temp file open lifecycle sequence memory temp not pending filesystem delete' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['pending_delete_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence memory temp increments memory count' => static fn (TestRunner $t) => $t->same(1, $memory()['next']['memory_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence memory flags include memory' => static fn (TestRunner $t) => $t->same(true, in_array('SQLITE_OPEN_MEMORY', $memory()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence directory fallback uses memory' => static fn (TestRunner $t) => $t->same(true, $fallback()['events'][0]['memory']),
    'vfs tempfile lifecycle temp file open lifecycle sequence directory fallback status' => static fn (TestRunner $t) => $t->same('memory-temp-open', $fallback()['events'][0]['status']),
    'vfs tempfile lifecycle temp file open lifecycle sequence custom non delete has zero pending delete' => static fn (TestRunner $t) => $t->same(0, $custom()['next']['pending_delete_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence custom non exclusive omits exclusive flag' => static fn (TestRunner $t) => $t->same(false, in_array('SQLITE_OPEN_EXCLUSIVE', $custom()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence custom non delete omits delete flag' => static fn (TestRunner $t) => $t->same(false, in_array('SQLITE_OPEN_DELETEONCLOSE', $custom()['current']['handles']['temp-wp-import-7-1']['flags'], true)),
    'vfs tempfile lifecycle temp file open lifecycle sequence custom suffix normalizes' => static fn (TestRunner $t) => $t->same('.stmt-journal', $custom()['current']['handles']['temp-wp-import-7-1']['suffix']),
    'vfs tempfile lifecycle temp file open lifecycle sequence preserves supplied current sequence' => static fn (TestRunner $t) => $t->same('temp-wp-import-7-8', $plan(['open(journal)'], ['current' => ['sequence' => 7]])['events'][0]['handle']),
    'vfs tempfile lifecycle temp file open lifecycle sequence preserves supplied current handles' => static fn (TestRunner $t) => $t->same(2, $plan(['open(sorter)'], ['current' => ['sequence' => 1, 'handles' => ['existing' => ['path' => '/tmp/existing', 'delete_on_close' => true, 'memory' => false]]]])['next']['open_count']),
    'vfs tempfile lifecycle temp file open lifecycle sequence rejects bad suffix slash' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(../bad)'])),
    'vfs tempfile lifecycle temp file open lifecycle sequence rejects bad suffix nul' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(["open(bad\0name)"])),
    'vfs tempfile lifecycle temp file open lifecycle sequence rejects empty temp dir' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTempFileOpenLifecycle::tempFileOpenLifecycleSequence(['open(journal)'], ['temp_dir' => ''])),
    'vfs tempfile lifecycle temp file open lifecycle sequence rejects unsupported sql shape' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['file_control(tempfile)'])),
];
