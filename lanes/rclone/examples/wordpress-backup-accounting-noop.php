<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$local = new MemoryProvider();
$remote = new MemoryProvider();
$plan = new SyncPlan();

$local->put('staging/uploads/hero.jpg', 'fresh hero bytes');
$remote->put('publish/uploads/hero.jpg', 'published hero bytes', [
    'modTime' => '2026-05-22T10:00:00Z',
    'metadata' => ['wp-artifact' => 'published-media'],
]);
$remote->put('archive/publish/uploads/hero-previous.jpg', 'stale hero archive');

$backup = $plan->copyFile(
    $remote,
    $local,
    'publish/uploads/hero.jpg',
    'staging/uploads/hero.jpg',
    [
        'backupPrefix' => 'archive',
        'suffix' => '-previous',
        'suffixKeepExtension' => true,
    ],
);

$noop = $plan->copyFile(
    $remote,
    $remote,
    'publish/uploads/hero.jpg',
    'publish/uploads/hero.jpg',
);

return [
    'backupAccounting' => $backup['backupAccounting'],
    'backupLogMessages' => array_map(static fn (array $event): string => $event['message'], $backup['logEvents']),
    'backupLoggerTypes' => array_map(static fn (array $event): string => $event['type'], $backup['loggerEvents']),
    'noopSkipped' => $noop['skipped'],
    'noopAccounting' => $noop['accounting'],
    'noopLogMessage' => $noop['logEvents'][0]['message'] ?? null,
    'noopLoggerType' => $noop['loggerEvents'][0]['type'] ?? null,
    'publishedBytes' => $remote->get('publish/uploads/hero.jpg'),
];
