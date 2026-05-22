<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$source = new MemoryProvider();
$target = new MemoryProvider();
$warmMirror = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

foreach ($tree as $path => $bytes) {
    $source->put($path, $bytes);
    if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
        continue;
    }
    $warmMirror->put($path, $bytes);
}

$target->put('wp-content/uploads/2026/05/hero.jpg', 'previous hero bytes');
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

$archive = 'archive/2026-05-22';
$copied = (new SyncPlan())->copyChanged(
    $source,
    $target,
    $filter,
    backupPrefix: $archive,
    copyDest: [$warmMirror],
);

return [
    'copiedFromWarmMirrorOrSource' => array_map(static fn ($info) => $info->path, $copied),
    'archivedHero' => $target->get($archive . '/wp-content/uploads/2026/05/hero.jpg'),
    'currentHero' => $target->get('wp-content/uploads/2026/05/hero.jpg'),
    'databaseRestored' => $target->get('database/site.sql'),
    'excludedCacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
