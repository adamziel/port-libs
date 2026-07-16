<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\LsJsonListing;
use PortLibs\Rclone\MemoryProvider;

$provider = new MemoryProvider();

foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
    if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
        continue;
    }

    $provider->put($path, $bytes, [
        'modTime' => '2026-05-22T00:00:00Z',
        'metadata' => ['wp-backup-scope' => 'portable-export'],
    ]);
}

return LsJsonListing::items($provider, '', [
    'recurse' => true,
    'hashTypes' => ['MD5'],
    'metadata' => true,
]);
