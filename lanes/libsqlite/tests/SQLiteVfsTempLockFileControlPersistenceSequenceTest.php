<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempLockFileControlPersistence;

$plan = static fn (array $ops, array $options = []): array => SQLiteVfsTempLockFileControlPersistence::tempLockFileControlPersistenceSequence(
    $ops,
    $options + ['temp_dir' => '/tmp/wp-cache', 'connection_id' => 'WP Import 76'],
);

$opened = static fn (): array => $plan(['open(journal)']);
$controlled = static fn (): array => $plan(['open(journal)', 'file_control(name_hint, "wp import")', 'file_control(chunk_size, 8192)', 'lock(exclusive)']);
$closedDelete = static fn (): array => $plan(['open(journal)', 'file_control(chunk_size, 8192)', 'lock(exclusive)', 'close']);
$persistent = static fn (): array => $plan([
    ['op' => 'open', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => 'wp importer'],
    ['op' => 'filecontrol', 'control' => 'persist_wal', 'value' => 'on'],
    ['op' => 'lock', 'value' => 'reserved'],
    ['op' => 'close'],
]);
$memory = static fn (): array => $plan([
    ['op' => 'open', 'suffix' => 'sorter', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 4096],
    ['op' => 'lock', 'value' => 'shared'],
    ['op' => 'close'],
], ['temp_store' => 'memory']);

