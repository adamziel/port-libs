<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempLockingFileControlCurrentSourcePlan;

$plan = static fn (array $ops, array $options = []): array => SQLiteVfsTempLockingFileControlCurrentSourcePlan::planTempLockingFileControl(
    $ops,
    $options + ['temp_dir' => '/tmp/wp-cache', 'connection_id' => 'WP Import 83'],
);

$shadowed = static fn (): array => $plan([
    ['op' => 'open', 'source' => 'main', 'suffix' => 'db', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'source' => 'main', 'control' => 'name_hint', 'value' => 'main wp_options'],
    ['op' => 'lock', 'source' => 'main', 'value' => 'shared'],
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => 'temp import'],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 4096],
    ['op' => 'lock', 'value' => 'reserved'],
]);

$qualified = static fn (): array => $plan([
    'open(main.db)',
    'file_control(main.name_hint, "main catalog")',
    'open(temp.stmt-journal)',
    'file_control(name_hint, "temp journal")',
    'file_control(main.chunk_size, 8192)',
    'lock(exclusive)',
]);

$reopen = static fn (): array => $plan([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => 'persisted temp'],
    ['op' => 'filecontrol', 'control' => 'persist_wal', 'value' => 'on'],
    ['op' => 'lock', 'value' => 'exclusive'],
    ['op' => 'close', 'source' => 'temp'],
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
]);

$sourceSwitch = static fn (): array => $plan([
    ['op' => 'open', 'source' => 'main', 'suffix' => 'db', 'delete_on_close' => false],
    ['op' => 'open', 'source' => 'attached', 'suffix' => 'site-db', 'delete_on_close' => false],
    'source(main)',
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 2048],
    'source(attached)',
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 6144],
    ['op' => 'lock', 'value' => 'shared'],
]);

$deleteOnClose = static fn (): array => $plan([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'sorter', 'delete_on_close' => true],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 1024],
    ['op' => 'lock', 'value' => 'exclusive'],
    ['op' => 'close', 'source' => 'temp'],
]);

$memory = static fn (): array => $plan([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'sorter', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 512],
    ['op' => 'lock', 'value' => 'shared'],
    ['op' => 'close', 'source' => 'temp'],
], ['temp_store' => 'memory']);

