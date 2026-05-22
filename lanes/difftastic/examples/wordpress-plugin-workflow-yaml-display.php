<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-before.yml');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-workflow-after.yml');

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/.github/workflows/release.yml',
    'YAML',
    ['language' => 'yaml'],
);
