<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$source->put('exports/site.wxr', $tree['exports/site.wxr']);
$source->putUnchecked('exports/site.wxr', '<rss>stale duplicate provider entry</rss>');
$source->put('database/site.sql', $tree['database/site.sql']);

$target->put('exports/site.wxr', $tree['exports/site.wxr']);

$filter = FilterRuleSet::fromRules([
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$changedBeforeCopy = $plan->changedPaths($source, $target, $filter);
$copied = $plan->copyChanged($source, $target, $filter);

return [
    'changedBeforeCopy' => $changedBeforeCopy,
    'copied' => array_map(static fn ($info): string => $info->path, $copied),
    'targetExportBytes' => $target->get('exports/site.wxr'),
    'targetDatabaseBytes' => $target->get('database/site.sql'),
];
