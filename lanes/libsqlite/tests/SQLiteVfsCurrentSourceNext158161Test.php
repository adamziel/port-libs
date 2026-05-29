<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext158161Plan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 12,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs154157-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'generation' => 12,
            'size' => 65536,
        ],
        'backup' => [
            'handle' => 'vfs154157-2',
            'path' => '/srv/www/wp-content/database/backup.sqlite',
            'generation' => 4,
            'size' => 32768,
            'readonly' => true,
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext158161Plan::run([
            'mmap(32768)',
            'fetch(4096,8192)',
            ['op' => 'xShmMap', 'page' => 0, 'size' => 32768, 'extend' => true],
            ['op' => 'xShmLock', 'offset' => 2, 'count' => 1, 'mode' => 'exclusive'],
            'unfetch()',
            ['op' => 'xShmUnmap', 'delete' => false],
            'source(backup)',
            ['op' => 'xShmMap', 'page' => 1, 'size' => 32768, 'extend' => true],
            ['op' => 'xShmLock', 'offset' => 3, 'count' => 1, 'mode' => 'exclusive'],
            ['op' => 'xShmLock', 'offset' => 3, 'count' => 1, 'mode' => 'shared'],
            ['op' => 'open', 'source' => 'wal', 'path' => '/srv/www/wp-content/database/wp.sqlite-wal', 'size' => 16384],
            ['op' => 'mmap', 'limit' => 8192],
            ['op' => 'fetch', 'offset' => 4096, 'amount' => 8192],
            'close(wal)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next158-161 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-mmap-shm-next158-161', $plan()['dependencies'], true)),
    'vfs current source next158-161 current source count' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next158-161 mmap maps selected source' => static fn (TestRunner $t) => $t->same(32768, $plan()['events'][0]['mapped']),
    'vfs current source next158-161 fetch records selected range' => static fn (TestRunner $t) => $t->same(['offset' => 4096, 'amount' => 8192], $plan()['events'][1]['next']['sources']['main']['fetches'][0]),
    'vfs current source next158-161 shm map extends main' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][2]['extended']),
    'vfs current source next158-161 shm lock records exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $plan()['events'][3]['mode']),
    'vfs current source next158-161 unfetch releases range' => static fn (TestRunner $t) => $t->same(0, $plan()['events'][4]['fetch_count']),
    'vfs current source next158-161 shm unmap releases page' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][5]['released_pages']),
    'vfs current source next158-161 selects readonly backup' => static fn (TestRunner $t) => $t->same('backup', $plan()['events'][6]['source']),
    'vfs current source next158-161 readonly shm extend blocked' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][7]['status']),
    'vfs current source next158-161 readonly exclusive shm lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][8]['status']),
    'vfs current source next158-161 readonly shared shm lock allowed' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][9]['status']),
    'vfs current source next158-161 opens wal under owner generation' => static fn (TestRunner $t) => $t->same(13, $plan()['events'][10]['generation']),
    'vfs current source next158-161 blocks fetch outside mmap window' => static fn (TestRunner $t) => $t->same('fetch range exceeds current-source mmap window', $plan()['events'][12]['reason']),
    'vfs current source next158-161 closes wal and releases locks' => static fn (TestRunner $t) => $t->same(0, $plan()['events'][13]['released_shm_locks']),
    'vfs current source next158-161 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next158-161 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next158-161 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next158-161 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext158161Plan::run([])),
    'vfs current source next158-161 rejects zero fetch amount' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext158161Plan::run([['op' => 'fetch', 'offset' => 0, 'amount' => 0]], ['current' => $current])),
    'vfs current source next158-161 rejects unsupported shm mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext158161Plan::run([['op' => 'shm_lock', 'offset' => 0, 'mode' => 'bad']], ['current' => $current])),
];
