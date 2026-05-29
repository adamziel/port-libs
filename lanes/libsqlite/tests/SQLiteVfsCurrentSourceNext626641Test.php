<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCurrentSourceNext626641Plan;

$previousDigest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625');
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
                ['token' => 'shared-cache-next417', 'data_version' => 20],
                ['token' => 'shared-cache-next433', 'data_version' => 20],
                ['token' => 'shared-cache-next449', 'data_version' => 20],
                ['token' => 'shared-cache-next465', 'data_version' => 20],
                ['token' => 'shared-cache-next481', 'data_version' => 20],
                ['token' => 'shared-cache-next497', 'data_version' => 20],
                ['token' => 'shared-cache-next513', 'data_version' => 20],
                ['token' => 'shared-cache-next529', 'data_version' => 20],
                ['token' => 'shared-cache-next545', 'data_version' => 20],
                ['token' => 'shared-cache-next561', 'data_version' => 20],
                ['token' => 'shared-cache-next577', 'data_version' => 20],
                ['token' => 'shared-cache-next593', 'data_version' => 20],
                ['token' => 'shared-cache-next625', 'data_version' => 20],
            ],
            'reuse_acks' => [
                [
                    'snapshot' => 'reader-ready-next641',
                        'receipt' => 'shared-cache-next625',
                        'data_version' => 20,
                    'published_count' => 30,
                    'receipt_digest' => $previousDigest,
                ],
            ],
        ],
    ],
    'snapshots' => [
        'reader-ready-next625' => [
            'source' => 'main',
            'handle' => 'vfs214217-1',
            'path' => '/srv/www/wp-content/database/wp.sqlite',
            'owner' => '/srv/www/wp-content/database/wp.sqlite',
            'data_version' => 20,
            'published_count' => 30,
            'receipt_digest' => $previousDigest,
        ],
    ],
];

$plan = static function () use ($readyCurrent): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next641,shared-cache-next625)',
            'claim(reader-ready-next641,shared-cache-next625,reader-reuse-next641)',
            'publish(reader-ready-next641,reader-reuse-next641,shared-cache-next641)',
        ], ['current' => $readyCurrent]);
    }
    return $result;
};

$next642657Current = $readyCurrent;
$next642657Current['sources']['main']['published'][] = ['token' => 'shared-cache-next641', 'data_version' => 20];
$next642657Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641');
$next642657Current['snapshots']['reader-ready-next641'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 31,
    'receipt_digest' => $next642657Digest,
];

$next642657Plan = static function () use ($next642657Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next657,shared-cache-next641)',
            'claim(reader-ready-next657,shared-cache-next641,reader-reuse-next657)',
            'publish(reader-ready-next657,reader-reuse-next657,shared-cache-next657)',
        ], ['current' => $next642657Current]);
    }
    return $result;
};

$next642657OldAckCurrent = $next642657Current;
$next642657OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next642', 'data_version' => 20];

$next658673Current = $next642657Current;
$next658673Current['sources']['main']['published'][] = ['token' => 'shared-cache-next657', 'data_version' => 20];
$next658673Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657');
$next658673Current['snapshots']['reader-ready-next657'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 32,
    'receipt_digest' => $next658673Digest,
];

$next658673Plan = static function () use ($next658673Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next673,shared-cache-next657)',
            'claim(reader-ready-next673,shared-cache-next657,reader-reuse-next673)',
            'publish(reader-ready-next673,reader-reuse-next673,shared-cache-next673)',
        ], ['current' => $next658673Current]);
    }
    return $result;
};

$next658673OldAckCurrent = $next658673Current;
$next658673OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next658', 'data_version' => 20];

$next674689Current = $next658673Current;
$next674689Current['sources']['main']['published'][] = ['token' => 'shared-cache-next673', 'data_version' => 20];
$next674689Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673');
$next674689Current['snapshots']['reader-ready-next673'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 33,
    'receipt_digest' => $next674689Digest,
];

$next674689Plan = static function () use ($next674689Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next689,shared-cache-next673)',
            'claim(reader-ready-next689,shared-cache-next673,reader-reuse-next689)',
            'publish(reader-ready-next689,reader-reuse-next689,shared-cache-next689)',
        ], ['current' => $next674689Current]);
    }
    return $result;
};

