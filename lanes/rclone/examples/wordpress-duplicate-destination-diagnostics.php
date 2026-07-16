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
$source->put('database/site.sql', $tree['database/site.sql']);

$target->put('exports/site.wxr', $tree['exports/site.wxr']);
$target->putUnchecked('exports/site.wxr', '<rss>interrupted stale duplicate</rss>');
$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$diagnostics = $plan->matchListingDiagnostics($source, $target, $filter);
$copied = $plan->copyChanged($source, $target, $filter);

return [
    'matches' => array_map(static fn (array $pair): string => $pair['source']->path, $diagnostics['matches']),
    'sourceOnly' => array_map(static fn ($info): string => $info->path, $diagnostics['sourceOnly']),
    'destinationOnly' => array_map(static fn ($info): string => $info->path, $diagnostics['destinationOnly']),
    'duplicateDestinationPaths' => array_map(static fn (array $duplicate): string => $duplicate['path'], $diagnostics['duplicateDestinations']),
    'duplicateDestinationMessages' => array_map(static fn (array $duplicate): string => $duplicate['message'], $diagnostics['duplicateDestinations']),
    'ignoredDuplicateHashes' => array_map(static fn (array $duplicate): string => $duplicate['ignored']->sha256, $diagnostics['duplicateDestinations']),
    'copied' => array_map(static fn ($info): string => $info->path, $copied),
    'targetExportBytes' => $target->get('exports/site.wxr'),
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
