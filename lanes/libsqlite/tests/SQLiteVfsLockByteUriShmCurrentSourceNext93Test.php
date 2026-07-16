<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$uri = 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix';
$sameUri = 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private';

$run = static fn (array $current, array $ops, string $filename = null): array => SQLiteVfsLockByteUriShmCurrentSourceNext::plan(
    $current,
    $ops,
    $filename ?? $uri,
);

$writer = static fn (): array => $run([], [
    'main shared wp-reader 12',
    'shm read0 shared wp-reader',
    'main reserved wp-import 21',
    'shm write exclusive wp-import',
    'main pending wp-import 21',
    'main exclusive wp-import 21',
    'release wp-reader',
    'main exclusive wp-import 21',
    'shm checkpoint exclusive wp-import',
]);

$reopen = static function () use ($run, $writer, $sameUri): array {
    return $run($writer()['next'], [
        'main shared wp-report 2',
        'shm read1 shared wp-report',
    ], $sameUri);
};

$readonly = static fn (): array => $run([], [
    'main shared wp-reader 4',
    'shm read0 shared wp-reader',
    'main reserved wp-import 4',
    'shm checkpoint exclusive wp-import',
], 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&cache=shared');

$immutable = static fn (): array => $run([], [
    'main shared wp-reader 4',
    'shm read0 shared wp-reader',
], 'file:/srv/www/wp-content/database/archive.sqlite?mode=rw&immutable=1');

$nolock = static fn (): array => $run([], [
    'main shared wp-reader 4',
    'shm read0 shared wp-reader',
], 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&nolock=1');

$memory = static fn (): array => $run([], [
    'main shared wp-reader 4',
    'shm read0 shared wp-reader',
], 'file::memory:?mode=memory&cache=shared');

$tests = [];

$tests['vfs lock byte uri shm current source next93 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-byte-uri-shm-current-source-next93', $writer()['dependencies'], true));
$tests['vfs lock byte uri shm current source next93 event count'] = static fn (TestRunner $t) => $t->same(9, count($writer()['events']));
$tests['vfs lock byte uri shm current source next93 decoded source key'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $writer()['next']['selected_source']);
$tests['vfs lock byte uri shm current source next93 decoded shm key'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite-shm', $writer()['next']['selected']['shm_key']);
$tests['vfs lock byte uri shm current source next93 uri authority captured'] = static fn (TestRunner $t) => $t->same('localhost', $writer()['next']['selected']['uri']['authority']);
$tests['vfs lock byte uri shm current source next93 uri cache captured'] = static fn (TestRunner $t) => $t->same('shared', $writer()['next']['selected']['uri']['cache']);
$tests['vfs lock byte uri shm current source next93 reader shared planned'] = static fn (TestRunner $t) => $t->same('planned', $writer()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 reader shared offset'] = static fn (TestRunner $t) => $t->same(1073741838, $writer()['events'][0]['plan']['acquire'][0]['offset']);
$tests['vfs lock byte uri shm current source next93 read shm acquired'] = static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 reserved planned with reader'] = static fn (TestRunner $t) => $t->same('planned', $writer()['events'][2]['status']);
$tests['vfs lock byte uri shm current source next93 reserved holder stored'] = static fn (TestRunner $t) => $t->same('reserved', $writer()['events'][2]['next']['selected']['main_holders']['wp-import']);
$tests['vfs lock byte uri shm current source next93 writer shm exclusive acquired'] = static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][3]['status']);
$tests['vfs lock byte uri shm current source next93 pending planned'] = static fn (TestRunner $t) => $t->same('planned', $writer()['events'][4]['status']);
$tests['vfs lock byte uri shm current source next93 exclusive blocked by reader'] = static fn (TestRunner $t) => $t->same('blocked', $writer()['events'][5]['status']);
$tests['vfs lock byte uri shm current source next93 exclusive blocker names reader'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $writer()['events'][5]['blocking']);
$tests['vfs lock byte uri shm current source next93 exclusive reason'] = static fn (TestRunner $t) => $t->same('main_lock_conflict', $writer()['events'][5]['reason']);
$tests['vfs lock byte uri shm current source next93 release removes reader main'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('wp-reader', $writer()['events'][6]['next']['selected']['main_holders']));
$tests['vfs lock byte uri shm current source next93 release removes reader shm'] = static fn (TestRunner $t) => $t->same([], $writer()['events'][6]['next']['selected']['shm_locks']['read0']);
$tests['vfs lock byte uri shm current source next93 exclusive succeeds after release'] = static fn (TestRunner $t) => $t->same('planned', $writer()['events'][7]['status']);
$tests['vfs lock byte uri shm current source next93 exclusive shared range length'] = static fn (TestRunner $t) => $t->same(510, $writer()['events'][7]['plan']['acquire'][0]['length']);
$tests['vfs lock byte uri shm current source next93 checkpoint acquired'] = static fn (TestRunner $t) => $t->same('acquired', $writer()['events'][8]['status']);
$tests['vfs lock byte uri shm current source next93 final main holder count'] = static fn (TestRunner $t) => $t->same(1, $writer()['next']['main_holder_count']);
$tests['vfs lock byte uri shm current source next93 final shm lock count'] = static fn (TestRunner $t) => $t->same(2, $writer()['next']['shm_lock_count']);
$tests['vfs lock byte uri shm current source next93 final import exclusive'] = static fn (TestRunner $t) => $t->same('exclusive', $writer()['next']['selected']['main_holders']['wp-import']);
$tests['vfs lock byte uri shm current source next93 final generation'] = static fn (TestRunner $t) => $t->same(9, $writer()['next']['selected']['generation']);

$tests['vfs lock byte uri shm current source next93 reopen same decoded source count'] = static fn (TestRunner $t) => $t->same(1, $reopen()['next']['source_count']);
$tests['vfs lock byte uri shm current source next93 reopen private cache still same source'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $reopen()['events'][0]['source_key']);
$tests['vfs lock byte uri shm current source next93 reopen shared reader coexists'] = static fn (TestRunner $t) => $t->same('planned', $reopen()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 reopen read1 shm acquired'] = static fn (TestRunner $t) => $t->same('acquired', $reopen()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 reopen preserved import exclusive'] = static fn (TestRunner $t) => $t->same('exclusive', $reopen()['next']['selected']['main_holders']['wp-import']);
$tests['vfs lock byte uri shm current source next93 reopen report reader'] = static fn (TestRunner $t) => $t->same('shared', $reopen()['next']['selected']['main_holders']['wp-report']);
$tests['vfs lock byte uri shm current source next93 reopen generation increments'] = static fn (TestRunner $t) => $t->same(11, $reopen()['next']['selected']['generation']);

$tests['vfs lock byte uri shm current source next93 readonly shared main ok'] = static fn (TestRunner $t) => $t->same('planned', $readonly()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 readonly shared shm ok'] = static fn (TestRunner $t) => $t->same('acquired', $readonly()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 readonly reserved blocked'] = static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][2]['status']);
$tests['vfs lock byte uri shm current source next93 readonly reserved reason'] = static fn (TestRunner $t) => $t->same('readonly_uri_disables_writer_lock', $readonly()['events'][2]['reason']);
$tests['vfs lock byte uri shm current source next93 readonly exclusive shm blocked'] = static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][3]['status']);
$tests['vfs lock byte uri shm current source next93 readonly exclusive shm reason'] = static fn (TestRunner $t) => $t->same('readonly_uri_disables_exclusive_shm_lock', $readonly()['events'][3]['reason']);