return [
    'vfs temp lock filecontrol temp lock file-control persistence sequence open status' => static fn (TestRunner $t) => $t->same('temp-open', $opened()['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-lock-filecontrol-persistence', $opened()['dependencies'], true)),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open handle id' => static fn (TestRunner $t) => $t->same('temp-wp-import-76-1', $opened()['events'][0]['handle']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open path' => static fn (TestRunner $t) => $t->same('/tmp/wp-cache/sqlite-wp-import-76-000001.journal', $opened()['events'][0]['path']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open file control key is path' => static fn (TestRunner $t) => $t->same('/tmp/wp-cache/sqlite-wp-import-76-000001.journal', $opened()['events'][0]['file_control_key']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open starts unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $opened()['events'][0]['lock_state']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open next count' => static fn (TestRunner $t) => $t->same(1, $opened()['next']['open_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open pending delete count' => static fn (TestRunner $t) => $t->same(1, $opened()['next']['pending_delete_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open requires directory write' => static fn (TestRunner $t) => $t->same(true, $opened()['next']['requires_directory_write']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence open has no persistent controls' => static fn (TestRunner $t) => $t->same(0, $opened()['next']['persistent_control_count']),

    'vfs temp lock filecontrol temp lock file-control persistence sequence name hint status' => static fn (TestRunner $t) => $t->same('ok', $controlled()['events'][1]['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence name hint value' => static fn (TestRunner $t) => $t->same('wp import', $controlled()['events'][1]['value']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence name hint changed' => static fn (TestRunner $t) => $t->same(true, $controlled()['events'][1]['changed']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence name hint appears on handle next' => static fn (TestRunner $t) => $t->same('wp import', $controlled()['events'][1]['next']['handles']['temp-wp-import-76-1']['controls']['name_hint']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence chunk size parsed' => static fn (TestRunner $t) => $t->same(8192, $controlled()['events'][2]['value']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence chunk previous null' => static fn (TestRunner $t) => $t->same(null, $controlled()['events'][2]['previous']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence chunk persists in state' => static fn (TestRunner $t) => $t->same(8192, $controlled()['current']['handles']['temp-wp-import-76-1']['controls']['chunk_size']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence controls persist count while open' => static fn (TestRunner $t) => $t->same(1, $controlled()['next']['persistent_control_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence exclusive lock event' => static fn (TestRunner $t) => $t->same('exclusive', $controlled()['events'][3]['lock_state']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence exclusive lock appears on handle' => static fn (TestRunner $t) => $t->same('exclusive', $controlled()['current']['handles']['temp-wp-import-76-1']['lock_state']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence exclusive lock counted' => static fn (TestRunner $t) => $t->same(1, $controlled()['next']['persistent_lock_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence lock current includes prior controls' => static fn (TestRunner $t) => $t->same(8192, $controlled()['events'][3]['current']['handles']['temp-wp-import-76-1']['controls']['chunk_size']),

    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close status' => static fn (TestRunner $t) => $t->same('closed', $closedDelete()['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close deleted' => static fn (TestRunner $t) => $t->same(true, $closedDelete()['events'][3]['deleted']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close does not persist controls' => static fn (TestRunner $t) => $t->same(false, $closedDelete()['events'][3]['persisted_controls']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close clears handles' => static fn (TestRunner $t) => $t->same(0, $closedDelete()['next']['open_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close clears pending delete' => static fn (TestRunner $t) => $t->same(0, $closedDelete()['next']['pending_delete_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close clears persistent controls' => static fn (TestRunner $t) => $t->same(0, $closedDelete()['next']['persistent_control_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close clears persistent locks' => static fn (TestRunner $t) => $t->same(0, $closedDelete()['next']['persistent_lock_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence delete close lock unlocks' => static fn (TestRunner $t) => $t->same('unlocked', $closedDelete()['events'][3]['lock_state']),

    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent suffix normalizes' => static fn (TestRunner $t) => $t->same('.stmt-journal', $persistent()['events'][0]['next']['handles']['temp-wp-import-76-1']['suffix']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent close not deleted' => static fn (TestRunner $t) => $t->same(false, $persistent()['events'][4]['deleted']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent close persists controls' => static fn (TestRunner $t) => $t->same(true, $persistent()['events'][4]['persisted_controls']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent close keeps one control set' => static fn (TestRunner $t) => $t->same(1, $persistent()['next']['persistent_control_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent close releases lock count' => static fn (TestRunner $t) => $t->same(0, $persistent()['next']['persistent_lock_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent control key retained' => static fn (TestRunner $t) => $t->same(['/tmp/wp-cache/sqlite-wp-import-76-000001.stmt-journal'], array_keys($persistent()['current']['persistent_controls'])),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent name hint retained' => static fn (TestRunner $t) => $t->same('wp importer', $persistent()['current']['persistent_controls']['/tmp/wp-cache/sqlite-wp-import-76-000001.stmt-journal']['name_hint']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent boolean retained' => static fn (TestRunner $t) => $t->same(true, $persistent()['current']['persistent_controls']['/tmp/wp-cache/sqlite-wp-import-76-000001.stmt-journal']['persist_wal']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent lock entry unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $persistent()['current']['persistent_locks']['/tmp/wp-cache/sqlite-wp-import-76-000001.stmt-journal']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence persistent close no pending delete' => static fn (TestRunner $t) => $t->same(0, $persistent()['next']['pending_delete_count']),

    'vfs temp lock filecontrol temp lock file-control persistence sequence memory status' => static fn (TestRunner $t) => $t->same('closed', $memory()['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence memory open status' => static fn (TestRunner $t) => $t->same('memory-temp-open', $memory()['events'][0]['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence memory has no path' => static fn (TestRunner $t) => $t->same('', $memory()['events'][0]['path']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence memory controls do not persist after close' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_control_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence memory locks do not persist after close' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_lock_count']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence memory close not filesystem deleted' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['deleted']),

    'vfs temp lock filecontrol temp lock file-control persistence sequence missing close reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $plan(['close(nope)'])['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence missing filecontrol reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $plan([['op' => 'filecontrol', 'handle' => 'nope', 'control' => 'chunk_size', 'value' => 1]])['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence missing lock reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $plan([['op' => 'lock', 'handle' => 'nope', 'value' => 'shared']])['status']),
    'vfs temp lock filecontrol temp lock file-control persistence sequence rejects bad suffix slash' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(../bad)'])),
    'vfs temp lock filecontrol temp lock file-control persistence sequence rejects bad lock level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(journal)', 'lock(bogus)'])),
    'vfs temp lock filecontrol temp lock file-control persistence sequence rejects bad name hint' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(journal)', ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']])),
    'vfs temp lock filecontrol temp lock file-control persistence sequence rejects negative chunk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(journal)', ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]])),
];
