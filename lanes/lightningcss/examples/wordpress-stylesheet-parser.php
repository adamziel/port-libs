<?php

declare(strict_types=1);

use PortLibs\LightningCSS\StylesheetParser;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-theme.css');
$rules = (new StylesheetParser())->parse($css);

echo json_encode([
    'rules' => count($rules),
    'firstType' => $rules[0]->type ?? null,
    'firstSelectors' => $rules[0]->selectors ?? [],
    'firstDeclarationCount' => count($rules[0]->declarations ?? []),
], JSON_PRETTY_PRINT) . "\n";
