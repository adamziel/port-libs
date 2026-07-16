<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "def migrate_blocks(posts):\n"
    . "    return posts\n";
$after = "def migrate_blocks(posts: list[dict[str, int]]) -> tuple[int, list[str]]:\n"
    . "    migrated = []\n"
    . "    list = migrated\n"
    . "    self.report = None\n"
    . "    cls.enabled = False\n"
    . "    def record(post):\n"
    . "        nonlocal migrated\n"
    . "        match post.get('needsMigration'):\n"
    . "            case True:\n"
    . "                migrated.append(dict(post))\n"
    . "        print(len(migrated))\n"
    . "        return migrated\n"
    . "    return (len(migrated), [record(post) for post in posts])\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-migrator/tools/migrate_blocks.py',
    'Python',
    ['language' => 'python'],
);
