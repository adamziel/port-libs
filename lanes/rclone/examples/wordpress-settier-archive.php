<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\LsJsonListing;
use PortLibs\Rclone\LsfListing;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$remote = new MemoryProvider();
$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
foreach ($tree as $path => $bytes) {
    $remote->put($path, $bytes, ['tier' => 'Hot']);
}

$portableArtifacts = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '- *.log',
    '- *.psd',
    '+ wp-content/uploads/**',
    '+ exports/*.wxr',
    '+ database/*.sql',
    '- *',
]);

$plan = new SyncPlan();
$updated = $plan->setTier($remote, 'Archive', $portableArtifacts);

return [
    'updatedPaths' => array_map(static fn ($info) => $info->path, $updated),
    'wxrTier' => $remote->getObjectTier('exports/site.wxr'),
    'sqlTier' => $remote->getObjectTier('database/site.sql'),
    'uploadTier' => $remote->getObjectTier('wp-content/uploads/2026/05/hero.jpg'),
    'cacheTier' => $remote->getObjectTier('wp-content/cache/page/index.html'),
    'sourceAssetTier' => $remote->getObjectTier('wp-content/uploads/2026/05/private-draft.psd'),
    'lsfTierLines' => LsfListing::lines($remote, [
        'format' => 'pT',
        'separator' => '|',
        'recurse' => true,
        'filesOnly' => true,
    ]),
    'wxrJsonTier' => LsJsonListing::stat($remote, 'exports/site.wxr')['Tier'] ?? '',
];
