<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);

$document = new AstNode('document', [
    'meta' => [
        'title' => 'WordPress Native Table Packet',
        'batch' => 'wp-native-table-42',
        'ready' => true,
    ],
], [
    new AstNode('heading', ['level' => 2, 'id' => 'native-table-packet', 'classes' => ['handoff']], [
        $text('Native Table Packet'),
    ]),
    new AstNode('table', [
        'id' => 'migration-review-table',
        'classes' => ['review-table'],
        'attributes' => ['source' => 'batch-42'],
        'caption' => 'Migration review table',
        'shortCaption' => 'review table',
        'alignments' => ['left', 'right'],
        'widths' => [0.4, 0.6],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell([$text('Field')], ['header' => true]),
                $cell([$text('Review note')], ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            $row([
                $cell([$text('All imports')], ['colspan' => 2, 'align' => 'center']),
            ]),
            $row([
                $cell([$text('Posts')], ['header' => true]),
                $cell([
                    new AstNode('paragraph', [], [$text('Confirm block conversion before publish.')]),
                    new AstNode('bullet_list', [], [
                        new AstNode('list_item', [], [$text('media captions')]),
                        new AstNode('list_item', [], [$text('source links')]),
                    ]),
                ]),
            ]),
        ]),
        new AstNode('table_foot', [], [
            $row([
                $cell([$text('Total')]),
                $cell([$text('49')]),
            ]),
        ]),
    ]),
]);

echo (new NativeWriter(['standalone' => true]))->write($document) . "\n";