$next674689OldAckCurrent = $next674689Current;
$next674689OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next674', 'data_version' => 20];

$next690705Current = $next674689Current;
$next690705Current['sources']['main']['published'][] = ['token' => 'shared-cache-next689', 'data_version' => 20];
$next690705Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689');
$next690705Current['snapshots']['reader-ready-next689'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 34,
    'receipt_digest' => $next690705Digest,
];

$next690705Plan = static function () use ($next690705Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next705,shared-cache-next689)',
            'claim(reader-ready-next705,shared-cache-next689,reader-reuse-next705)',
            'publish(reader-ready-next705,reader-reuse-next705,shared-cache-next705)',
        ], ['current' => $next690705Current]);
    }
    return $result;
};

$next690705OldAckCurrent = $next690705Current;
$next690705OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next690', 'data_version' => 20];

$next706721Current = $next690705Current;
$next706721Current['sources']['main']['published'][] = ['token' => 'shared-cache-next705', 'data_version' => 20];
$next706721Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689|shared-cache-next705');
$next706721Current['snapshots']['reader-ready-next705'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 35,
    'receipt_digest' => $next706721Digest,
];

$next706721Plan = static function () use ($next706721Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next721,shared-cache-next705)',
            'claim(reader-ready-next721,shared-cache-next705,reader-reuse-next721)',
            'publish(reader-ready-next721,reader-reuse-next721,shared-cache-next721)',
        ], ['current' => $next706721Current]);
    }
    return $result;
};

$next706721OldAckCurrent = $next706721Current;
$next706721OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next706', 'data_version' => 20];

$next722737Current = $next706721Current;
$next722737Current['sources']['main']['published'][] = ['token' => 'shared-cache-next721', 'data_version' => 20];
$next722737Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689|shared-cache-next705|shared-cache-next721');
$next722737Current['snapshots']['reader-ready-next721'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 36,
    'receipt_digest' => $next722737Digest,
];

$next722737Plan = static function () use ($next722737Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next737,shared-cache-next721)',
            'claim(reader-ready-next737,shared-cache-next721,reader-reuse-next737)',
            'publish(reader-ready-next737,reader-reuse-next737,shared-cache-next737)',
        ], ['current' => $next722737Current]);
    }
    return $result;
};

$next722737OldAckCurrent = $next722737Current;
$next722737OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next722', 'data_version' => 20];

$next738753Current = $next722737Current;
$next738753Current['sources']['main']['published'][] = ['token' => 'shared-cache-next737', 'data_version' => 20];
$next738753Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689|shared-cache-next705|shared-cache-next721|shared-cache-next737');
$next738753Current['snapshots']['reader-ready-next737'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 37,
    'receipt_digest' => $next738753Digest,
];

$next738753Plan = static function () use ($next738753Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next753,shared-cache-next737)',
            'claim(reader-ready-next753,shared-cache-next737,reader-reuse-next753)',
            'publish(reader-ready-next753,reader-reuse-next753,shared-cache-next753)',
        ], ['current' => $next738753Current]);
    }
    return $result;
};

$next738753OldAckCurrent = $next738753Current;
$next738753OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next738', 'data_version' => 20];

$next754769Current = $next738753Current;
$next754769Current['sources']['main']['published'][] = ['token' => 'shared-cache-next753', 'data_version' => 20];
$next754769Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689|shared-cache-next705|shared-cache-next721|shared-cache-next737|shared-cache-next753');
$next754769Current['snapshots']['reader-ready-next753'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 38,
    'receipt_digest' => $next754769Digest,
];

$next754769Plan = static function () use ($next754769Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next769,shared-cache-next753)',
            'claim(reader-ready-next769,shared-cache-next753,reader-reuse-next769)',
            'publish(reader-ready-next769,reader-reuse-next769,shared-cache-next769)',
        ], ['current' => $next754769Current]);
    }
    return $result;
};

$next754769OldAckCurrent = $next754769Current;
$next754769OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next754', 'data_version' => 20];

