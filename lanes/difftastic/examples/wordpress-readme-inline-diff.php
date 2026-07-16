<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\InlineDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-before.txt');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-readme-footer-after.txt');

echo (new InlineDiffRenderer())->renderTextDiff($before, $after, [
    'path' => 'wp-content/plugins/acme-review-tools/readme.txt',
    'language' => 'text',
    'contextLines' => 1,
]);
