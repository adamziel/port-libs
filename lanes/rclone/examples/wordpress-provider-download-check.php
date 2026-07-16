<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider(false, new HashSet());
$restore = new MemoryProvider(false, new HashSet());
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

foreach ($tree as $path => $bytes) {
    if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
        continue;
    }

    $source->put($path, $bytes);
    $restore->put($path, $bytes);
}

$restore->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');
$restore->put('database/site.sql', $tree['database/site.sql'], [
    'readError' => 'restored database stream interrupted',
    'readErrorAfterBytes' => 10,
]);

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$result = (new SyncPlan())->checkDownload($source, $restore, false, $filter);

return [
    'combined' => $result->combinedLines(),
    'errors' => $result->errorMessages,
    'matches' => $result->matches,
    'differences' => $result->differences(),
    'errorCount' => $result->errors(),
];
