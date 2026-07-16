<?php

declare(strict_types=1);

use PortLibs\Syncthing\PullJobQueue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$queue = new PullJobQueue();
$queue->push('wp-content/uploads/2026/gallery.zip', 48_000_000, 1_780_100_000_000_000_000);
$queue->push('wp-content/uploads/2026/hero.jpg', 900_000, 1_780_100_100_000_000_000);
$queue->push('wp-content/uploads/2026/private-export.zip', 12_000_000, 1_780_100_200_000_000_000);

$queue->bringToFront('wp-content/uploads/2026/private-export.zip');
$active = $queue->pop();
$firstPage = $queue->jobs(1, 2);

if ($active !== null) {
    $queue->done($active);
}

echo json_encode([
    'activePull' => $active,
    'firstPage' => $firstPage,
    'afterCompletion' => $queue->jobs(1, 10),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
