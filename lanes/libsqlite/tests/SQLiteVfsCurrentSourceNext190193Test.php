<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext190193Plan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 47,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs182185-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'sector_size' => 4096,
            'characteristics' => ['powersafe_overwrite', 'safe_append'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext190193Plan::run([
            'lock(shared)',
            'access()',
            'truncate(12288)',
            'sync()',
            ['op' => 'xDelete'],
            ['op' => 'xAccess'],
            ['op' => 'open', 'source' => 'journal', 'path' => '/srv/www/wp-content/database/wp.sqlite-journal', 'size' => 4096, 'sector_size' => 8192, 'characteristics' => ['sequential']],
            ['op' => 'xSync'],
            ['op' => 'xLock', 'level' => 'reserved'],
            ['op' => 'xDelete'],
            ['op' => 'xUnlock', 'level' => 'none'],
            ['op' => 'xDelete'],
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next190-193 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-access-delete-truncate-sync-next190-193', $plan()['dependencies'], true)),
    'vfs current source next190-193 preserves 186-189 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-lock-filecontrol-next186-189', $plan()['dependencies'], true)),
    'vfs current source next190-193 starts with hydrated source' => static fn (TestRunner $t) => $t->same(1, $plan()['current']['source_count']),
    'vfs current source next190-193 shared lock is not reserved' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][0]['reserved']),
    'vfs current source next190-193 access sees hydrated source' => static fn (TestRunner $t) => $t->same('exists', $plan()['events'][1]['status']),
    'vfs current source next190-193 truncate records size' => static fn (TestRunner $t) => $t->same(12288, $plan()['events'][2]['size']),
    'vfs current source next190-193 sync advances count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][3]['sync_count']),
    'vfs current source next190-193 shared source delete succeeds' => static fn (TestRunner $t) => $t->same('deleted', $plan()['events'][4]['status']),
    'vfs current source next190-193 access reports missing after delete' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][5]['exists']),
    'vfs current source next190-193 open increments journal owner generation' => static fn (TestRunner $t) => $t->same(48, $plan()['events'][6]['next']['owner_generations']['/srv/www/wp-content/database/wp.sqlite']),
    'vfs current source next190-193 open hydrates journal size' => static fn (TestRunner $t) => $t->same(4096, $plan()['events'][6]['next']['sources']['journal']['size']),
    'vfs current source next190-193 journal sync advances count' => static fn (TestRunner $t) => $t->same(1, $plan()['events'][7]['sync_count']),
    'vfs current source next190-193 reserved journal delete is blocked' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][9]['status']),
    'vfs current source next190-193 unlocked journal delete succeeds' => static fn (TestRunner $t) => $t->same('deleted', $plan()['events'][11]['status']),
    'vfs current source next190-193 deleted journal no longer exists' => static fn (TestRunner $t) => $t->same(false, $plan()['next']['sources']['journal']['exists']),
    'vfs current source next190-193 final source count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['source_count']),
    'vfs current source next190-193 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next190-193 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext190193Plan::run([])),
    'vfs current source next190-193 rejects bad lock level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext190193Plan::run([['op' => 'xLock', 'level' => 'writer']], ['current' => $current])),
    'vfs current source next190-193 rejects small sector size' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext190193Plan::run([['op' => 'open', 'source' => 'bad', 'path' => '/tmp/bad.sqlite', 'sector_size' => 128]], ['current' => $current])),
    'vfs current source next190-193 rejects unknown characteristic' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext190193Plan::run([['op' => 'open', 'source' => 'bad', 'path' => '/tmp/bad.sqlite', 'characteristics' => ['mystery']]], ['current' => $current])),
    'vfs current source next190-193 rejects negative truncate size' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext190193Plan::run([['op' => 'xTruncate', 'size' => -1]], ['current' => $current])),
];
