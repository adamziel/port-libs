<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$local = new MemoryProvider();
$remote = new MemoryProvider();
$plan = new SyncPlan();

$local->put('exports/site-small.wxr', '<rss>small</rss>');
$local->put('exports/site-large.wxr', '<rss>' . str_repeat('post', 24) . '</rss>');

$small = $plan->copyFile($remote, $local, 'restore/site-small.wxr', 'exports/site-small.wxr', [
    'maxTransfer' => 64,
    'cutoffMode' => 'hard',
]);

$hardError = null;
try {
    $plan->copyFile($remote, $local, 'restore/site-large-hard.wxr', 'exports/site-large.wxr', [
        'maxTransfer' => 64,
        'cutoffMode' => 'hard',
    ]);
} catch (RuntimeException $throwable) {
    $hardError = $throwable->getMessage();
}

$cautiousError = null;
try {
    $plan->copyFile($remote, $local, 'restore/site-large-cautious.wxr', 'exports/site-large.wxr', [
        'maxTransfer' => 64,
        'cutoffMode' => 'cautious',
    ]);
} catch (RuntimeException $throwable) {
    $cautiousError = $throwable->getMessage();
}

$soft = $plan->copyFile($remote, $local, 'restore/site-large-soft.wxr', 'exports/site-large.wxr', [
    'maxTransfer' => 64,
    'cutoffMode' => 'soft',
]);

$softAfterLimitError = null;
try {
    $plan->copyFile($remote, $local, 'restore/site-small-after-limit.wxr', 'exports/site-small.wxr', [
        'maxTransfer' => 64,
        'cutoffMode' => 'soft',
        'bytesTransferredSoFar' => 64,
    ]);
} catch (RuntimeException $throwable) {
    $softAfterLimitError = $throwable->getMessage();
}

return [
    'smallCopiedPath' => $small['copied']->path,
    'hardError' => $hardError,
    'cautiousError' => $cautiousError,
    'softCopiedPath' => $soft['copied']->path,
    'softAfterLimitError' => $softAfterLimitError,
    'hardDestinationCreated' => $remote->pathExists('restore/site-large-hard.wxr'),
    'cautiousDestinationCreated' => $remote->pathExists('restore/site-large-cautious.wxr'),
    'softBytes' => $remote->get('restore/site-large-soft.wxr'),
    'stagedLargePreserved' => $local->get('exports/site-large.wxr'),
];
