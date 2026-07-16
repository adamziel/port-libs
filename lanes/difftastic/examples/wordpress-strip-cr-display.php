<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "<?php\r\n/**\r\n * Render the card block.\r\n */\r\nfunction acme_render_card(): string {\r\n    return '<section>Card</section>';\r\n}\r\n";
$after = "<?php\n/**\n * Render the card block.\n */\nfunction acme_render_card(): string {\n    return '<section>Card</section>';\n}\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/render.php',
    'PHP',
    ['language' => 'php'],
);
