<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$sourcePacket = new AstNode('document', [
    'meta' => [
        'title' => 'Native Import Review',
        'batch' => 'native-reader-42',
        'ready' => true,
    ],
], [
    new AstNode('heading', ['level' => 2, 'id' => 'native-import-review'], [
        $text('Native Import Review'),
    ]),
    new AstNode('paragraph', [], [
        $text('Review packet from '),
        new AstNode('link', ['url' => 'https://example.test/archive/native-42', 'title' => 'Native source'], [
            $text('legacy archive'),
        ]),
        $text(' before publishing.'),
    ]),
    new AstNode('table', [
        'caption' => 'Import checks',
        'shortCaption' => 'checks',
        'alignments' => ['left', 'right'],
        'widths' => [0.55, 0.45],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell([$text('Check')]),
                $cell([$text('Status')]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            $row([
                $cell([$text('Media captions')]),
                $cell([$text('Needs review')]),
            ]),
            $row([
                $cell([$text('Source links')]),
                $cell([$text('Ready')]),
            ]),
        ]),
    ]),
]);

$nativePacket = (new NativeWriter(['standalone' => true]))->write($sourcePacket);
$document = (new NativeReader())->read($nativePacket);

echo (new WordPressBlockWriter())->write($document) . "\n";
