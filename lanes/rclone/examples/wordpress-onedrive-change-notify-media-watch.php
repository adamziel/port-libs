<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDriveDeltaCursor;

$queuedImports = [];
$summary = OneDriveDeltaCursor::runChangeNotify(
    ['@odata.deltaLink' => 'https://graph.example/root/delta?token=watch-seed'],
    [
        'watch-seed' => [
            '@odata.deltaLink' => 'https://graph.example/root/delta?token=after-scan',
            'value' => [
                [
                    'name' => 'hero.jpg',
                    'parentReference' => ['id' => 'may', 'path' => '/drives/site/root:/wp-content/uploads/2026/05'],
                    'file' => ['mimeType' => 'image/jpeg'],
                ],
                [
                    'name' => 'site.wxr',
                    'parentReference' => ['id' => 'exports', 'path' => '/drives/site/root:/wp-content/exports'],
                    'file' => ['mimeType' => 'application/rss+xml'],
                ],
                [
                    'name' => 'bad-cache-entry.php',
                    'parentReference' => ['id' => 'uploads', 'path' => '/drives/site/root/wp-content/uploads'],
                    'file' => ['mimeType' => 'text/x-php'],
                ],
                [
                    'name' => 'other-site.sql',
                    'parentReference' => ['id' => 'other', 'path' => '/drives/site/root:/other-site'],
                    'file' => ['mimeType' => 'application/sql'],
                ],
            ],
        ],
    ],
    [0, 15, null],
    'wp-content',
    'site-drive',
    static function (string $path, string $type) use (&$queuedImports): null {
        if ($type === OneDriveDeltaCursor::ENTRY_OBJECT) {
            $queuedImports[] = $path;
        }

        return null;
    },
);

return [
    'startToken' => $summary['startToken'],
    'finalToken' => $summary['finalToken'],
    'queuedImports' => $queuedImports,
    'log' => $summary['log'],
    'closed' => $summary['stopped'],
];
