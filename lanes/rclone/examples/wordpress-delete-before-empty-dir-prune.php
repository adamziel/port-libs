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
$target->mkdir('exports/retired');
$target->put('exports/retired/old-site.wxr', '<rss>old export</rss>');
$target->mkdir('wp-content/uploads/2024');
$target->mkdir('wp-content/uploads/2024/01');
$target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
$target->mkdir('wp-content/cache');
$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ exports/retired/**',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$result = $plan->syncWithDeleteMode(
    $source,
    $target,
    $filter,
    deleteMode: DeleteMode::BEFORE,
    backupPrefix: 'archive/2026-05-22',
    suffix: '-previous',
    suffixKeepExtension: true,
);

$directoryExists = static function (MemoryProvider $provider, string $path): bool {
    try {
        $provider->directoryInfo($path);

        return true;
    } catch (RuntimeException) {
        return false;
    }
};

return [
    'deleted' => array_map(static fn ($info): string => $info->path, $result['deleted']),
    'copied' => array_map(static fn ($info): string => $info->path, $result['copied']),
    'prunedDirectories' => array_map(static fn ($info): string => $info->path, $result['deletePassPrunedDirectories']),
    'archivedOldExportBytes' => $target->get('archive/2026-05-22/exports/retired/old-site-previous.wxr'),
    'archivedObsoleteUploadBytes' => $target->get('archive/2026-05-22/wp-content/uploads/2024/01/obsolete-previous.jpg'),
    'staleExportDirectoryExists' => $directoryExists($target, 'exports/retired'),
    'staleUploadDirectoryExists' => $directoryExists($target, 'wp-content/uploads/2024'),
    'backupArchiveDirectoryExists' => $directoryExists($target, 'archive/2026-05-22/exports/retired'),
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
