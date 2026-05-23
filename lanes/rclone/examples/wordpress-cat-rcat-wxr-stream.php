<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider();
$plan = new SyncPlan();

$uploadStats = null;
$plan->rcat(
    $provider,
    'exports/site.wxr',
    '<rss version="2.0"></rss>',
    '2026-05-23T00:00:00Z',
    ['wp-export' => 'full-site'],
    streamingUploadCutoff: 8,
    stats: $uploadStats,
);
$provider->put('database/site.sql', 'CREATE TABLE wp_posts;');
$provider->put('wp-content/cache/page.html', '<html>cached</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '+ database/**',
    '+ exports/**',
    '- *',
]);

$catStats = null;
$catManifest = $plan->cat($provider, separator: "\n", filter: $filter, stats: $catStats);
$tailExport = $plan->cat($provider, 'exports/site.wxr', offset: -6);

return [
    'uploadedExport' => $provider->get('exports/site.wxr'),
    'uploadedMetadata' => $provider->info('exports/site.wxr')->metadata,
    'uploadStats' => $uploadStats,
    'catManifest' => $catManifest,
    'catStats' => $catStats,
    'tailExport' => $tailExport,
];
