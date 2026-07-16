<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistence;

$tests = [];

$makeRoot = static function (string $name): string {
    $root = sys_get_temp_dir() . '/port-libsqlite-vfs-filecontrol-persist75-' . $name . '-' . bin2hex(random_bytes(4));
    $database = $root . '/srv/www/wp-content/database/.ht.sqlite';
    if (!is_dir(dirname($database)) && !mkdir(dirname($database), 0777, true) && !is_dir(dirname($database))) {
        throw new RuntimeException('failed to create VFS file-control persistence test directory');
    }
    file_put_contents($database, str_repeat('wp option page', 64));

    return $root;
};

$durableCases = [
    'persist wal on' => ['persist_wal', true, true],
    'persist wal off' => ['persist_wal', false, false],
    'chunk size four k' => ['chunk_size', 4096, 4096],
    'chunk size eight k' => ['chunk_size', 8192, 8192],
    'mmap size disabled' => ['mmap_size', 0, 0],
    'mmap size small' => ['mmap_size', 65536, 65536],
    'mmap size large' => ['mmap_size', 1048576, 1048576],
    'powersafe overwrite on' => ['powersafe_overwrite', true, true],
    'powersafe overwrite off' => ['powersafe_overwrite', false, false],
    'size limit zero' => ['size_limit', 0, 0],
    'size limit small cap' => ['size_limit', 32768, 32768],
    'size limit import cap' => ['size_limit', 1048576, 1048576],
    'reserve bytes zero' => ['reserve_bytes', 0, 0],
    'reserve bytes plugin payload' => ['reserve_bytes', 32, 32],
    'reserve bytes max' => ['reserve_bytes', 255, 255],
];

foreach ($durableCases as $name => [$op, $value, $expected]) {
    $tests['vfs filecontrol persistence persistent file-control apply persists durable ' . $name] = static function (TestRunner $t) use ($makeRoot, $op, $value, $expected): void {
        $root = $makeRoot(str_replace(' ', '-', $op));
        $persistence = new SQLiteVfsFileControlPersistence($root);
        $result = $persistence->persistentFileControlApply(
            'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
            true,
            true,
            [[$op => $value]][0],
            'wp-admin',
            4096,
            ['safe_append', 'powersafe_overwrite'],
            'full',
            false,
            4096,
            0
        );

        $t->same('persisted', $result['status']);
        $t->same('acquired', $result['lock']['status']);
        $t->same('released', $result['release']['status']);
        $t->same($expected, $result['persisted'][$op]);
        $t->same($expected, $result['next']['controls'][$op]);
        $t->same([], $result['release']['holders']);
        $t->same(true, is_file($result['sidecar']));
        $t->same(true, in_array('vfs-file-control-persistence-persistent-file-control-apply', $result['dependencies'], true));
    };
}

$ephemeralCases = [
    'name hint' => ['name_hint', 'wp-options-import', 'wp-options-import'],
    'lock timeout' => ['lock_timeout', 2500, 2500],
    'write hint' => ['write_hint', 65536, 65536],
    'overwrite page one' => ['overwrite', 1, [1]],
    'overwrite page five' => ['overwrite', 5, [5]],
    'begin atomic write' => ['begin_atomic_write', null, true],
    'sync full' => ['sync', 'full', ['full']],
    'commit phase two' => ['commit_phasetwo', null, 1],
    'has moved other path' => ['has_moved', '/srv/www/wp-content/database/moved.sqlite', true],
];

foreach ($ephemeralCases as $name => [$op, $value, $expected]) {
    $tests['vfs filecontrol persistence persistent file-control apply drops ephemeral ' . $name] = static function (TestRunner $t) use ($makeRoot, $op, $value, $expected): void {
        $root = $makeRoot(str_replace(' ', '-', $op));
        $persistence = new SQLiteVfsFileControlPersistence($root);
        $result = $persistence->persistentFileControlApply(
            'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
            true,
            true,
            [['op' => $op, 'value' => $value]],
            'wp-cli',
            4096,
            ['powersafe_overwrite'],
            'normal',
            false,
            null,
            0
        );

        $applied = $result['file_control']['results'][0];
        $t->same($expected, $applied['value']);
        $t->same(false, array_key_exists($op, $result['persisted']));
        $t->same(false, array_key_exists($op, $persistence->readPersisted('/srv/www/wp-content/database/.ht.sqlite')));
        $t->same(null, $result['next']['controls']['name_hint']);
        $t->same(0, $result['next']['controls']['lock_timeout']);
        $t->same(null, $result['next']['controls']['write_hint_bytes']);
        $t->same([], $result['next']['controls']['overwrite_pages']);
        $t->same(false, $result['next']['controls']['atomic_write_active']);
        $t->same(null, $result['next']['controls']['last_sync_flags']);
    };
}

