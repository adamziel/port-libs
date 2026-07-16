<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
$after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";

$result = (new DiffCommandRunner())->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/render-metadata.php',
    'PHP',
    [
        'language' => 'php',
        'exitCode' => true,
    ],
    [
        'DFT_BYTE_LIMIT' => '80',
        'DFT_GRAPH_LIMIT' => '3000000',
        'DFT_PARSE_ERROR_LIMIT' => '0',
    ],
);

echo $result['stdout'];
echo 'exit_code=' . $result['exitCode'] . "\n";
