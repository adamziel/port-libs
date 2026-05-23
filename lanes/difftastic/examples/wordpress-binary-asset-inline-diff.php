<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\InlineDiffRenderer;

$pngHeader = "\x89PNG\r\n\x1a\n";
$before = $pngHeader . str_repeat("\0", 16) . 'legacy-logo-bytes';
$after = $pngHeader . str_repeat("\0", 16) . 'modern-logo-bytes-with-retina-metadata';

echo (new InlineDiffRenderer())->renderBinaryDiff($before, $after, [
    'path' => 'wp-content/plugins/acme-card/assets/logo.png',
    'extraInfo' => 'Binary asset changed during block branding update.',
]);
