<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\LsJsonListing;
use PortLibs\Rclone\MemoryProvider;

$provider = new MemoryProvider(true);

$provider->put('wp-content/uploads/2026/05/Hero.JPG', 'new image bytes', [
    'modTime' => '2026-05-22T00:00:00Z',
]);
$provider->put('database/site.sql', 'insert into wp_posts values (...)', [
    'modTime' => '2026-05-22T00:00:00Z',
]);

return [
    'uploadDirectory' => LsJsonListing::stat($provider, 'WP-CONTENT/UPLOADS'),
    'uploadObject' => LsJsonListing::stat($provider, 'wp-content/uploads/2026/05/hero.jpg', [
        'hashTypes' => ['MD5'],
    ]),
    'databaseDirectory' => LsJsonListing::stat($provider, 'DATABASE', [
        'dirsOnly' => true,
    ]),
];
