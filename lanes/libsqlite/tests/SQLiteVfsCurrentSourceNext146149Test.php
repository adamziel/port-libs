<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext146149Plan;

$current = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs137-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'file_controls' => ['persist_wal' => true],
        ],
        'temp' => [
            'handle' => 'vfs137-2',
            'path' => '/srv/www/wp-content/uploads/sqlite-tmp/sqlite-temp-2.db',
            'locks' => ['shared' => 'wp-import'],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext146149Plan::run([
            'source(temp)',
            ['op' => 'filecontrol', 'control' => 'checkpoint_fullfsync', 'value' => true],
            'open(main, /srv/www/wp-content/database/wp.sqlite)',
            'lock(reserved, wp-import)',
            ['op' => 'open', 'source' => 'archive', 'path' => '/srv/www/wp-content/database/archive.sqlite', 'readonly' => true],
            'lock(shared, wp-reader)',
            'lock(reserved, wp-reader)',
            'source(main)',
        ], ['current' => $current]);
    }
    return $result;
};

return [
    'vfs current source next146-149 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-next146-149', $plan()['dependencies'], true)),
    'vfs current source next146-149 current has two sources' => static fn (TestRunner $t) => $t->same(2, $plan()['current']['source_count']),
    'vfs current source next146-149 starts on hydrated main' => static fn (TestRunner $t) => $t->same('main', $plan()['current']['current_source']),
    'vfs current source next146-149 selects hydrated temp' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][0]['status']),
    'vfs current source next146-149 filecontrol applies to selected temp' => static fn (TestRunner $t) => $t->same('temp', $plan()['events'][1]['source']),
    'vfs current source next146-149 filecontrol records value' => static fn (TestRunner $t) => $t->same(true, $plan()['events'][1]['next']['sources']['temp']['file_controls']['checkpoint_fullfsync']),
    'vfs current source next146-149 reuses hydrated main handle' => static fn (TestRunner $t) => $t->same('reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next146-149 reused main keeps original handle' => static fn (TestRunner $t) => $t->same('vfs137-1', $plan()['events'][2]['handle']),
    'vfs current source next146-149 writer lock uses main owner' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp.sqlite', $plan()['events'][3]['owner']),
    'vfs current source next146-149 opens readonly archive' => static fn (TestRunner $t) => $t->same('open', $plan()['events'][4]['status']),
    'vfs current source next146-149 archive handle is next sequence' => static fn (TestRunner $t) => $t->same('vfs146149-3', $plan()['events'][4]['handle']),
    'vfs current source next146-149 readonly shared lock allowed' => static fn (TestRunner $t) => $t->same('ok', $plan()['events'][5]['status']),
    'vfs current source next146-149 readonly reserved lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $plan()['events'][6]['status']),
    'vfs current source next146-149 readonly blocked reason' => static fn (TestRunner $t) => $t->same('readonly current-source cannot take writer lock', $plan()['events'][6]['reason']),
    'vfs current source next146-149 returns to main' => static fn (TestRunner $t) => $t->same('main', $plan()['next']['current_source']),
    'vfs current source next146-149 final source count' => static fn (TestRunner $t) => $t->same(3, $plan()['next']['source_count']),
    'vfs current source next146-149 preserves hydrated temp lock' => static fn (TestRunner $t) => $t->same('wp-import', $plan()['next']['sources']['temp']['locks']['shared']),
    'vfs current source next146-149 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext146149Plan::run([])),
    'vfs current source next146-149 rejects missing hydrated source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext146149Plan::run(['source(main)'], ['current' => ['current_source' => 'missing', 'sources' => []]])),
    'vfs current source next146-149 rejects bad hydrated handle' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext146149Plan::run(['source(main)'], ['current' => ['sources' => ['main' => ['handle' => '../bad', 'path' => '/tmp/a']]]])),
];
