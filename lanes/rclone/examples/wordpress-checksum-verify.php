<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ChecksumFile;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\MemoryProvider;

$provider = new MemoryProvider();

foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
    if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
        continue;
    }

    $provider->put($path, $bytes);
}

$manifest = implode("\n", [
    strtoupper(hash('md5', 'new image bytes')) . '  WP-CONTENT/UPLOADS/2026/05/HERO.JPG',
    hash('md5', 'generated webp bytes') . '  wp-content/uploads/2026/05/hero.webp',
    hash('md5', '<rss version="2.0"></rss>') . '  exports/site.wxr',
    hash('md5', 'insert into wp_posts values (...)') . '  database/site.sql',
]);

return ChecksumFile::check($provider, $manifest, HashType::MD5, false, null, true)->combinedLines();
