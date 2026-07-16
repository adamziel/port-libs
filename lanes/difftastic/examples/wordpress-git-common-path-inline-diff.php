<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\InlineDiffRenderer;

$before = <<<'JSON'
{
  "apiVersion": 3,
  "name": "acme/card",
  "title": "Legacy Card",
  "supports": {
    "html": false
  }
}
JSON;

$after = <<<'JSON'
{
  "apiVersion": 3,
  "name": "acme/card",
  "title": "Modern Card",
  "viewScriptModule": "file:./view.js",
  "supports": {
    "html": true
  }
}
JSON;

echo (new InlineDiffRenderer())->renderPathArgumentsTextDiff($before, $after, [
    '/srv/releases/old/wp-content/plugins/acme-card/block.json',
    '/srv/releases/new/wp-content/plugins/acme-card/block.json',
], [
    'language' => 'json',
    'contextLines' => 1,
]);
