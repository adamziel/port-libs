<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$fixtures = dirname(__DIR__) . '/fixtures';
$before = (string) file_get_contents($fixtures . '/wordpress-tsx-tag-highlight-before.tsx');
$after = (string) file_get_contents($fixtures . '/wordpress-tsx-tag-highlight-after.tsx');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/edit.tsx',
    'TSX',
    ['language' => 'tsx'],
);