return [
    'vfs temp locking filecontrol current source next83 shadow status' => static fn (TestRunner $t) => $t->same('ok', $shadowed()['status']),
    'vfs temp locking filecontrol current source next83 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-locking-filecontrol', $shadowed()['dependencies'], true)),
    'vfs temp locking filecontrol current source next83 main open source' => static fn (TestRunner $t) => $t->same('main', $shadowed()['events'][0]['source']),
    'vfs temp locking filecontrol current source next83 temp open source' => static fn (TestRunner $t) => $t->same('temp', $shadowed()['events'][3]['source']),
    'vfs temp locking filecontrol current source next83 main handle mapped' => static fn (TestRunner $t) => $t->same('temp-wp-import-83-1', $shadowed()['current']['source_handles']['main']),
    'vfs temp locking filecontrol current source next83 temp handle mapped' => static fn (TestRunner $t) => $t->same('temp-wp-import-83-2', $shadowed()['current']['source_handles']['temp']),
    'vfs temp locking filecontrol current source next83 current source is temp after temp open' => static fn (TestRunner $t) => $t->same('temp', $shadowed()['next']['current_source']),
    'vfs temp locking filecontrol current source next83 unqualified filecontrol routes temp' => static fn (TestRunner $t) => $t->same('temp', $shadowed()['events'][4]['source']),
    'vfs temp locking filecontrol current source next83 unqualified lock routes temp' => static fn (TestRunner $t) => $t->same('temp', $shadowed()['events'][6]['source']),
    'vfs temp locking filecontrol current source next83 main name preserved' => static fn (TestRunner $t) => $t->same('main wp_options', $shadowed()['current']['handles']['temp-wp-import-83-1']['controls']['name_hint']),
    'vfs temp locking filecontrol current source next83 temp name preserved' => static fn (TestRunner $t) => $t->same('temp import', $shadowed()['current']['handles']['temp-wp-import-83-2']['controls']['name_hint']),
    'vfs temp locking filecontrol current source next83 temp chunk preserved' => static fn (TestRunner $t) => $t->same(4096, $shadowed()['current']['handles']['temp-wp-import-83-2']['controls']['chunk_size']),
    'vfs temp locking filecontrol current source next83 main lock shared' => static fn (TestRunner $t) => $t->same('shared', $shadowed()['current']['handles']['temp-wp-import-83-1']['lock_state']),
    'vfs temp locking filecontrol current source next83 temp lock reserved' => static fn (TestRunner $t) => $t->same('reserved', $shadowed()['current']['handles']['temp-wp-import-83-2']['lock_state']),
    'vfs temp locking filecontrol current source next83 open by source main' => static fn (TestRunner $t) => $t->same(1, $shadowed()['next']['open_by_source']['main']),
    'vfs temp locking filecontrol current source next83 open by source temp' => static fn (TestRunner $t) => $t->same(1, $shadowed()['next']['open_by_source']['temp']),
    'vfs temp locking filecontrol current source next83 locked by source main' => static fn (TestRunner $t) => $t->same(1, $shadowed()['next']['locked_by_source']['main']),
    'vfs temp locking filecontrol current source next83 locked by source temp' => static fn (TestRunner $t) => $t->same(1, $shadowed()['next']['locked_by_source']['temp']),
    'vfs temp locking filecontrol current source next83 persistent controls counted' => static fn (TestRunner $t) => $t->same(2, $shadowed()['next']['persistent_control_count']),
    'vfs temp locking filecontrol current source next83 persistent locks counted' => static fn (TestRunner $t) => $t->same(2, $shadowed()['next']['persistent_lock_count']),
    'vfs temp locking filecontrol current source next83 no pending delete for persistent handles' => static fn (TestRunner $t) => $t->same(0, $shadowed()['next']['pending_delete_count']),

    'vfs temp locking filecontrol current source next83 qualified status' => static fn (TestRunner $t) => $t->same('ok', $qualified()['status']),
    'vfs temp locking filecontrol current source next83 string main source open' => static fn (TestRunner $t) => $t->same('main', $qualified()['events'][0]['source']),
    'vfs temp locking filecontrol current source next83 string temp source open' => static fn (TestRunner $t) => $t->same('temp', $qualified()['events'][2]['source']),
    'vfs temp locking filecontrol current source next83 schema qualified main control after temp open' => static fn (TestRunner $t) => $t->same('main', $qualified()['events'][4]['source']),
    'vfs temp locking filecontrol current source next83 schema qualified main chunk' => static fn (TestRunner $t) => $t->same(8192, $qualified()['current']['handles']['temp-wp-import-83-1']['controls']['chunk_size']),
    'vfs temp locking filecontrol current source next83 unqualified lock after temp open is temp' => static fn (TestRunner $t) => $t->same('temp', $qualified()['events'][5]['source']),
    'vfs temp locking filecontrol current source next83 qualified main lock remains unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $qualified()['current']['handles']['temp-wp-import-83-1']['lock_state']),
    'vfs temp locking filecontrol current source next83 qualified temp lock exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $qualified()['current']['handles']['temp-wp-import-83-2']['lock_state']),
    'vfs temp locking filecontrol current source next83 qualified keys include source prefix' => static fn (TestRunner $t) => $t->same(true, str_starts_with(array_keys($qualified()['current']['persistent_controls'])[0], 'main:')),

    'vfs temp locking filecontrol current source next83 reopen status' => static fn (TestRunner $t) => $t->same('temp-open', $reopen()['status']),
    'vfs temp locking filecontrol current source next83 close persists controls' => static fn (TestRunner $t) => $t->same(true, $reopen()['events'][4]['persisted_controls']),
    'vfs temp locking filecontrol current source next83 close unlocks persistent lock' => static fn (TestRunner $t) => $t->same('unlocked', array_values($reopen()['events'][4]['next']['persistent_locks'])[0]),
    'vfs temp locking filecontrol current source next83 reopen reuses controls' => static fn (TestRunner $t) => $t->same(true, $reopen()['events'][5]['reused_controls']),
    'vfs temp locking filecontrol current source next83 reopen name retained' => static fn (TestRunner $t) => $t->same('persisted temp', $reopen()['current']['handles']['temp-wp-import-83-2']['controls']['name_hint']),
    'vfs temp locking filecontrol current source next83 reopen bool retained' => static fn (TestRunner $t) => $t->same(true, $reopen()['current']['handles']['temp-wp-import-83-2']['controls']['persist_wal']),
    'vfs temp locking filecontrol current source next83 reopen lock starts unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $reopen()['current']['handles']['temp-wp-import-83-2']['lock_state']),
    'vfs temp locking filecontrol current source next83 reopen one persistent control set' => static fn (TestRunner $t) => $t->same(1, $reopen()['next']['persistent_control_count']),

    'vfs temp locking filecontrol current source next83 source switch main event' => static fn (TestRunner $t) => $t->same('main', $sourceSwitch()['events'][2]['source']),
    'vfs temp locking filecontrol current source next83 source switch attached event' => static fn (TestRunner $t) => $t->same('attached', $sourceSwitch()['events'][4]['source']),
    'vfs temp locking filecontrol current source next83 source switch main control' => static fn (TestRunner $t) => $t->same(2048, $sourceSwitch()['current']['handles']['temp-wp-import-83-1']['controls']['chunk_size']),
    'vfs temp locking filecontrol current source next83 source switch attached control' => static fn (TestRunner $t) => $t->same(6144, $sourceSwitch()['current']['handles']['temp-wp-import-83-2']['controls']['chunk_size']),
    'vfs temp locking filecontrol current source next83 attached lock shared' => static fn (TestRunner $t) => $t->same('shared', $sourceSwitch()['current']['handles']['temp-wp-import-83-2']['lock_state']),
    'vfs temp locking filecontrol current source next83 attached open count' => static fn (TestRunner $t) => $t->same(1, $sourceSwitch()['next']['open_by_source']['attached']),
    'vfs temp locking filecontrol current source next83 current source attached at end' => static fn (TestRunner $t) => $t->same('attached', $sourceSwitch()['next']['current_source']),

    'vfs temp locking filecontrol current source next83 delete close status' => static fn (TestRunner $t) => $t->same('closed', $deleteOnClose()['status']),
    'vfs temp locking filecontrol current source next83 delete close deleted' => static fn (TestRunner $t) => $t->same(true, $deleteOnClose()['events'][3]['deleted']),
    'vfs temp locking filecontrol current source next83 delete close clears controls' => static fn (TestRunner $t) => $t->same(0, $deleteOnClose()['next']['persistent_control_count']),
    'vfs temp locking filecontrol current source next83 delete close clears locks' => static fn (TestRunner $t) => $t->same(0, $deleteOnClose()['next']['persistent_lock_count']),
    'vfs temp locking filecontrol current source next83 delete close clears source handle' => static fn (TestRunner $t) => $t->same(false, isset($deleteOnClose()['current']['source_handles']['temp'])),
    'vfs temp locking filecontrol current source next83 delete close no directory write needed' => static fn (TestRunner $t) => $t->same(false, $deleteOnClose()['next']['requires_directory_write']),

    'vfs temp locking filecontrol current source next83 memory status' => static fn (TestRunner $t) => $t->same('closed', $memory()['status']),
    'vfs temp locking filecontrol current source next83 memory open status' => static fn (TestRunner $t) => $t->same('memory-temp-open', $memory()['events'][0]['status']),
    'vfs temp locking filecontrol current source next83 memory close not deleted' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['deleted']),
    'vfs temp locking filecontrol current source next83 memory controls not persisted' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_control_count']),
    'vfs temp locking filecontrol current source next83 memory locks not persisted' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_lock_count']),

    'vfs temp locking filecontrol current source next83 missing source control' => static fn (TestRunner $t) => $t->same('missing-handle', $plan([['op' => 'filecontrol', 'source' => 'temp', 'control' => 'chunk_size', 'value' => 1]])['status']),
    'vfs temp locking filecontrol current source next83 missing source lock' => static fn (TestRunner $t) => $t->same('missing-handle', $plan([['op' => 'lock', 'source' => 'main', 'value' => 'shared']])['status']),
    'vfs temp locking filecontrol current source next83 rejects bad source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['op' => 'open', 'source' => 'aux', 'suffix' => 'db']])),
    'vfs temp locking filecontrol current source next83 rejects bad lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['op' => 'open', 'source' => 'temp', 'suffix' => 'journal'], ['op' => 'lock', 'value' => 'bogus']])),
    'vfs temp locking filecontrol current source next83 rejects bad suffix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(['open(temp../bad)'])),
    'vfs temp locking filecontrol current source next83 rejects bad chunk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['op' => 'open', 'source' => 'temp', 'suffix' => 'journal'], ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]])),
    'vfs temp locking filecontrol current source next83 rejects empty name hint' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan([['op' => 'open', 'source' => 'temp', 'suffix' => 'journal'], ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']])),
];
