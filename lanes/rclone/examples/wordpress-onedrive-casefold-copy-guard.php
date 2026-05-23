<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$plan = new SyncPlan();
$remote = new MemoryProvider(caseInsensitive: true, serverSideMove: true, serverSideCopy: true);
$remote->put('exports/site.wxr', '<rss>portable export</rss>', [
    'modTime' => '2026-05-22T02:00:00Z',
    'metadata' => ['wp-artifact' => 'published-export'],
]);

$caseFoldGuard = null;
try {
    $plan->serverSideCopyReplace($remote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'guardCaseFoldSameRemote' => true,
        'guardCaseFoldAfterRemoveExisting' => true,
    ]);
} catch (RuntimeException $throwable) {
    $caseFoldGuard = $throwable->getMessage();
}

$blockedRemote = new MemoryProvider(caseInsensitive: true, serverSideMove: false, serverSideCopy: true);
$blockedRemote->put('exports/site.wxr', '<rss>portable export</rss>');
$removeExistingFirstError = null;
try {
    $plan->serverSideCopyReplace($blockedRemote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
        'provider' => 'onedrive',
        'guardCaseFoldSameRemote' => true,
        'guardCaseFoldAfterRemoveExisting' => true,
    ]);
} catch (RuntimeException $throwable) {
    $removeExistingFirstError = $throwable->getMessage();
}

$restored = $remote->info('exports/site.wxr');

return [
    'caseFoldGuard' => $caseFoldGuard,
    'restoredPath' => $restored->path,
    'restoredBytes' => $remote->get('exports/site.wxr'),
    'restoredMetadata' => $restored->metadata,
    'temporaryCopyVisible' => $remote->pathExists('EXPORTS/SITE.WXR.wpcopy'),
    'removeExistingFirstError' => $removeExistingFirstError,
];