$tests['vfs lock byte uri shm current source next93 immutable main blocked'] = static fn (TestRunner $t) => $t->same('blocked', $immutable()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 immutable main reason'] = static fn (TestRunner $t) => $t->same('immutable_uri_disables_lock_bytes', $immutable()['events'][0]['reason']);
$tests['vfs lock byte uri shm current source next93 immutable shm blocked'] = static fn (TestRunner $t) => $t->same('blocked', $immutable()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 immutable shm reason'] = static fn (TestRunner $t) => $t->same('immutable_uri_disables_shm_locking', $immutable()['events'][1]['reason']);

$tests['vfs lock byte uri shm current source next93 nolock main blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 nolock main reason'] = static fn (TestRunner $t) => $t->same('nolock_uri_disables_lock_bytes', $nolock()['events'][0]['reason']);
$tests['vfs lock byte uri shm current source next93 nolock shm blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 nolock shm reason'] = static fn (TestRunner $t) => $t->same('nolock_uri_disables_shm_locking', $nolock()['events'][1]['reason']);

$tests['vfs lock byte uri shm current source next93 memory source private'] = static fn (TestRunner $t) => $t->same(false, $memory()['next']['selected']['persistent']);
$tests['vfs lock byte uri shm current source next93 memory shm key private'] = static fn (TestRunner $t) => $t->same(true, str_ends_with($memory()['next']['selected']['shm_key'], ':private-shm'));
$tests['vfs lock byte uri shm current source next93 memory main blocked'] = static fn (TestRunner $t) => $t->same('blocked', $memory()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next93 memory main reason'] = static fn (TestRunner $t) => $t->same('memory_uri_has_private_lock_bytes', $memory()['events'][0]['reason']);
$tests['vfs lock byte uri shm current source next93 memory shm blocked'] = static fn (TestRunner $t) => $t->same('blocked', $memory()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 memory shm reason'] = static fn (TestRunner $t) => $t->same('memory_uri_has_private_shm', $memory()['events'][1]['reason']);

$tests['vfs lock byte uri shm current source next93 shm shared coexist'] = static fn (TestRunner $t) => $t->same('acquired', $run([], ['shm read2 shared a', 'shm read2 shared b'])['events'][1]['status']);
$tests['vfs lock byte uri shm current source next93 shm exclusive blocked by shared'] = static fn (TestRunner $t) => $t->same(['a:shared'], $run([], ['shm read2 shared a', 'shm read2 exclusive b'])['events'][1]['blocking']);
$tests['vfs lock byte uri shm current source next93 second reserved blocked'] = static fn (TestRunner $t) => $t->same(['a:reserved'], $run([], ['main reserved a 1', 'main reserved b 2'])['events'][1]['blocking']);
$tests['vfs lock byte uri shm current source next93 pending not blocked by shared'] = static fn (TestRunner $t) => $t->same([], $run([], ['main shared a 1', 'main pending b 2'])['events'][1]['blocking']);
$tests['vfs lock byte uri shm current source next93 unlock shm idempotent'] = static fn (TestRunner $t) => $t->same('released', $run([], ['shm read3 unlock missing'])['events'][0]['status']);

$tests['vfs lock byte uri shm current source next93 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::plan([], [], $uri));
$tests['vfs lock byte uri shm current source next93 rejects unsupported op'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['checkpoint wp']));
$tests['vfs lock byte uri shm current source next93 rejects bad uri authority'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['main shared wp 1'], 'file://example.com/srv/db.sqlite?mode=rw'));
$tests['vfs lock byte uri shm current source next93 rejects bad percent'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['main shared wp 1'], 'file:/srv/db%2.sqlite?mode=rw'));
$tests['vfs lock byte uri shm current source next93 rejects bad slot'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'main', 'level' => 'shared', 'connection' => 'wp', 'shared_slot' => 510]]));
$tests['vfs lock byte uri shm current source next93 rejects bad shm lock'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], ['shm read9 shared wp']));
$tests['vfs lock byte uri shm current source next93 rejects empty connection'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([], [['op' => 'main', 'level' => 'shared', 'connection' => '']]));

return $tests;
