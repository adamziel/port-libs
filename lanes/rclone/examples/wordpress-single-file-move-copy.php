<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$local = new MemoryProvider();
$remote = new MemoryProvider(true);

$local->put('wp-content/uploads/2026/05/hero-renamed.jpg', $tree['wp-content/uploads/2026/05/hero.jpg'], [
    'modTime' => '2026-05-22T01:00:00Z',
]);
$local->put('exports/site.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-22T01:00:00Z',
]);

$remote->put('wp-content/uploads/2026/05/Hero.JPG', $tree['wp-content/uploads/2026/05/hero.jpg'], [
    'modTime' => '2026-05-21T01:00:00Z',
]);
$remote->put('exports/site.wxr', '<rss>remote recovery export</rss>', [
    'modTime' => '2026-05-21T01:00:00Z',
]);

$plan = new SyncPlan();
$caseRepair = $plan->moveFile(
    $remote,
    $remote,
    'wp-content/uploads/2026/05/hero.jpg',
    'wp-content/uploads/2026/05/Hero.JPG',
);
$ignoredRecoveryExport = $plan->moveFile($remote, $local, 'exports/site.wxr', 'exports/site.wxr', [
    'ignoreExisting' => true,
]);

$partialFailure = null;
try {
    $plan->copyFile($remote, $local, 'exports/site.wxr', 'exports/site.wxr', [
        'partialUploads' => true,
        'simulatePartialTransferError' => true,
    ]);
} catch (RuntimeException $throwable) {
    $partialFailure = $throwable->getMessage();
}

return [
    'caseRepairUsedTemporaryMove' => $caseRepair['caseInsensitiveMove'],
    'canonicalUploadPath' => $remote->info('wp-content/uploads/2026/05/Hero.JPG')->path,
    'ignoreExistingKeptLocalExport' => $ignoredRecoveryExport['skipped'] && $local->get('exports/site.wxr') === $tree['exports/site.wxr'],
    'remoteRecoveryExport' => $remote->get('exports/site.wxr'),
    'partialFailure' => $partialFailure,
    'remotePathsAfterPartialCleanup' => array_map(static fn ($info) => $info->path, $remote->list()),
];
