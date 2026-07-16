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
$remote->put('database/site.sql', $tree['database/site.sql']);

$result = (new SyncPlan())->deduplicateByName(
    $remote,
    DeduplicateMode::INTERACTIVE,
    interactiveChoice: static fn (array $group): array => [
        'action' => 'keep',
        'keep' => 2,
    ],
);

return [
    'action' => $result['groups'][0]['action'],
    'identicalDeleted' => array_map(static fn ($info) => $info->path, $result['groups'][0]['identicalDeleted']),
    'deletedConflicts' => array_map(static fn ($info) => $info->path, $result['groups'][0]['deleted']),
    'keptBody' => $remote->get('exports/site.wxr'),
    'remaining' => array_map(static fn ($info) => $info->path, $remote->list()),
];
