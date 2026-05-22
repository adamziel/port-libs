<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-interactivity-script-before.html');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-interactivity-script-after.html');

echo (new HtmlDiffRenderer())->renderSyntaxListDiff($before, $after, [
    'language' => 'html',
    'title' => 'Interactivity script sub-language diff',
]);
