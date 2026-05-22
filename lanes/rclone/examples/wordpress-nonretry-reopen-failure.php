<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\NoLowLevelRetryException;
use PortLibs\Rclone\ReOpenReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$provider = new MemoryProvider();

$provider->put('exports/site.wxr', $tree['exports/site.wxr'], [
    'readError' => new NoLowLevelRetryException('provider says this WXR range cannot be retried'),
    'readBreaks' => [5],
]);

$reader = new ReOpenReader($provider, 'exports/site.wxr', 10);
$partial = $reader->read(8);
$error = null;

try {
    $reader->read(1);
} catch (NoLowLevelRetryException $throwable) {
    $error = $throwable->getMessage();
}

return [
    'partialBytes' => $partial,
    'error' => $error,
    'reopenAttempts' => count($provider->openLog()),
    'retried' => count($provider->openLog()) > 1,
];
