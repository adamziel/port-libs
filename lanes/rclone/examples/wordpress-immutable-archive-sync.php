<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();

$source->put('exports/site-2026-05-22.wxr', '<rss version="2.0"></rss>');
$source->put('database/site-2026-05-22.sql', 'insert into wp_posts values (...)');
$target->put('exports/site-2026-05-22.wxr', '<rss version="2.0"></rss>');

$plan = new SyncPlan();
$created = $plan->copyChanged($source, $target, immutable: true);

$source->put('exports/site-2026-05-22.wxr', '<rss>rewritten archive</rss>');
$immutableError = null;
try {
    $plan->copyChanged($source, $target, immutable: true);
} catch (RuntimeException $throwable) {
    $immutableError = $throwable->getMessage();
}

return [
    'createdArtifacts' => array_map(static fn ($info) => $info->path, $created),
    'immutableError' => $immutableError,
    'preservedWxr' => $target->get('exports/site-2026-05-22.wxr'),
    'createdSql' => $target->get('database/site-2026-05-22.sql'),
];
