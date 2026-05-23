<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$uploadPath = 'wp-content/uploads/2026/05';

$source = new MemoryProvider();
$source->mkdir($uploadPath, ['id' => 'source-month']);
$source->put($uploadPath, 'directory marker bytes');

$target = new MemoryProvider();
$target->mkdir($uploadPath, ['id' => 'published-month']);
$target->mkdirUnchecked($uploadPath, ['id' => 'interrupted-restore-month']);
$target->put($uploadPath, 'directory marker bytes');
$target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

$filter = FilterRuleSet::fromRules([
    '- wp-content/cache/**',
    '+ wp-content/uploads/2026/05',
    '- *',
]);

$diagnostics = (new SyncPlan())->matchListingDiagnostics($source, $target, $filter, includeDirectories: true);

$uploadPathMatches = [];
foreach ($diagnostics['matches'] as $pair) {
    if ($pair['source']->path !== $uploadPath) {
        continue;
    }

    $uploadPathMatches[] = (ListDirectory::isDirectory($pair['source']) ? 'directory:' : 'object:')
        . $pair['source']->path;
}

return [
    'uploadPathMatches' => $uploadPathMatches,
    'duplicateDestinationPaths' => array_map(
        static fn (array $duplicate): string => $duplicate['path'],
        $diagnostics['duplicateDestinations'],
    ),
    'duplicateDestinationTypes' => array_map(
        static fn (array $duplicate): string => $duplicate['type'],
        $diagnostics['duplicateDestinations'],
    ),
    'duplicateDestinationMessages' => array_map(
        static fn (array $duplicate): string => $duplicate['message'],
        $diagnostics['duplicateDestinations'],
    ),
    'keptDirectoryIds' => array_map(
        static fn (array $duplicate): ?string => $duplicate['kept']->id,
        $diagnostics['duplicateDestinations'],
    ),
    'ignoredDirectoryIds' => array_map(
        static fn (array $duplicate): ?string => $duplicate['ignored']->id,
        $diagnostics['duplicateDestinations'],
    ),
    'markerObjectHash' => $target->info($uploadPath)->sha256,
    'cacheLeftUntouched' => $target->get('wp-content/cache/orphan.html'),
];
