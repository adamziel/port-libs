<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeduplicateMode;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$remote->put('exports/site-copy-a.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-20T00:00:00Z',
]);
$remote->put('exports/site-copy-b.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-22T00:00:00Z',
]);
$remote->put('exports/site-copy-c.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-21T00:00:00Z',
]);
$remote->put('database/site.sql', $tree['database/site.sql'], [
    'modTime' => '2026-05-22T01:00:00Z',
]);

$result = (new SyncPlan())->deduplicateByHash($remote, DeduplicateMode::NEWEST);

return [
    'hashType' => $result['hashType'],
    'kept' => array_map(static fn ($group) => $group['kept']?->path, $result['groups']),
    'deleted' => array_map(
        static fn ($group) => array_map(static fn ($info) => $info->path, $group['deleted']),
        $result['groups'],
    ),
    'remaining' => array_map(static fn ($info) => $info->path, $remote->list()),
];
