<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeduplicateMode;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$remote->putUnchecked('exports/site.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-20T00:00:00Z',
]);
$remote->putUnchecked('exports/site.wxr', $tree['exports/site.wxr'], [
    'modTime' => '2026-05-21T00:00:00Z',
]);
$remote->putUnchecked('exports/site.wxr', '<rss><channel><title>Recovered draft</title></channel></rss>', [
    'modTime' => '2026-05-22T00:00:00Z',
]);
$remote->put('exports/site-1.wxr', '<rss><channel><title>Existing numbered export</title></channel></rss>');
$remote->put('database/site.sql', $tree['database/site.sql']);

$skip = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::SKIP);
$rename = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::RENAME);

return [
    'skipIdenticalDeleted' => array_map(
        static fn ($group) => array_map(static fn ($info) => $info->path, $group['identicalDeleted']),
        $skip['groups'],
    ),
    'renamed' => array_map(
        static fn ($group) => array_map(static fn ($info) => $info->path, $group['renamed']),
        $rename['groups'],
    ),
    'remaining' => array_map(static fn ($info) => $info->path, $remote->list()),
];
