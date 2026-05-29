<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run112 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext112($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20options.sqlite?mode=rw&cache=shared',
]);

$wordpress = static function () use ($run112): array {
    static $result = null;
    if ($result === null) {
        $result = $run112([
            'open(file:/srv/www/wp-content/database/wp%20options.sqlite-shm?mode=rw&cache=shared)',
            'shm read0 shared wp-reader',
            'file_control(data_version)',
            'open(file:/srv/www/wp-content/database/wp%20options.sqlite?mode=rw&cache=shared)',
            'file_control(persist_wal, on)',
            'lock shared wp-reader 13 on main',
            'lock reserved wp-import 17 on main',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
            'source(main)',
            'file_control(reserve_bytes, 32)',
            'open(file:/srv/www/wp-content/database/wp%20options.sqlite-wal?mode=rw&cache=shared)',
            'file_control(data_version)',
            'source(main)',
            'lock exclusive wp-import 17',
            'yield wp-reader',
            'lock exclusive wp-import 17',
            'file_control(chunk_size, 8192)',
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $run112([
    'open(file:/srv/www/wp-content/database/archive.sqlite?mode=ro&cache=private)',
    'lock shared wp-report 4',
    'file_control(persist_wal, on)',
    'file_control(mmap_size, 65536)',
    'file_control(data_version)',
]);

$seeded = static fn (): array => $run112([
    'open(file:/srv/www/wp-content/database/seeded.sqlite?mode=rw)',
    'file_control(data_version)',
    'lock reserved wp-seed 2',
    'file_control(powersafe_overwrite, off)',
    'open(file:/srv/www/wp-content/database/seeded.sqlite-shm?mode=rw)',
    'file_control(data_version)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/seeded.sqlite' => ['persist_wal' => true, 'data_version' => 8],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/seeded.sqlite' => 8,
        ],
    ],
]);

$nolock = static fn (): array => $run112([
    'open(file:/srv/www/wp-content/database/nolock.sqlite?mode=rw&nolock=1)',
    'lock reserved wp-import 5',
    'file_control(reserve_bytes, 64)',
    'file_control(data_version)',
]);

$tests['vfs shm lockbyte filecontrol current source next112 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-lockbyte-filecontrol-current-source-next112', $wordpress()['dependencies'], true));
$tests['vfs shm lockbyte filecontrol current source next112 data version dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-file-control-data-version', $wordpress()['dependencies'], true));
$tests['vfs shm lockbyte filecontrol current source next112 event count'] = static fn (TestRunner $t) => $t->same(19, count($wordpress()['events']));
$tests['vfs shm lockbyte filecontrol current source next112 first open shm'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][0]['source']);
$tests['vfs shm lockbyte filecontrol current source next112 owner decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp options.sqlite', $wordpress()['events'][0]['owner']);
$tests['vfs shm lockbyte filecontrol current source next112 first generation one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][0]['next']['handles']['vfs97-1']['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 shm read acquired'] = static fn (TestRunner $t) => $t->same('acquired', $wordpress()['events'][1]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 shm read stored'] = static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $wordpress()['events'][1]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['shm_locks']['read0']);
$tests['vfs shm lockbyte filecontrol current source next112 shm data version initial ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][2]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 shm data version one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][2]['value']);
$tests['vfs shm lockbyte filecontrol current source next112 shm data version fresh'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][2]['stale_current_source']);
$tests['vfs shm lockbyte filecontrol current source next112 main open generation one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][3]['next']['handles']['vfs97-2']['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 write before byte lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $wordpress()['events'][4]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 write before byte lock reason'] = static fn (TestRunner $t) => $t->same('requires_reserved_pending_or_exclusive_byte_lock', $wordpress()['events'][4]['reason']);
$tests['vfs shm lockbyte filecontrol current source next112 shared byte lock planned'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][5]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 shared slot offset'] = static fn (TestRunner $t) => $t->same(1073741839, $wordpress()['events'][5]['plan']['acquire'][0]['offset']);
$tests['vfs shm lockbyte filecontrol current source next112 reserved writer planned'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][6]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 reserved holder stored'] = static fn (TestRunner $t) => $t->same('reserved', $wordpress()['events'][6]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['holders']['wp-import']);
$tests['vfs shm lockbyte filecontrol current source next112 persist wal after reserved ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][7]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 persist wal stored'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][7]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['controls']['persist_wal']);
$tests['vfs shm lockbyte filecontrol current source next112 persist wal generation bumps two'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][7]['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 persist wal data version stored'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][7]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['controls']['data_version']);
$tests['vfs shm lockbyte filecontrol current source next112 persist wal marks shm stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $wordpress()['events'][7]['stale_handles']);
$tests['vfs shm lockbyte filecontrol current source next112 source shm ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][8]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 stale shm sees generation two'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][9]['value']);
$tests['vfs shm lockbyte filecontrol current source next112 stale shm opened generation one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][9]['opened_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 stale shm flag true'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][9]['stale_current_source']);
$tests['vfs shm lockbyte filecontrol current source next112 reserve bytes ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][11]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 reserve bytes stored'] = static fn (TestRunner $t) => $t->same(32, $wordpress()['events'][11]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['controls']['reserve_bytes']);
$tests['vfs shm lockbyte filecontrol current source next112 reserve bytes generation three'] = static fn (TestRunner $t) => $t->same(3, $wordpress()['events'][11]['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 reserve marks shm stale only'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $wordpress()['events'][11]['stale_handles']);
$tests['vfs shm lockbyte filecontrol current source next112 wal opens at generation three'] = static fn (TestRunner $t) => $t->same(3, $wordpress()['events'][12]['next']['handles']['vfs97-3']['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 wal data version fresh'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][13]['stale_current_source']);
$tests['vfs shm lockbyte filecontrol current source next112 exclusive blocked by reader'] = static fn (TestRunner $t) => $t->same('blocked', $wordpress()['events'][15]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 exclusive blocker reader'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $wordpress()['events'][15]['blocking']);
$tests['vfs shm lockbyte filecontrol current source next112 yield releases reader'] = static fn (TestRunner $t) => $t->same('released', $wordpress()['events'][16]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 yield clears shm reader'] = static fn (TestRunner $t) => $t->same([], $wordpress()['events'][16]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['shm_locks']['read0']);
$tests['vfs shm lockbyte filecontrol current source next112 exclusive succeeds after yield'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][17]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 chunk size ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][18]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 chunk size stored'] = static fn (TestRunner $t) => $t->same(8192, $wordpress()['events'][18]['next']['owners']['/srv/www/wp-content/database/wp options.sqlite']['controls']['chunk_size']);
$tests['vfs shm lockbyte filecontrol current source next112 final generation count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['persistent_generation_count']);
$tests['vfs shm lockbyte filecontrol current source next112 final control count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['persistent_control_count']);
$tests['vfs shm lockbyte filecontrol current source next112 final owner count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['owner_count']);

$tests['vfs shm lockbyte filecontrol current source next112 readonly write ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 readonly reason'] = static fn (TestRunner $t) => $t->same('readonly_handle', $readonly()['events'][2]['reason']);
$tests['vfs shm lockbyte filecontrol current source next112 readonly mmap ok'] = static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 readonly mmap stored'] = static fn (TestRunner $t) => $t->same(65536, $readonly()['events'][3]['next']['owners']['/srv/www/wp-content/database/archive.sqlite']['controls']['mmap_size']);
$tests['vfs shm lockbyte filecontrol current source next112 readonly data version unchanged'] = static fn (TestRunner $t) => $t->same(1, $readonly()['events'][4]['value']);

$tests['vfs shm lockbyte filecontrol current source next112 seeded opens at generation eight'] = static fn (TestRunner $t) => $t->same(8, $seeded()['events'][0]['next']['handles']['vfs97-1']['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 seeded data version fresh'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][1]['stale_current_source']);
$tests['vfs shm lockbyte filecontrol current source next112 seeded powersafe false stored'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][3]['next']['owners']['/srv/www/wp-content/database/seeded.sqlite']['controls']['powersafe_overwrite']);
$tests['vfs shm lockbyte filecontrol current source next112 seeded generation nine'] = static fn (TestRunner $t) => $t->same(9, $seeded()['events'][3]['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 seeded shm opens at generation nine'] = static fn (TestRunner $t) => $t->same(9, $seeded()['events'][4]['next']['handles']['vfs97-2']['source_generation']);
$tests['vfs shm lockbyte filecontrol current source next112 seeded shm data version fresh'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][5]['stale_current_source']);

$tests['vfs shm lockbyte filecontrol current source next112 nolock byte lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 nolock write control blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']);
$tests['vfs shm lockbyte filecontrol current source next112 nolock data version one'] = static fn (TestRunner $t) => $t->same(1, $nolock()['events'][3]['value']);

$tests['vfs shm lockbyte filecontrol current source next112 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext112([]));
$tests['vfs shm lockbyte filecontrol current source next112 rejects bad file control value'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run112(['open(main)', 'lock reserved wp 1', ['op' => 'filecontrol', 'control' => 'reserve_bytes', 'value' => -1]]));
$tests['vfs shm lockbyte filecontrol current source next112 rejects bad file control name'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run112(['open(main)', ['op' => 'filecontrol', 'control' => '']]));

return $tests;