$sequenceCases = [
    'chunk then size hint keeps chunk for next open' => [
        [['op' => 'chunk_size', 'value' => 8192], ['op' => 'size_hint', 'value' => 20000]],
        ['chunk_size' => 8192],
    ],
    'limit blocks oversized next hint after reopen' => [
        [['op' => 'size_limit', 'value' => 4096], ['op' => 'size_hint', 'value' => 8192]],
        ['size_limit' => 4096],
    ],
    'reserve and mmap survive together' => [
        [['op' => 'reserve_bytes', 'value' => 24], ['op' => 'mmap_size', 'value' => 131072]],
        ['reserve_bytes' => 24, 'mmap_size' => 131072],
    ],
    'persist wal and psow survive together' => [
        [['op' => 'persist_wal', 'value' => true], ['op' => 'powersafe_overwrite', 'value' => false]],
        ['persist_wal' => true, 'powersafe_overwrite' => false],
    ],
    'ephemeral mixed with durable persists only durable' => [
        [['op' => 'name_hint', 'value' => 'wp repair'], ['op' => 'chunk_size', 'value' => 4096], ['op' => 'lock_timeout', 'value' => 100]],
        ['chunk_size' => 4096],
    ],
];

foreach ($sequenceCases as $name => [$controls, $expectedPersisted]) {
    $tests['vfs filecontrol persistence persistent file-control apply sequence ' . $name] = static function (TestRunner $t) use ($makeRoot, $controls, $expectedPersisted): void {
        $root = $makeRoot(substr(hash('sha1', json_encode($controls)), 0, 8));
        $persistence = new SQLiteVfsFileControlPersistence($root);
        $result = $persistence->persistentFileControlApply(
            'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
            true,
            true,
            $controls,
            'wp-import',
            4096,
            ['safe_append', 'powersafe_overwrite'],
            'full',
            false,
            null,
            0
        );

        foreach ($expectedPersisted as $key => $expected) {
            $t->same($expected, $result['persisted'][$key]);
            $t->same($expected, $result['next']['controls'][$key]);
        }
        $t->same(true, count($result['persisted']) >= count($expectedPersisted));
        $t->same('reserved', $result['lock']['held']);
        $t->same([], $result['release']['holders']);
    };
}

$tests['vfs filecontrol persistence persistent file-control apply rehydrates second current from sidecar'] = static function (TestRunner $t) use ($makeRoot): void {
    $root = $makeRoot('rehydrates');
    $persistence = new SQLiteVfsFileControlPersistence($root);
    $first = $persistence->persistentFileControlApply(
        'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
        true,
        true,
        ['chunk_size' => 8192, 'reserve_bytes' => 16, 'mmap_size' => 65536],
        'wp-admin'
    );
    $second = $persistence->persistentFileControlApply(
        'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
        true,
        true,
        ['persist_wal' => true],
        'wp-cron'
    );

    $t->same(8192, $first['next']['controls']['chunk_size']);
    $t->same(8192, $second['current']['controls']['chunk_size']);
    $t->same(16, $second['current']['controls']['reserve_bytes']);
    $t->same(65536, $second['current']['controls']['mmap_size']);
    $t->same(true, $second['next']['controls']['persist_wal']);
};

