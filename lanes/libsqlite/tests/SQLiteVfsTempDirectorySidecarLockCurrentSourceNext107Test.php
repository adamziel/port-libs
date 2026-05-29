<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan;

$plan107 = static fn (array $ops, array $options = []): array => SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan::planTempDirectorySidecarLock(
    $ops,
    $options + ['temp_dir' => '/tmp/wp-a', 'connection_id' => 'WP Import 107'],
);

$move = static function () use ($plan107): array {
    static $result = null;
    if ($result === null) {
        $result = $plan107([
            ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
            ['op' => 'lock', 'source' => 'temp', 'value' => 'reserved'],
            ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'chunk_size', 'value' => 4096],
            ['op' => 'temp_directory', 'path' => '/tmp/wp-b'],
            ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
            ['op' => 'lock', 'source' => 'temp', 'value' => 'exclusive'],
            ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'size_hint', 'value' => 8192],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-107-1', 'control' => 'lock_timeout', 'value' => 50],
            ['op' => 'close', 'handle' => 'temp-wp-import-107-1'],
            ['op' => 'close', 'handle' => 'temp-wp-import-107-2'],
        ]);
    }

    return $result;
};

$attached = static fn (): array => $plan107([
    ['op' => 'open', 'source' => 'attached', 'suffix' => 'sorter', 'delete_on_close' => false],
    ['op' => 'lock', 'source' => 'attached', 'value' => 'shared'],
    ['op' => 'temp_directory', 'path' => '/tmp/wp-next'],
    ['op' => 'open', 'source' => 'attached', 'suffix' => 'sorter', 'delete_on_close' => true],
    ['op' => 'lock', 'source' => 'attached', 'value' => 'reserved'],
    ['op' => 'close', 'source' => 'attached'],
]);

$sources = static fn (): array => $plan107([
    ['op' => 'open', 'source' => 'main', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'lock', 'source' => 'main', 'value' => 'reserved'],
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'lock', 'source' => 'temp', 'value' => 'reserved'],
    ['op' => 'temp_directory', 'path' => '/tmp/wp-c'],
    ['op' => 'open', 'source' => 'main', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'source' => 'main', 'control' => 'chunk_size', 'value' => 2048],
]);

$deleteOnClose = static fn (): array => $plan107([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'delete-journal', 'delete_on_close' => true],
    ['op' => 'lock', 'value' => 'exclusive'],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 1024],
    ['op' => 'close', 'source' => 'temp'],
]);

