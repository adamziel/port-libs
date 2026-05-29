<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext202205Plan;

$current = [
    'current_source' => 'main',
    'owner_generations' => ['/srv/www/wp-content/database/wp.sqlite' => 61],
    'sources' => [
        'main' => [
            'handle' => 'vfs194197-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'lock' => 'reserved',
            'data_version' => 12,
            'write_receipts' => [['page' => 4, 'bytes' => 4096, 'digest' => 'preexisting']],
            'durable_receipts' => [['page' => 4, 'bytes' => 4096, 'digest' => 'preexisting']],
        ],
    ],
];

$plan = static function () use ($current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext202205Plan::run([
            'prepare(4,lease-main-4)',
            'prepare(7,lease-main-7)',
            'publish(batch-202,4,7)',
            'ack(batch-202)',
            'reader(reader-a,4,lease-main-4)',
            'reader(reader-b,7,stale-lease)',
        ], [
            'current' => $current,
            'prerequisite_next198_201' => ['sync(normal)'],
        ]);
    }
    return $result;
};

return [
    'vfs current source next202-205 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-publish-reader-fence-next202-205', $plan()['dependencies'], true)),
    'vfs current source next202-205 keeps local prerequisite isolated' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-local-prerequisite-next198-201', $plan()['dependencies'], true)),
    'vfs current source next202-205 starts from durable prerequisite' => static fn (TestRunner $t) => $t->same(1, count($plan()['prerequisite_next198_201']['sources']['main']['durable_receipts'])),
    'vfs current source next202-205 prepares first page' => static fn (TestRunner $t) => $t->same('prepared', $plan()['events'][0]['status']),
    'vfs current source next202-205 records first lease' => static fn (TestRunner $t) => $t->same('lease-main-4', $plan()['next']['sources']['main']['prepared_pages'][4]['lease']),
    'vfs current source next202-205 prepares second page' => static fn (TestRunner $t) => $t->same(2, $plan()['events'][1]['prepared_count']),
    'vfs current source next202-205 publishes batch' => static fn (TestRunner $t) => $t->same('published', $plan()['events'][2]['status']),
    'vfs current source next202-205 publishes both pages' => static fn (TestRunner $t) => $t->same([4, 7], $plan()['events'][2]['batch']['pages']),
    'vfs current source next202-205 emits stable lease digest' => static fn (TestRunner $t) => $t->same(20, strlen($plan()['events'][2]['batch']['lease_digest'])),
    'vfs current source next202-205 acknowledges checkpoint batch' => static fn (TestRunner $t) => $t->same('acknowledged', $plan()['events'][3]['status']),
    'vfs current source next202-205 records ack token' => static fn (TestRunner $t) => $t->same('batch-202', $plan()['next']['sources']['main']['checkpoint_acks'][0]['token']),
    'vfs current source next202-205 retains matching reader' => static fn (TestRunner $t) => $t->same('reader-retained', $plan()['events'][4]['status']),
    'vfs current source next202-205 fences stale reader' => static fn (TestRunner $t) => $t->same('reader-reopen-required', $plan()['events'][5]['status']),
    'vfs current source next202-205 records reader fence' => static fn (TestRunner $t) => $t->same('reader-reopen-required', $plan()['next']['sources']['main']['reader_fences']['reader-b']['status']),
    'vfs current source next202-205 reports non overlap' => static fn (TestRunner $t) => $t->contains('does not repeat durable receipts next194-197', $plan()['non_overlap']),
    'vfs current source next202-205 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext202205Plan::run([])),
    'vfs current source next202-205 blocks stale durable prepare' => static fn (TestRunner $t) => $t->same('stale-durable-count', SQLiteVfsCurrentSourceNext202205Plan::run([['op' => 'prepare_page', 'page' => 4, 'lease' => 'lease-main-4', 'durable_count' => 2]], ['current' => $current])['events'][0]['status']),
    'vfs current source next202-205 blocks missing publish page' => static fn (TestRunner $t) => $t->same('missing-prepared-pages', SQLiteVfsCurrentSourceNext202205Plan::run(['publish(batch-missing,8)'], ['current' => $current])['events'][0]['status']),
    'vfs current source next202-205 blocks missing ack' => static fn (TestRunner $t) => $t->same('missing-published-batch', SQLiteVfsCurrentSourceNext202205Plan::run(['ack(batch-missing)'], ['current' => $current])['events'][0]['status']),
    'vfs current source next202-205 rejects bad reader token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext202205Plan::run([['op' => 'reader_fence', 'reader' => 'bad token', 'page' => 1, 'lease' => 'lease']], ['current' => $current])),
];