$next770785Current = $next754769Current;
$next770785Current['sources']['main']['published'][] = ['token' => 'shared-cache-next769', 'data_version' => 20];
$next770785Digest = hash('sha256', 'publish-next217|shared-cache-next229|shared-cache-next237|shared-cache-next245|shared-cache-next257|shared-cache-next265|shared-cache-next273|shared-cache-next281|shared-cache-next289|shared-cache-next297|shared-cache-next313|shared-cache-next321|shared-cache-next337|shared-cache-next353|shared-cache-next369|shared-cache-next385|shared-cache-next401|shared-cache-next417|shared-cache-next433|shared-cache-next449|shared-cache-next465|shared-cache-next481|shared-cache-next497|shared-cache-next513|shared-cache-next529|shared-cache-next545|shared-cache-next561|shared-cache-next577|shared-cache-next593|shared-cache-next625|shared-cache-next641|shared-cache-next657|shared-cache-next673|shared-cache-next689|shared-cache-next705|shared-cache-next721|shared-cache-next737|shared-cache-next753|shared-cache-next769');
$next770785Current['snapshots']['reader-ready-next769'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 39,
    'receipt_digest' => $next770785Digest,
];

$next770785Plan = static function () use ($next770785Current): array {
    static $result = null;
    if ($result === null) {
        $result = SQLiteVfsCurrentSourceNext626641Plan::run([
            'snapshot(reader-ready-next785,shared-cache-next769)',
            'claim(reader-ready-next785,shared-cache-next769,reader-reuse-next785)',
            'publish(reader-ready-next785,reader-reuse-next785,shared-cache-next785)',
        ], ['current' => $next770785Current]);
    }
    return $result;
};

$next770785OldAckCurrent = $next770785Current;
$next770785OldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next770', 'data_version' => 20];

$dirtyCurrent = $readyCurrent;
$dirtyCurrent['sources']['main']['dirty_pages'] = [
    ['page' => 12, 'bytes' => 4096, 'digest' => 'dirty-next626'],
];

$oldAckCurrent = $readyCurrent;
$oldAckCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next626', 'data_version' => 20];

$claimedCurrent = $readyCurrent;
$claimedCurrent['sources']['main']['reuse_claims'] = [
    [
        'token' => 'reader-reuse-next641',
        'snapshot' => 'reader-ready-next641',
        'ack' => 'shared-cache-next625',
        'data_version' => 20,
        'published_count' => 30,
        'receipt_digest' => $previousDigest,
    ],
];
$claimedCurrent['snapshots']['reader-ready-next641'] = [
    'source' => 'main',
    'handle' => 'vfs214217-1',
    'path' => '/srv/www/wp-content/database/wp.sqlite',
    'owner' => '/srv/www/wp-content/database/wp.sqlite',
    'data_version' => 20,
    'published_count' => 30,
    'receipt_digest' => $previousDigest,
];
$claimedCurrent['sources']['main']['reuse_acks'][] = [
    'snapshot' => 'reader-ready-next641',
    'receipt' => 'shared-cache-next625',
    'data_version' => 20,
    'published_count' => 30,
    'receipt_digest' => $previousDigest,
];

$staleClaimCurrent = $claimedCurrent;
$staleClaimCurrent['sources']['main']['published'][] = ['token' => 'late-publish-next626', 'data_version' => 20];

