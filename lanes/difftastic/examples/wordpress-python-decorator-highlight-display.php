<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "def migrate_post(post):\n    return post\n";
$after = "@CacheWarmup\n"
    . "@staticmethod\n"
    . "def migrate_post(post):\n"
    . "    return MigrationRunner(post)\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-migrator/tools/migrate_posts.py',
    'Python',
    ['language' => 'python'],
);
