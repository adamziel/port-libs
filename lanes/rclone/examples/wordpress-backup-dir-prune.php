<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();

foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
    $source->put($path, $bytes);
}

$target->put('wp-content/uploads/2026/05/hero.jpg', 'previous published hero');
$target->put('exports/old-site.wxr', '<rss>old</rss>');
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
$archive = 'archive/2026-05-22';
$copied = $plan->copyChanged(
    $source,
    $target,
    $filter,
    backupPrefix: $archive,
    suffix: '-previous',
    suffixKeepExtension: true,
);

$error = null;
try {
    $plan->deleteDestinationOnly(
        $source,
        $target,
        $filter,
        maxDelete: 1,
        backupPrefix: $archive,
        suffix: '-previous',
        suffixKeepExtension: true,
    );
} catch (RuntimeException $throwable) {
    $error = $throwable->getMessage();
}

return [
    'copied' => array_map(static fn ($info) => $info->path, $copied),
    'archivedHero' => $target->get($archive . '/wp-content/uploads/2026/05/hero-previous.jpg'),
    'archivedDeletedExport' => $target->get($archive . '/exports/old-site-previous.wxr'),
    'deleteLimitError' => $error,
    'remaining' => array_map(static fn ($info) => $info->path, $target->list()),
];