return [
    'vfs current source next626-641 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next626-641', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next610-625 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next610-625', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next562-577 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next562-577', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next530-545 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next530-545', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next514-529 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next514-529', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next498-513 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next498-513', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next482-497 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next482-497', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next450-465 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next450-465', $plan()['dependencies'], true)),
    'vfs current source next626-641 records next434-449 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next434-449', $plan()['dependencies'], true)),
    'vfs current source next626-641 snapshots fresh current source' => static fn (TestRunner $t) => $t->same('snapshotted-current-source', $plan()['events'][0]['status']),
    'vfs current source next626-641 snapshot records next625 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next625', $plan()['events'][0]['ack']),
    'vfs current source next626-641 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $plan()['events'][1]['status']),
    'vfs current source next626-641 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next641', $plan()['events'][1]['claim']),
    'vfs current source next626-641 claim has no blockers' => static fn (TestRunner $t) => $t->same([], $plan()['events'][1]['blocked_reasons']),
    'vfs current source next626-641 publishes after claim' => static fn (TestRunner $t) => $t->same('published-reused-current-source', $plan()['events'][2]['status']),
    'vfs current source next626-641 publish uses claim' => static fn (TestRunner $t) => $t->same('reader-reuse-next641', $plan()['events'][2]['claim']),
    'vfs current source next626-641 publish preserves ack' => static fn (TestRunner $t) => $t->same('shared-cache-next625', $plan()['events'][2]['reuse_ack']),
    'vfs current source next626-641 publish count advances' => static fn (TestRunner $t) => $t->same(31, $plan()['events'][2]['published_count']),
    'vfs current source next626-641 blocks dirty snapshot' => static fn (TestRunner $t) => $t->same(true, in_array('dirty-pages-present', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next641,shared-cache-next625)'], ['current' => $dirtyCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next626-641 blocks old snapshot ack' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next641,shared-cache-next625)'], ['current' => $oldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next626-641 blocks publish without claim' => static fn (TestRunner $t) => $t->same(true, in_array('missing-reuse-claim', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next641,shared-cache-next625)', 'publish(reader-ready-next641,reader-reuse-next641,shared-cache-next641)'], ['current' => $readyCurrent])['events'][1]['blocked_reasons'], true)),
    'vfs current source next626-641 blocks stale claim' => static fn (TestRunner $t) => $t->same(true, in_array('stale-reuse-claim', SQLiteVfsCurrentSourceNext626641Plan::run(['publish(reader-ready-next641,reader-reuse-next641,shared-cache-next641)'], ['current' => $staleClaimCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next626-641 accepts preclaimed publish' => static fn (TestRunner $t) => $t->same('published-reused-current-source', SQLiteVfsCurrentSourceNext626641Plan::run(['publish(reader-ready-next641,reader-reuse-next641,shared-cache-next641)'], ['current' => $claimedCurrent])['events'][0]['status']),
    'vfs current source next626-641 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext626641Plan::run([])),
    'vfs current source next626-641 rejects bad claim token' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext626641Plan::run([['op' => 'claim', 'snapshot' => 'reader-ready-next641', 'ack' => 'shared-cache-next625', 'claim' => 'bad claim']], ['current' => $readyCurrent])),
    'vfs current source next626-641 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsCurrentSourceNext626641Plan::run(['republish(reader-ready-next641,shared-cache-next641)'], ['current' => $readyCurrent])),
    'vfs current source next626-641 notes prior window non-overlap' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['non_overlap'], 'prior next610-625')),
    'vfs current source next642-657 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next642-657', $next642657Plan()['dependencies'], true)),
    'vfs current source next642-657 records next626-641 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next626-641', $next642657Plan()['dependencies'], true)),
    'vfs current source next642-657 snapshots from next641 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next641', $next642657Plan()['events'][0]['ack']),
    'vfs current source next642-657 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next642657Plan()['events'][1]['status']),
    'vfs current source next642-657 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next657', $next642657Plan()['events'][1]['claim']),
    'vfs current source next642-657 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next657', $next642657Plan()['events'][2]['token']),
    'vfs current source next642-657 publish preserves next641 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next641', $next642657Plan()['events'][2]['reuse_ack']),
    'vfs current source next642-657 publish count advances' => static fn (TestRunner $t) => $t->same(32, $next642657Plan()['events'][2]['published_count']),
    'vfs current source next642-657 blocks stale next641 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next657,shared-cache-next641)'], ['current' => $next642657OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next642-657 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next642657Plan()['non_overlap'], 'next642-657 follows the integrated next626-641 handoff')),
    'vfs current source next658-673 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next658-673', $next658673Plan()['dependencies'], true)),
    'vfs current source next658-673 records next642-657 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next642-657', $next658673Plan()['dependencies'], true)),
    'vfs current source next658-673 snapshots from next657 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next657', $next658673Plan()['events'][0]['ack']),
    'vfs current source next658-673 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next658673Plan()['events'][1]['status']),
    'vfs current source next658-673 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next673', $next658673Plan()['events'][1]['claim']),
    'vfs current source next658-673 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next673', $next658673Plan()['events'][2]['token']),
    'vfs current source next658-673 publish preserves next657 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next657', $next658673Plan()['events'][2]['reuse_ack']),
    'vfs current source next658-673 publish count advances' => static fn (TestRunner $t) => $t->same(33, $next658673Plan()['events'][2]['published_count']),
    'vfs current source next658-673 blocks stale next657 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next673,shared-cache-next657)'], ['current' => $next658673OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next658-673 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next658673Plan()['non_overlap'], 'next658-673 follows the integrated next642-657 handoff')),
    'vfs current source next674-689 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next674-689', $next674689Plan()['dependencies'], true)),
    'vfs current source next674-689 records next658-673 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next658-673', $next674689Plan()['dependencies'], true)),
    'vfs current source next674-689 snapshots from next673 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next673', $next674689Plan()['events'][0]['ack']),
    'vfs current source next674-689 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next674689Plan()['events'][1]['status']),
    'vfs current source next674-689 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next689', $next674689Plan()['events'][1]['claim']),
    'vfs current source next674-689 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next689', $next674689Plan()['events'][2]['token']),
    'vfs current source next674-689 publish preserves next673 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next673', $next674689Plan()['events'][2]['reuse_ack']),
    'vfs current source next674-689 publish count advances' => static fn (TestRunner $t) => $t->same(34, $next674689Plan()['events'][2]['published_count']),
    'vfs current source next674-689 blocks stale next673 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next689,shared-cache-next673)'], ['current' => $next674689OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next674-689 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next674689Plan()['non_overlap'], 'next674-689 follows the integrated next658-673 handoff')),
    'vfs current source next690-705 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next690-705', $next690705Plan()['dependencies'], true)),
    'vfs current source next690-705 records next674-689 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next674-689', $next690705Plan()['dependencies'], true)),
    'vfs current source next690-705 snapshots from next689 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next689', $next690705Plan()['events'][0]['ack']),
    'vfs current source next690-705 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next690705Plan()['events'][1]['status']),
    'vfs current source next690-705 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next705', $next690705Plan()['events'][1]['claim']),
    'vfs current source next690-705 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next705', $next690705Plan()['events'][2]['token']),
    'vfs current source next690-705 publish preserves next689 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next689', $next690705Plan()['events'][2]['reuse_ack']),
    'vfs current source next690-705 publish count advances' => static fn (TestRunner $t) => $t->same(35, $next690705Plan()['events'][2]['published_count']),
    'vfs current source next690-705 blocks stale next689 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next705,shared-cache-next689)'], ['current' => $next690705OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next690-705 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next690705Plan()['non_overlap'], 'next690-705 follows the integrated next674-689 handoff')),
    'vfs current source next706-721 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next706-721', $next706721Plan()['dependencies'], true)),
    'vfs current source next706-721 records next690-705 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next690-705', $next706721Plan()['dependencies'], true)),
    'vfs current source next706-721 snapshots from next705 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next705', $next706721Plan()['events'][0]['ack']),
    'vfs current source next706-721 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next706721Plan()['events'][1]['status']),
    'vfs current source next706-721 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next721', $next706721Plan()['events'][1]['claim']),
    'vfs current source next706-721 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next721', $next706721Plan()['events'][2]['token']),
    'vfs current source next706-721 publish preserves next705 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next705', $next706721Plan()['events'][2]['reuse_ack']),
    'vfs current source next706-721 publish count advances' => static fn (TestRunner $t) => $t->same(36, $next706721Plan()['events'][2]['published_count']),
    'vfs current source next706-721 blocks stale next705 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next721,shared-cache-next705)'], ['current' => $next706721OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next706-721 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next706721Plan()['non_overlap'], 'next706-721 follows the integrated next690-705 handoff')),
    'vfs current source next722-737 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next722-737', $next722737Plan()['dependencies'], true)),
    'vfs current source next722-737 records next706-721 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next706-721', $next722737Plan()['dependencies'], true)),
    'vfs current source next722-737 snapshots from next721 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next721', $next722737Plan()['events'][0]['ack']),
    'vfs current source next722-737 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next722737Plan()['events'][1]['status']),
    'vfs current source next722-737 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next737', $next722737Plan()['events'][1]['claim']),
    'vfs current source next722-737 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next737', $next722737Plan()['events'][2]['token']),
    'vfs current source next722-737 publish preserves next721 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next721', $next722737Plan()['events'][2]['reuse_ack']),
    'vfs current source next722-737 publish count advances' => static fn (TestRunner $t) => $t->same(37, $next722737Plan()['events'][2]['published_count']),
    'vfs current source next722-737 blocks stale next721 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next737,shared-cache-next721)'], ['current' => $next722737OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next722-737 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next722737Plan()['non_overlap'], 'next722-737 follows the integrated next706-721 handoff')),
    'vfs current source next738-753 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next738-753', $next738753Plan()['dependencies'], true)),
    'vfs current source next738-753 records next722-737 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next722-737', $next738753Plan()['dependencies'], true)),
    'vfs current source next738-753 snapshots from next737 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next737', $next738753Plan()['events'][0]['ack']),
    'vfs current source next738-753 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next738753Plan()['events'][1]['status']),
    'vfs current source next738-753 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next753', $next738753Plan()['events'][1]['claim']),
    'vfs current source next738-753 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next753', $next738753Plan()['events'][2]['token']),
    'vfs current source next738-753 publish preserves next737 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next737', $next738753Plan()['events'][2]['reuse_ack']),
    'vfs current source next738-753 publish count advances' => static fn (TestRunner $t) => $t->same(38, $next738753Plan()['events'][2]['published_count']),
    'vfs current source next738-753 blocks stale next737 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next753,shared-cache-next737)'], ['current' => $next738753OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next738-753 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next738753Plan()['non_overlap'], 'next738-753 follows the integrated next722-737 handoff')),
    'vfs current source next754-769 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next754-769', $next754769Plan()['dependencies'], true)),
    'vfs current source next754-769 records next738-753 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next738-753', $next754769Plan()['dependencies'], true)),
    'vfs current source next754-769 snapshots from next753 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next753', $next754769Plan()['events'][0]['ack']),
    'vfs current source next754-769 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next754769Plan()['events'][1]['status']),
    'vfs current source next754-769 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next769', $next754769Plan()['events'][1]['claim']),
    'vfs current source next754-769 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next769', $next754769Plan()['events'][2]['token']),
    'vfs current source next754-769 publish preserves next753 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next753', $next754769Plan()['events'][2]['reuse_ack']),
    'vfs current source next754-769 publish count advances' => static fn (TestRunner $t) => $t->same(39, $next754769Plan()['events'][2]['published_count']),
    'vfs current source next754-769 blocks stale next753 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next769,shared-cache-next753)'], ['current' => $next754769OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next754-769 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next754769Plan()['non_overlap'], 'next754-769 follows the integrated next738-753 handoff')),
    'vfs current source next770-785 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next770-785', $next770785Plan()['dependencies'], true)),
    'vfs current source next770-785 records next754-769 prerequisite' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-snapshot-reuse-publish-next754-769', $next770785Plan()['dependencies'], true)),
    'vfs current source next770-785 snapshots from next769 handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next769', $next770785Plan()['events'][0]['ack']),
    'vfs current source next770-785 claims reusable snapshot' => static fn (TestRunner $t) => $t->same('claimed-reusable-current-source', $next770785Plan()['events'][1]['status']),
    'vfs current source next770-785 records claim token' => static fn (TestRunner $t) => $t->same('reader-reuse-next785', $next770785Plan()['events'][1]['claim']),
    'vfs current source next770-785 publishes shared cache handoff' => static fn (TestRunner $t) => $t->same('shared-cache-next785', $next770785Plan()['events'][2]['token']),
    'vfs current source next770-785 publish preserves next769 ack' => static fn (TestRunner $t) => $t->same('shared-cache-next769', $next770785Plan()['events'][2]['reuse_ack']),
    'vfs current source next770-785 publish count advances' => static fn (TestRunner $t) => $t->same(40, $next770785Plan()['events'][2]['published_count']),
    'vfs current source next770-785 blocks stale next769 handoff' => static fn (TestRunner $t) => $t->same(true, in_array('ack-not-latest-publish', SQLiteVfsCurrentSourceNext626641Plan::run(['snapshot(reader-ready-next785,shared-cache-next769)'], ['current' => $next770785OldAckCurrent])['events'][0]['blocked_reasons'], true)),
    'vfs current source next770-785 notes non-overlap handoff' => static fn (TestRunner $t) => $t->same(true, str_contains($next770785Plan()['non_overlap'], 'next770-785 follows the integrated next754-769 handoff')),
];
