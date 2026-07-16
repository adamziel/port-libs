<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "export { register } from './register';\n";
$after = "BlockRegistry.configure(WP_BLOCK_API_VERSION);\nexport { register } from './register';\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/block-registry.js',
    'JavaScript',
    ['language' => 'javascript'],
);