return [
    'vfs temp directory sidecar lock current source next107 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-directory-sidecar-lock', $move()['dependencies'], true)),
    'vfs temp directory sidecar lock current source next107 final status closed' => static fn (TestRunner $t) => $t->same('closed', $move()['status']),
    'vfs temp directory sidecar lock current source next107 first open status' => static fn (TestRunner $t) => $t->same('temp-open', $move()['events'][0]['status']),
    'vfs temp directory sidecar lock current source next107 first handle id' => static fn (TestRunner $t) => $t->same('temp-wp-import-107-1', $move()['events'][0]['handle']),
    'vfs temp directory sidecar lock current source next107 first temp path' => static fn (TestRunner $t) => $t->same('/tmp/wp-a/sqlite-wp-import-107-000001.stmt-journal', $move()['events'][0]['path']),
    'vfs temp directory sidecar lock current source next107 first sidecar path' => static fn (TestRunner $t) => $t->same('/tmp/wp-a/.sqlite-wp-import-107-temp.stmt-journal.lock', $move()['events'][0]['sidecar_path']),
    'vfs temp directory sidecar lock current source next107 first sidecar key' => static fn (TestRunner $t) => $t->same('/tmp/wp-a|temp|.stmt-journal', $move()['events'][0]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 first generation' => static fn (TestRunner $t) => $t->same(1, $move()['events'][0]['directory_generation']),
    'vfs temp directory sidecar lock current source next107 first sidecar starts unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $move()['events'][0]['next']['sidecar_locks']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 reserved lock status' => static fn (TestRunner $t) => $t->same('ok', $move()['events'][1]['status']),
    'vfs temp directory sidecar lock current source next107 reserved lock key' => static fn (TestRunner $t) => $t->same('/tmp/wp-a|temp|.stmt-journal', $move()['events'][1]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 reserved lock persisted' => static fn (TestRunner $t) => $t->same('reserved', $move()['events'][1]['next']['sidecar_locks']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 chunk control status' => static fn (TestRunner $t) => $t->same('ok', $move()['events'][2]['status']),
    'vfs temp directory sidecar lock current source next107 chunk control value' => static fn (TestRunner $t) => $t->same(4096, $move()['events'][2]['value']),
    'vfs temp directory sidecar lock current source next107 chunk control changed' => static fn (TestRunner $t) => $t->same(true, $move()['events'][2]['changed']),
    'vfs temp directory sidecar lock current source next107 chunk control persisted old key' => static fn (TestRunner $t) => $t->same(['chunk_size' => 4096], $move()['events'][2]['next']['sidecar_controls']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 temp dir change status' => static fn (TestRunner $t) => $t->same('ok', $move()['events'][3]['status']),
    'vfs temp directory sidecar lock current source next107 temp dir old path' => static fn (TestRunner $t) => $t->same('/tmp/wp-a', $move()['events'][3]['old_directory']),
    'vfs temp directory sidecar lock current source next107 temp dir new path' => static fn (TestRunner $t) => $t->same('/tmp/wp-b', $move()['events'][3]['new_directory']),
    'vfs temp directory sidecar lock current source next107 temp dir generation bumped' => static fn (TestRunner $t) => $t->same(2, $move()['events'][3]['temp_generation']),
    'vfs temp directory sidecar lock current source next107 old handle keeps old generation' => static fn (TestRunner $t) => $t->same(1, $move()['events'][3]['next']['handles']['temp-wp-import-107-1']['directory_generation']),
    'vfs temp directory sidecar lock current source next107 second open status' => static fn (TestRunner $t) => $t->same('temp-open', $move()['events'][4]['status']),
    'vfs temp directory sidecar lock current source next107 second handle id' => static fn (TestRunner $t) => $t->same('temp-wp-import-107-2', $move()['events'][4]['handle']),
    'vfs temp directory sidecar lock current source next107 second path uses new temp dir' => static fn (TestRunner $t) => $t->same('/tmp/wp-b/sqlite-wp-import-107-000002.stmt-journal', $move()['events'][4]['path']),
    'vfs temp directory sidecar lock current source next107 second sidecar path uses new temp dir' => static fn (TestRunner $t) => $t->same('/tmp/wp-b/.sqlite-wp-import-107-temp.stmt-journal.lock', $move()['events'][4]['sidecar_path']),
    'vfs temp directory sidecar lock current source next107 second sidecar key isolated' => static fn (TestRunner $t) => $t->same('/tmp/wp-b|temp|.stmt-journal', $move()['events'][4]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 second generation is next source' => static fn (TestRunner $t) => $t->same(2, $move()['events'][4]['directory_generation']),
    'vfs temp directory sidecar lock current source next107 second open does not reuse old lock' => static fn (TestRunner $t) => $t->same(null, $move()['events'][4]['reused_sidecar_lock']),
    'vfs temp directory sidecar lock current source next107 both sidecars exist after second open' => static fn (TestRunner $t) => $t->same(2, count($move()['events'][4]['next']['sidecar_locks'])),
    'vfs temp directory sidecar lock current source next107 open count two after move' => static fn (TestRunner $t) => $t->same(2, count($move()['events'][4]['next']['handles'])),
    'vfs temp directory sidecar lock current source next107 old directory still has open handle' => static fn (TestRunner $t) => $t->same('/tmp/wp-a', $move()['events'][4]['next']['handles']['temp-wp-import-107-1']['directory']),
    'vfs temp directory sidecar lock current source next107 new directory has open handle' => static fn (TestRunner $t) => $t->same('/tmp/wp-b', $move()['events'][4]['next']['handles']['temp-wp-import-107-2']['directory']),
    'vfs temp directory sidecar lock current source next107 exclusive next lock persisted' => static fn (TestRunner $t) => $t->same('exclusive', $move()['events'][5]['next']['sidecar_locks']['/tmp/wp-b|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 old reserved lock still current' => static fn (TestRunner $t) => $t->same('reserved', $move()['events'][5]['next']['sidecar_locks']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 size hint status' => static fn (TestRunner $t) => $t->same('ok', $move()['events'][6]['status']),
    'vfs temp directory sidecar lock current source next107 size hint value' => static fn (TestRunner $t) => $t->same(8192, $move()['events'][6]['value']),
    'vfs temp directory sidecar lock current source next107 size hint persisted new key only' => static fn (TestRunner $t) => $t->same(['size_hint' => 8192], $move()['events'][6]['next']['sidecar_controls']['/tmp/wp-b|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 old chunk control remains old key' => static fn (TestRunner $t) => $t->same(['chunk_size' => 4096], $move()['events'][6]['next']['sidecar_controls']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 old handle filecontrol targets old key' => static fn (TestRunner $t) => $t->same('/tmp/wp-a|temp|.stmt-journal', $move()['events'][7]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 old handle lock timeout added' => static fn (TestRunner $t) => $t->same(50, $move()['events'][7]['next']['sidecar_controls']['/tmp/wp-a|temp|.stmt-journal']['lock_timeout']),
    'vfs temp directory sidecar lock current source next107 close old handle unlocks old sidecar' => static fn (TestRunner $t) => $t->same('unlocked', $move()['events'][8]['next']['sidecar_locks']['/tmp/wp-a|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 close old keeps new sidecar locked' => static fn (TestRunner $t) => $t->same('exclusive', $move()['events'][8]['next']['sidecar_locks']['/tmp/wp-b|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 close old leaves one open' => static fn (TestRunner $t) => $t->same(1, count($move()['events'][8]['next']['handles'])),
    'vfs temp directory sidecar lock current source next107 final close unlocks new sidecar' => static fn (TestRunner $t) => $t->same('unlocked', $move()['events'][9]['next']['sidecar_locks']['/tmp/wp-b|temp|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 final close leaves no open handles' => static fn (TestRunner $t) => $t->same(0, $move()['next']['open_count']),
    'vfs temp directory sidecar lock current source next107 final temp directory is new' => static fn (TestRunner $t) => $t->same('/tmp/wp-b', $move()['next']['temp_dir']),
    'vfs temp directory sidecar lock current source next107 final generation stays two' => static fn (TestRunner $t) => $t->same(2, $move()['next']['temp_generation']),
    'vfs temp directory sidecar lock current source next107 final sidecar lock count two' => static fn (TestRunner $t) => $t->same(2, $move()['next']['sidecar_lock_count']),
    'vfs temp directory sidecar lock current source next107 final sidecar control count two' => static fn (TestRunner $t) => $t->same(2, $move()['next']['sidecar_control_count']),
    'vfs temp directory sidecar lock current source next107 final locked sidecars zero' => static fn (TestRunner $t) => $t->same(0, $move()['next']['locked_sidecar_count']),

    'vfs temp directory sidecar lock current source next107 attached first sidecar key' => static fn (TestRunner $t) => $t->same('/tmp/wp-a|attached|.sorter', $attached()['events'][0]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 attached second sidecar key' => static fn (TestRunner $t) => $t->same('/tmp/wp-next|attached|.sorter', $attached()['events'][3]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 attached delete close clears controls' => static fn (TestRunner $t) => $t->same(0, $attached()['next']['sidecar_control_count']),
    'vfs temp directory sidecar lock current source next107 attached old shared lock preserved until next handle locks' => static fn (TestRunner $t) => $t->same('shared', $attached()['events'][3]['next']['sidecar_locks']['/tmp/wp-a|attached|.sorter']),
    'vfs temp directory sidecar lock current source next107 attached close unlocks new sidecar' => static fn (TestRunner $t) => $t->same('unlocked', $attached()['events'][5]['next']['sidecar_locks']['/tmp/wp-next|attached|.sorter']),

    'vfs temp directory sidecar lock current source next107 main and temp initial keys differ' => static fn (TestRunner $t) => $t->same(['/tmp/wp-a|main|.stmt-journal', '/tmp/wp-a|temp|.stmt-journal'], array_keys($sources()['events'][3]['next']['sidecar_locks'])),
    'vfs temp directory sidecar lock current source next107 main reopens in moved directory' => static fn (TestRunner $t) => $t->same('/tmp/wp-c|main|.stmt-journal', $sources()['events'][5]['sidecar_key']),
    'vfs temp directory sidecar lock current source next107 main filecontrol uses newest source handle' => static fn (TestRunner $t) => $t->same('temp-wp-import-107-3', $sources()['events'][6]['handle']),
    'vfs temp directory sidecar lock current source next107 main old lock remains reserved' => static fn (TestRunner $t) => $t->same('reserved', $sources()['events'][6]['next']['sidecar_locks']['/tmp/wp-a|main|.stmt-journal']),
    'vfs temp directory sidecar lock current source next107 main new control separate' => static fn (TestRunner $t) => $t->same(['chunk_size' => 2048], $sources()['events'][6]['next']['sidecar_controls']['/tmp/wp-c|main|.stmt-journal']),

    'vfs temp directory sidecar lock current source next107 delete on close status' => static fn (TestRunner $t) => $t->same('closed', $deleteOnClose()['status']),
    'vfs temp directory sidecar lock current source next107 delete on close removes controls' => static fn (TestRunner $t) => $t->same(0, $deleteOnClose()['next']['sidecar_control_count']),
    'vfs temp directory sidecar lock current source next107 delete on close unlocks sidecar' => static fn (TestRunner $t) => $t->same('unlocked', $deleteOnClose()['next']['current_sidecar_keys'] === [] ? 'unlocked' : $deleteOnClose()['current']['sidecar_locks']['/tmp/wp-a|temp|.delete-journal']),
    'vfs temp directory sidecar lock current source next107 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan::planTempDirectorySidecarLock([])),
    'vfs temp directory sidecar lock current source next107 rejects bad source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan107([['op' => 'open', 'source' => 'bogus']])),
    'vfs temp directory sidecar lock current source next107 rejects bad temp directory' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan107([['op' => 'temp_directory', 'path' => '']])),
    'vfs temp directory sidecar lock current source next107 rejects bad suffix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan107([['op' => 'open', 'suffix' => '../bad']])),
];
