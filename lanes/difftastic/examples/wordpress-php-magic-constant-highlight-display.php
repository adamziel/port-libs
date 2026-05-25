<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "<?php\nrequire_once plugin_dir_path(__FILE__) . 'includes/legacy.php';\n";
$after = "<?php\n"
    . "require_once plugin_dir_path(__FILE__) . 'includes/blocks.php';\n"
    . "require_once __DIR__ . '/includes/render.php';\n"
    . "\$manifest = __FILE__;\n"
    . "\$root = plugin_dir_path(__FILE__);\n"
    . "add_action('init', [Acme_Card\\Blocks::class, 'register']);\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/acme-card.php',
    'PHP',
    ['language' => 'php'],
);
