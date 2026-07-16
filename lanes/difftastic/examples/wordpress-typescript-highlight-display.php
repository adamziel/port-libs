<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "export { save } from './save';\n";
$after = "type BlockAttributes = { title: string; columns: number };\n"
    . "const supports: Record<string, boolean> = {};\n"
    . "export { save } from './save';\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/index.ts',
    'TypeScript',
    ['language' => 'typescript'],
);
