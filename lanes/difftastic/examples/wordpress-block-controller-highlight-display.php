<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = "export { save } from './save';\n";
$after = "interface BlockVariationController {\n    mount(): void;\n}\nconst controller: BlockVariationController = new BlockVariationController();\nexport { save } from './save';\n";

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/variation-controller.ts',
    'TypeScript',
    ['language' => 'typescript'],
);
