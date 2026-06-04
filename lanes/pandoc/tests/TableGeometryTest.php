<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$buildSpannedTableDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Migration review grid',
            'alignments' => ['left', 'right', 'center'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope', 'colspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
            ]),
        ]),
    ]);
};

return [
    'lays out pandoc table spans by visual columns for writer handoff' => static function (TestRunner $t) use ($buildSpannedTableDocument): void {
        $table = $buildSpannedTableDocument()->children[0];
        $headRows = $table->children[0]->children;
        $bodyRows = $table->children[1]->children;
        $headLayout = TableGeometry::layoutRows($headRows, 3);
        $bodyLayout = TableGeometry::layoutRows($bodyRows, 3);

        $t->same(3, TableGeometry::columnCountForRows([...$headRows, ...$bodyRows]));
        $t->same(['left', 'right', 'center'], TableGeometry::alignments($table, 3));
        $t->same([0, 2], array_map(static fn (array $cell): int => $cell['column'], $headLayout[0]['cells']));
        $t->same([2, 1], array_map(static fn (array $cell): int => $cell['colspan'], $headLayout[0]['cells']));
        $t->same([0, 1, 2], array_map(static fn (array $cell): int => $cell['column'], $bodyLayout[0]['cells']));
        $t->same([1, 2], array_map(static fn (array $cell): int => $cell['column'], $bodyLayout[1]['cells']));
        $t->same(2, $bodyLayout[0]['cells'][0]['rowspan']);
        $t->same('center', TableGeometry::cellAlignment($table, 2, $headLayout[0]['cells'][1]['node']));
        $t->same('right', TableGeometry::cellAlignment($table, 1, $bodyLayout[1]['cells'][0]['node']));
        $t->same('center', TableGeometry::cellAlignment($table, 2, $bodyLayout[1]['cells'][1]['node']));
    },
    'renders wordpress and markdown tables with span advanced alignments' => static function (TestRunner $t) use ($buildSpannedTableDocument): void {
        $document = $buildSpannedTableDocument();
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->contains('<th colspan="2" style="text-align:left">Scope</th><th style="text-align:center">Status</th>', $blocks);
        $t->contains('<tr><td rowspan="2" style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Migration review grid</figcaption>', $blocks);
        $t->contains('| Scope |     | Status |', $markdown);
        $t->contains('|:----|--:|:----:|', $markdown);
        $t->contains('| Posts |  42 | Ready  |', $markdown);
        $t->contains('|       |   7 | Review |', $markdown);
        $t->contains(': Migration review grid', $markdown);
    },
];
