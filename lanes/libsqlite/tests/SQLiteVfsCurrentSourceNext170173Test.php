<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNextPlan;

$current = [
    'current_source' => 'main',
    'owner_generations' => [
        '/srv/www/wp-content/database/wp.sqlite' => 31,
    ],
    'sources' => [
        'main' => [
            'handle' => 'vfs166169-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'controls' => [
                'chunk_size' => 4096,
                'persist_wal' => true,
            ],
        ],
        'wal' => [
            'handle' => 'vfs166169-2',
            'path' => '/srv/www/wp-content/database/wp.sqlite-wal',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNextPlan::run([
            ['op' => 'xFileControl', 'name' => 'chunk-size', 'value' => 8192],
            ['op' => 'xFileControl', 'name' => 'powersafe_overwrite', 'value' => false],
            'pathname(-wal)',
            'tempname(etilqs,7)',
            'source(wal)',
            ['op' => 'xFileControl', 'name' => 'size_hint', 'value' => 65536],
            ['op' => 'open', 'source' => 'journal', 'path' => '/srv/www/wp-content/database/wp.sqlite-journal', 'controls' => ['tempfilename' => '/srv/www/wp-content/database/etilqs-journal.tmp']],
            'close(journal)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next170-173 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-path-control-names-next170-173', $plan()['dependencies'], true)),
    'vfs current source next170-173 preserves 166-169 marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-time-error-syscall-next166-169', $plan()['dependencies'], true)),
    'vfs current source next170-173 starts with hydrated sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next170-173 updates chunk size control' => static fn (TestRunner $t) => $t->same(8192, $plan()['events'][0]['next']['sources']['main']['controls']['chunk_size']),
    'vfs current source next170-173 records boolean control' => static fn (TestRunner $t) => $t->same(false, $plan()['events'][1]['value']),
    'vfs current source next170-173 derives wal pathname from selected source' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp.sqlite-wal', $plan()['events'][2]['path']),
    'vfs current source next170-173 keeps wal pathname on same owner' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][2]['same_owner']),
    'vfs current source next170-173 generates temp name beside source' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/etilqs-7.tmp', $plan()['events'][3]['path']),
    'vfs current source next170-173 selects wal source' => static fn (TestRunner $t) => $t->same('wal', $plan()['events'][4]['source']),
    'vfs current source next170-173 records control on selected wal source' => static fn (TestRunner $t) => $t->same(65536, $plan()['events'][5]['next']['sources']['wal']['controls']['size_hint']),
    'vfs current source next170-173 open increments owner generation' => static fn (TestRunner $t) => $t->same(32, $plan()['events'][6]['next']['owner_generations']['/srv/www/wp-content/database/wp.sqlite']),
    'vfs current source next170-173 hydrates open controls' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/etilqs-journal.tmp', $plan()['events'][6]['next']['sources']['journal']['controls']['tempfilename']),
    'vfs current source next170-173 close restores first open source' => static fn (TestRunner $t) => $t->same('main', $plan()['events'][7]['next']['current_source']),
    'vfs current source next170-173 returns main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next170-173 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next170-173 final open count' => static fn (TestRunner $t) => $t->same(2, $plan()['next']['open_source_count']),
    'vfs current source next170-173 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([])),
    'vfs current source next170-173 rejects bad file control' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run([['op' => 'xFileControl', 'name' => 'unknown']], ['current' => $current])),
    'vfs current source next170-173 rejects bad path suffix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNextPlan::run(['pathname(-other)'], ['current' => $current])),
];
