<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = "<?php\nreturn [\n    'render_callback' => 'acme_render_legacy_card',\n    'supports' => ['html' => false],\n];\n";
$after = "<?php\nreturn [\n    'render_callback' => 'acme_render_modern_card',\n    'supports' => ['html' => true, 'align' => ['wide']],\n];\n";

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'php',
    'byteLimit' => 80,
    'title' => 'Block render metadata byte-limit fallback diff',
]);
