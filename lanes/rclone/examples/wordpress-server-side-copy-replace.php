<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
$plan = new SyncPlan();

$remote->put('staging/site.wxr', '<rss>fresh export</rss>');
$remote->put('exports/site.wxr', '<rss>previous export</rss>');

$replace = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'temporarySuffix' => '.wpcopy',
    'precreateDestination' => true,
]);

$caseFoldError = null;
try {
    $plan->serverSideCopyReplace($remote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
        'guardCaseFoldSameRemote' => true,
    ]);
} catch (RuntimeException $throwable) {
    $caseFoldError = $throwable->getMessage();
}

$missingDestinationError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/failed-copy.wxr', [
        'precreateDestination' => true,
        'simulateCopyError' => 'provider copy failed',
    ]);
} catch (RuntimeException $throwable) {
    $missingDestinationError = $throwable->getMessage();
}

return [
    'savedOldExportPath' => $replace['savedPath'],
    'precreatedDestinationPath' => $replace['precreatedPath'],
    'freshExport' => $remote->get('exports/site.wxr'),
    'caseFoldGuard' => $caseFoldError,
    'failedPrecreatedCopyError' => $missingDestinationError,
    'failedCopyCreatedObject' => array_map(static fn ($info) => $info->path, $remote->list('exports/failed-copy.wxr')),
];
