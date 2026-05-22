<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider(
    supportedHashes: new HashSet(HashType::SHA1, HashType::MD5),
    serverSideMove: true,
    serverSideCopy: true,
);
$plan = new SyncPlan();

$remote->put('staging/site.wxr', '<rss>fresh export</rss>', [
    'modTime' => '2026-05-22T02:00:00Z',
    'mimeType' => 'application/rss+xml',
    'metadata' => [
        'description' => 'portable WordPress export',
        'permissions' => '[{"roles":["read"]}]',
    ],
    'id' => 'staging-export',
]);
$remote->put('exports/site.wxr', '<rss>previous export</rss>');

$onedrive = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'onedrive-copied-export',
        'mimeType' => 'application/rss+xml',
        'hashes' => [
            'sha1Hash' => strtoupper(hash('sha1', '<rss>fresh export</rss>')),
        ],
    ],
]);
$onedriveInfo = $remote->info('exports/site.wxr');
$onedriveSha1 = $remote->hashes('exports/site.wxr', new HashSet(HashType::SHA1))[HashType::SHA1];

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$yandex = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'yandex',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'customProperties' => ['rclone_modified' => '2026-05-22T02:00:00Z'],
        'modified' => '2026-05-22T04:15:00Z',
        'md5' => strtoupper(hash('md5', '<rss>fresh export</rss>')),
        'mimeType' => 'application/rss+xml',
    ],
]);
$yandexInfo = $remote->info('exports/site.wxr');
$yandexMd5 = $remote->hashes('exports/site.wxr', new HashSet(HashType::MD5))[HashType::MD5];

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveAccessDeniedError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'providerError' => ['kind' => 'async-access-denied'],
    ]);
} catch (RuntimeException $throwable) {
    $onedriveAccessDeniedError = $throwable->getMessage();
}

return [
    'onedriveCopiedId' => $onedrive['copied']->id,
    'onedriveModTime' => $onedriveInfo->modTime,
    'onedrivePermissionMode' => $onedrive['copied']->metadata['onedrive_permissions_mode'],
    'onedriveSha1' => $onedriveSha1,
    'onedriveMetadataRefresh' => $onedrive['metadataRefresh'],
    'yandexCopiedModTime' => $yandexInfo->modTime,
    'yandexMd5' => $yandexMd5,
    'yandexMetadataRefresh' => $yandex['metadataRefresh'],
    'onedriveAccessDeniedError' => $onedriveAccessDeniedError,
    'restoredAfterAccessDenied' => $remote->get('exports/site.wxr'),
];