$tests['vfs filecontrol persistence persistent file-control apply readonly open can rehydrate but ignores writes'] = static function (TestRunner $t) use ($makeRoot): void {
    $root = $makeRoot('readonly');
    $persistence = new SQLiteVfsFileControlPersistence($root);
    $persistence->writePersisted('/srv/www/wp-content/database/.ht.sqlite', ['mmap_size' => 32768, 'reserve_bytes' => 20]);
    $result = $persistence->persistentFileControlApply(
        'file:/srv/www/wp-content/database/.ht.sqlite?mode=ro&immutable=1',
        true,
        false,
        ['reserve_bytes' => 40, 'mmap_size' => 65536],
        'wp-reader'
    );

    $t->same('persisted', $result['status']);
    $t->same(20, $result['current']['controls']['reserve_bytes']);
    $t->same(32768, $result['current']['controls']['mmap_size']);
    $t->same('ignored', $result['file_control']['results'][0]['status']);
    $t->same('ignored', $result['file_control']['results'][1]['status']);
    $t->same(20, $result['next']['controls']['reserve_bytes']);
    $t->same(0, $result['next']['controls']['mmap_size']);
};

$tests['vfs filecontrol persistence persistent file-control apply nolock open blocks lock before persistence'] = static function (TestRunner $t) use ($makeRoot): void {
    $root = $makeRoot('nolock');
    $persistence = new SQLiteVfsFileControlPersistence($root);
    $result = $persistence->persistentFileControlApply(
        'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&nolock=1',
        true,
        true,
        ['chunk_size' => 8192],
        'wp-repair'
    );

    $t->same('blocked', $result['status']);
    $t->same('blocked', $result['lock']['status']);
    $t->same('nolock VFS disables POSIX byte-range locking', $result['lock']['reason']);
    $t->same(0, $result['file_control']['applied']);
    $t->same([], $result['persisted']);
    $t->same(false, is_file($result['sidecar']));
};

$invalidCases = [
    'empty root' => static fn (): SQLiteVfsFileControlPersistence => new SQLiteVfsFileControlPersistence(''),
    'empty sidecar path' => static fn (): string => (new SQLiteVfsFileControlPersistence(sys_get_temp_dir()))->sidecarPath(''),
    'nul sidecar path' => static fn (): string => (new SQLiteVfsFileControlPersistence(sys_get_temp_dir()))->sidecarPath("/tmp/bad\0name.sqlite"),
    'empty controls' => static fn (): array => (new SQLiteVfsFileControlPersistence(sys_get_temp_dir()))->persistentFileControlApply('file:/tmp/site.sqlite?mode=rwc', false, true, []),
];

foreach ($invalidCases as $name => $callback) {
    $tests['vfs filecontrol persistence persistent file-control apply rejects ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

$tests['vfs filecontrol persistence persistent file-control apply rejects unopenable current handle'] = static function (TestRunner $t): void {
    $persistence = new SQLiteVfsFileControlPersistence(sys_get_temp_dir());

    $t->throws(RuntimeException::class, static fn () => $persistence->persistentFileControlApply('file:/tmp/missing.sqlite?mode=rw', false, false, ['chunk_size' => 4096]));
};

$roundTripCases = [
    'same path sidecar is stable' => ['/srv/www/wp-content/database/.ht.sqlite', '/srv/www/wp-content/database/.ht.sqlite', true],
    'different path sidecar differs' => ['/srv/www/wp-content/database/.ht.sqlite', '/srv/www/wp-content/database/other.sqlite', false],
    'leading slash path has sidecar json suffix' => ['/srv/www/wp-content/database/.ht.sqlite', '/srv/www/wp-content/database/.ht.sqlite', true],
    'subdirectory path has sidecar json suffix' => ['/srv/www/wp-content/database/site/blog.sqlite', '/srv/www/wp-content/database/site/blog.sqlite', true],
    'journal path gets independent sidecar' => ['/srv/www/wp-content/database/.ht.sqlite-journal', '/srv/www/wp-content/database/.ht.sqlite', false],
];

foreach ($roundTripCases as $name => [$left, $right, $same]) {
    $tests['vfs filecontrol persistence persistent file-control apply sidecar ' . $name] = static function (TestRunner $t) use ($left, $right, $same): void {
        $persistence = new SQLiteVfsFileControlPersistence(sys_get_temp_dir());
        $leftSidecar = $persistence->sidecarPath($left);
        $rightSidecar = $persistence->sidecarPath($right);

        $t->same($same, $leftSidecar === $rightSidecar);
        $t->same(true, str_ends_with($leftSidecar, '.json'));
        $t->same(true, str_contains($leftSidecar, '.sqlite-vfs-file-control'));
    };
}

return $tests;
