<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 21,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs158161-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'sector_size' => 4096,
            'device' => ['safe_append'],
        ],
        'temp' => [
            'handle' => 'vfs158161-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-journal',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'temporary' => true,
            'sector_size' => 4096,
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            'access(/srv/www/wp-content/database/wp.sqlite-wal,exists)',
            'access(/srv/www/wp-content/other.sqlite,exists)',
            ['op' => 'xFullPathname', 'path' => 'wp.sqlite'],
            ['op' => 'xSectorSize', 'bytes' => 8192],
            ['op' => 'xDeviceCharacteristics', 'flags' => ['safe_append', 'powersafe_overwrite']],
            ['op' => 'xRandomness', 'bytes' => 24, 'seed' => 'nonce162'],
            'sleep(2500)',
            ['op' => 'xDelete', 'path' => '/srv/www/wp-content/database/wp.sqlite-wal', 'sync_dir' => false],
            ['op' => 'xDelete', 'path' => '/srv/www/wp-content/database/wp.sqlite-wal', 'sync_dir' => true],
            'source(temp)',
            ['op' => 'xDelete', 'path' => '/srv/www/wp-content/database/wp.sqlite-journal'],
            ['op' => 'open', 'source' => 'next', 'path' => '/srv/www/wp-content/database/wp.sqlite-shm', 'temporary' => true],
            'close(next)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next162-165 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-environment-next162-165', $plan()['dependencies'], true)),
    'vfs current source next162-165 preserves prior dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-mmap-shm-next158-161', $plan()['dependencies'], true)),
    'vfs current source next162-165 starts with hydrated sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next162-165 access accepts owner sibling' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][0]['status']),
    'vfs current source next162-165 access blocks other owner' => static fn (TestRunner $t) => $t->same('access path belongs to a different current-source owner', $plan()['events'][1]['reason']),
    'vfs current source next162-165 full pathname resolves beside owner' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp.sqlite', $plan()['events'][2]['full_pathname']),
    'vfs current source next162-165 sector size records direct risk' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][3]['direct_overflow_risk']),
    'vfs current source next162-165 device records powersafe overwrite' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][4]['powersafe_overwrite']),
    'vfs current source next162-165 randomness is source scoped' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['events'][5]['digest'])),
    'vfs current source next162-165 sleep accumulates on source' => static fn (TestRunner $t) => $t->same(2500, $plan()['events'][6]['total_sleep_microseconds']),
    'vfs current source next162-165 persistent delete needs sync' => static fn (TestRunner $t) => $t->same('persistent current-source delete requires directory sync', $plan()['events'][7]['reason']),
    'vfs current source next162-165 persistent delete succeeds with sync' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][8]['delete_count']),
    'vfs current source next162-165 selects temp source' => static fn (TestRunner $t) => $t->same('temp', $plan()['events'][9]['source']),
    'vfs current source next162-165 temp delete can omit sync' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][10]['status']),
    'vfs current source next162-165 open increments owner generation' => static fn (TestRunner $t) => $t->same(22, $plan()['events'][11]['next']['owner_generations']['/srv/www/wp-content/database/wp.sqlite']),
    'vfs current source next162-165 close next restores first open source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][12]['next']['current_source']),
    'vfs current source next162-165 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next162-165 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next162-165 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next162-165 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next162-165 rejects bad access mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'access', 'mode' => 'writeonly']], ['current' => $current])),
    'vfs current source next162-165 rejects unsupported device flag' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'device', 'flags' => ['networked']]], ['current' => $current])),
];
