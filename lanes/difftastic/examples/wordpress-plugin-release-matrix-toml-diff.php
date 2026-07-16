<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-release-matrix-before.toml');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-release-matrix-after.toml');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'toml',
    'title' => 'WordPress plugin release matrix TOML diff',
]);
