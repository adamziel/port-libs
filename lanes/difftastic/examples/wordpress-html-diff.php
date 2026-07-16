<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$renderer = new HtmlDiffRenderer();

$styleBefore = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-before.php');
$styleAfter = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-after.php');
$themeBefore = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-json-before.json');
$themeAfter = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-theme-json-after.json');

echo '<!doctype html><meta charset="utf-8"><title>WordPress Difftastic HTML Slice</title>';
echo '<style>body{font-family:system-ui,sans-serif;margin:2rem}pre{white-space:pre-wrap}.dft-add,ins{background:#d1fae5}.dft-del,del{background:#fee2e2}.dft-path{margin-right:1rem;color:#475569}</style>';
echo $renderer->renderWordDiff($styleBefore, $styleAfter, [
    'splitNumbers' => true,
    'title' => 'Block style subword diff',
]);
echo $renderer->renderSyntaxListDiff($themeBefore, $themeAfter, [
    'title' => 'theme.json palette syntax-list diff',
]);
