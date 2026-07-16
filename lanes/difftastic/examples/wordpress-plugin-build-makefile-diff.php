<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-build-makefile-before.mk');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-plugin-build-makefile-after.mk');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'makefile',
    'title' => 'Plugin build Makefile text-atom diff',
]);
