<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('exports/site.wxr', '<rss>stale published export</rss>');
$target->put('exports/old-site.wxr', '<rss>old export</rss>');
$target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$stats = null;
$error = null;
try {
    $plan->syncWithDeleteMode(
        $source,
        $target,
        $filter,
        deleteMode: DeleteMode::BEFORE,
        noTraverse: true,
        noTraverseStats: $stats,
        backupPrefix: 'archive/2026-05-22',
        suffix: '-previous',
        suffixKeepExtension: true,
        maxDelete: 1,
    );
} catch (RuntimeException $throwable) {
    $error = $throwable->getMessage();
}

return [
    'error' => $error,
    'copyPassRan' => $stats !== null,
    'archivedOldExportBytes' => $target->get('archive/2026-05-22/exports/old-site-previous.wxr'),
    'obsoleteUploadStillPresent' => $target->get('wp-content/uploads/2024/01/obsolete.jpg'),
    'publishedExportBytes' => $target->get('exports/site.wxr'),
    'databaseCopied' => $target->pathExists('database/site.sql'),
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
