<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$path = 'exports/' . 'site-export-' . str_repeat('segment-', 14) . 'final.wxr';
$remote = new MemoryProvider(serverSideMove: true);
$plan = new SyncPlan();

$remote->put($path, '<rss>previous long export</rss>');
$success = $plan->removeExisting($remote, $path, 'server side copy', '.wpbackup');
$remote->put($path, '<rss>fresh long export</rss>');
$successError = null;
$success['cleanup']($successError);
$freshAfterSuccess = $remote->get($path);

$remote->put($path, '<rss>previous long export</rss>');
$failure = $plan->removeExisting($remote, $path, 'server side copy', '.wpbackup');
$copyError = new RuntimeException('provider copy failed');
$failure['cleanup']($copyError);

return [
    'savedPathWasTruncated' => strlen($success['savedPath'] ?? '') === strlen($path),
    'newExportAfterSuccessfulCopy' => $freshAfterSuccess,
    'successfulCleanupError' => $successError?->getMessage(),
    'failedCopyErrorPreserved' => $copyError->getMessage(),
    'restoredAfterFailure' => $remote->get($path),
];
