<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext402417Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401');
$readyCurrent = [
    'current_source' => 'main',
    'sources' => [
        'main' => [
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published' => [
                ['token' => 'publish-next217', 'data_version' => 20],
                ['token' => 'shared-cache-next229', 'data_version' => 20],
                ['token' => 'shared-cache-next237', 'data_version' => 20],
                ['token' => 'shared-cache-next245', 'data_version' => 20],
                ['token' => 'shared-cache-next257', 'data_version' => 20],
                ['token' => 'shared-cache-next265', 'data_version' => 20],
                ['token' => 'shared-cache-next273', 'data_version' => 20],
                ['token' => 'shared-cache-next281', 'data_version' => 20],
                ['token' => 'shared-cache-next289', 'data_version' => 20],
                ['token' => 'shared-cache-next297', 'data_version' => 20],
                ['token' => 'shared-cache-next313', 'data_version' => 20],
                ['token' => 'shared-cache-next321', 'data_version' => 20],
                ['token' => 'shared-cache-next337', 'data_version' => 20],
                ['token' => 'shared-cache-next353', 'data_version' => 20],
                ['token' => 'shared-cache-next369', 'data_version' => 20],
                ['token' => 'shared-cache-next385', 'data_version' => 20],
                ['token' => 'shared-cache-next401', 'data_version' => 20],
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next401',
                    'receipt' => 'shared-cache-next401',
                    'data_version' => 20,
                    'published_count' => 17,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next401' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 17,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext402417Plan::run([
            'snapshot(reader-ready-next417,shared-cache-next401)',
            'claim(reader-ready-next417,shared-cache-next401,reader-reuse-next417)',
            'publish(reader-ready-next417,reader-reuse-next417,shared-cache-next417)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next402'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next402', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next417',
        'snapshot' => 'reader-ready-next417',
        'ack' => 'shared-cache-next401',
        'data_version' => 20,
        'published_count' => 17,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next417'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 17,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next417',
    'receipt' => 'shared-cache-next401',
    'data_version' => 20,
    'published_count' => 17,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next402', 'data_version' => 20];

return [
    'vfs current source next402-417 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next402-417', $plan()['dependencies'], true)),
    'vfs current source next402-417 records next386-401 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next386-401', $plan()['dependencies'], true)),
    'vfs current source next402-417 records next370-385 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next370-385', $plan()['dependencies'], true)),
    'vfs current source next402-417 records next354-369 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next354-369', $plan()['dependencies'], true)),
    'vfs current source next402-417 records next338-353 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next338-353', $plan()['dependencies'], true)),
    'vfs current source next402-417 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next402-417 snapshot records next401 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next401', $plan()['events'][0]['ack']),
    'vfs current source next402-417 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next402-417 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next417', $plan()['events'][1]['claim']),
    'vfs current source next402-417 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next402-417 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next402-417 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next417', $plan()['events'][2]['claim']),
    'vfs current source next402-417 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next401', $plan()['events'][2]['reuse_ack']),
    'vfs current source next402-417 publish count advances' => static fn (TestRunner $t) => $t->same(18, $plan()['events'][2]['published_count']),
    'vfs current source next402-417 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext402417Plan::run(['snapshot(reader-ready-next417,shared-cache-next401)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next402-417 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext402417Plan::run(['snapshot(reader-ready-next417,shared-cache-next401)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next402-417 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext402417Plan::run(['snapshot(reader-ready-next417,shared-cache-next401)', 'publish(reader-ready-next417,reader-reuse-next417,shared-cache-next417)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next402-417 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext402417Plan::run(['publish(reader-ready-next417,reader-reuse-next417,shared-cache-next417)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next402-417 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext402417Plan::run(['publish(reader-ready-next417,reader-reuse-next417,shared-cache-next417)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next402-417 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext402417Plan::run([])),
    'vfs current source next402-417 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext402417Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next417', 'ack' => 'shared-cache-next401', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next402-417 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext402417Plan::run(['republish(reader-ready-next417,shared-cache-next401)'], ['current' => $readyCurrent])),
    'vfs current source next402-417 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next386-401')),
];
