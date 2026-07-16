<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\HtmlDiffRenderer;

$line = static fn (int $index): string => 'int acme_asset_' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '() { return ' . $index . '; }';
$beforeLines = array_map($line, range(0, 1600));
$afterLines = $beforeLines;
$afterLines[256] = 'int acme_asset_0256() { return 512; }';
$afterLines[1200] = 'int acme_asset_1200() { return 1208; }';
array_splice($afterLines, 257, 0, ['int acme_generated_view_asset() { return 257; }']);

echo (new HtmlDiffRenderer())->renderSyntaxListDiff(
    implode("\n", $beforeLines) . "\n",
    implode("\n", $afterLines) . "\n",
    [
        'language' => 'cpp',
        'byteLimit' => 1024,
        'title' => 'Generated asset C++ byte-limit fallback diff',
    ],
);
