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

$buildColspecTableDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Import queue with reserved audit column',
            'alignments' => ['left', 'center', 'right', 'left'],
            'widths' => [0.2, 0.25, 0.25, 0.3],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
                    new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildRowHeadColumnDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Lane coverage review',
            'alignments' => ['left', 'left', 'right', 'center'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Lane'], [new AstNode('text', ['text' => 'Lane'])]),
                    new AstNode('table_cell', ['text' => 'Slice'], [new AstNode('text', ['text' => 'Slice'])]),
                    new AstNode('table_cell', ['text' => 'Checks'], [new AstNode('text', ['text' => 'Checks'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', ['rowHeadColumns' => 2], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Pandoc', 'rowspan' => 2], [new AstNode('text', ['text' => 'Pandoc'])]),
                    new AstNode('table_cell', ['text' => 'Table geometry'], [new AstNode('text', ['text' => 'Table geometry'])]),
                    new AstNode('table_cell', ['text' => '4'], [new AstNode('text', ['text' => '4'])]),
                    new AstNode('table_cell', ['text' => 'Mapped'], [new AstNode('text', ['text' => 'Mapped'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'DOCX handoff'], [new AstNode('text', ['text' => 'DOCX handoff'])]),
                    new AstNode('table_cell', ['text' => '10'], [new AstNode('text', ['text' => '10'])]),
                    new AstNode('table_cell', ['text' => 'Accepted'], [new AstNode('text', ['text' => 'Accepted'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSectionScopedRowspanDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Section boundary review',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope', 'rowspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
            new AstNode('table_foot', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Total'], [new AstNode('text', ['text' => 'Total'])]),
                    new AstNode('table_cell', ['text' => '1'], [new AstNode('text', ['text' => '1'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildDeclaredColumnOverflowDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Declared column overflow review',
            'alignments' => ['left', 'right'],
            'widths' => [0.5, 0.5],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', ['rowHeadColumns' => 1], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Needs media'], [new AstNode('text', ['text' => 'Needs media'])]),
                    new AstNode('table_cell', ['text' => 'Overflow note'], [new AstNode('text', ['text' => 'Overflow note'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Full width audit note', 'colspan' => 3], [new AstNode('text', ['text' => 'Full width audit note'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSourceCoordinateOverflowDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Rowspan source coordinate review',
            'alignments' => ['left', 'right'],
            'widths' => [0.5, 0.5],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Merged source', 'rowspan' => 2, 'colspan' => 2], [new AstNode('text', ['text' => 'Merged source'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Unexpected source cell'], [new AstNode('text', ['text' => 'Unexpected source cell'])]),
                    new AstNode('table_cell', ['text' => 'Second conflict'], [new AstNode('text', ['text' => 'Second conflict'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSectionGridDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Normalized table grid review',
            'alignments' => ['left', 'center', 'right', 'default'],
            'widths' => [0.25, 0.25, 0.25, 0.25],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope', 'colspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'colspan' => 2, 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Needs media'], [new AstNode('text', ['text' => 'Needs media'])]),
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
    'preserves pandoc table colspec columns beyond physical row cells' => static function (TestRunner $t) use ($buildColspecTableDocument): void {
        $document = $buildColspecTableDocument();
        $table = $document->children[0];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same(3, TableGeometry::columnCountForRows($table->children[0]->children));
        $t->same(['left', 'center', 'right', 'left'], TableGeometry::alignments($table, 4));
        $t->contains('| Scope    |   Items    |     Status |              |', $markdown);
        $t->contains('|:-------|:--------:|---------:|:-----------|', $markdown);
        $t->contains('| Posts    |     42     |      Ready |              |', $markdown);
        $t->contains('<colgroup><col style="width:20%"/><col style="width:25%"/><col style="width:25%"/><col style="width:30%"/></colgroup>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Import queue with reserved audit column</figcaption>', $blocks);
    },
    'renders table body row head columns by visual column for wordpress handoff' => static function (TestRunner $t) use ($buildRowHeadColumnDocument): void {
        $document = $buildRowHeadColumnDocument();
        $table = $document->children[0];
        $body = $table->children[1];
        $layout = TableGeometry::layoutRows($body->children, 4);
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(2, TableGeometry::rowHeadColumns($body, 4));
        $t->same(1, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => '1'], []), 4));
        $t->same(0, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => 'many'], []), 4));
        $t->same(4, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => 9], []), 4));
        $t->same([0, 1, 2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[0]['cells']));
        $t->same([1, 2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->contains('<tbody><tr><th rowspan="2" style="text-align:left">Pandoc</th><th style="text-align:left">Table geometry</th><td style="text-align:right">4</td><td style="text-align:center">Mapped</td></tr><tr><th style="text-align:left">DOCX handoff</th><td style="text-align:right">10</td><td style="text-align:center">Accepted</td></tr></tbody>', $blocks);
        $t->contains('| Pandoc | Table geometry |      4 |  Mapped  |', $markdown);
        $t->contains('|        | DOCX handoff   |     10 | Accepted |', $markdown);
        $t->contains(': Lane coverage review', $markdown);
    },
    'keeps rowspans scoped to table sections for wordpress and markdown handoff' => static function (TestRunner $t) use ($buildSectionScopedRowspanDocument): void {
        $document = $buildSectionScopedRowspanDocument();
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $diagnostics = TableGeometry::diagnostics($table);
        $headLayout = TableGeometry::layoutRows($head->children, 2);
        $bodyLayout = TableGeometry::layoutRows($body->children, 2);
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(2, TableGeometry::columnCount($table));
        $t->same('rowspan-crosses-section-boundary', $diagnostics[0]['code'] ?? null);
        $t->same('head', $diagnostics[0]['section'] ?? null);
        $t->same(0, $diagnostics[0]['row'] ?? null);
        $t->same(0, $diagnostics[0]['column'] ?? null);
        $t->same(2, $diagnostics[0]['rowspan'] ?? null);
        $t->same(1, $diagnostics[0]['availableRows'] ?? null);
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['column'], $headLayout[0]['cells']));
        $t->same(1, $headLayout[0]['cells'][0]['rowspan']);
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['column'], $bodyLayout[0]['cells']));
        $t->contains('<thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Status</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">Ready</td></tr></tbody><tfoot><tr><td style="text-align:left">Total</td><td style="text-align:right">1</td></tr></tfoot>', $blocks);
        $t->contains('| Scope | Status |', $markdown);
        $t->contains('|:----|-----:|', $markdown);
        $t->contains('| Posts |  Ready |', $markdown);
        $t->contains('| Total |      1 |', $markdown);
        $t->contains(': Section boundary review', $markdown);
    },
    'diagnoses cells that exceed declared pandoc table columns without dropping content' => static function (TestRunner $t) use ($buildDeclaredColumnOverflowDocument): void {
        $document = $buildDeclaredColumnOverflowDocument();
        $table = $document->children[0];
        $body = $table->children[1];
        $diagnostics = TableGeometry::diagnostics($table);
        $layout = TableGeometry::layoutRows($body->children, TableGeometry::columnCount($table));
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(3, TableGeometry::columnCount($table));
        $t->same(['left', 'right', 'default'], TableGeometry::alignments($table, 3));
        $t->same(1, TableGeometry::rowHeadColumns($body, 3));
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['column'], $layout[0]['cells']));
        $t->same([1, 2], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->same([0], array_map(static fn (array $cell): int => $cell['column'], $layout[2]['cells']));
        $t->same(3, $layout[2]['cells'][0]['colspan']);
        $t->same(2, count($diagnostics));
        $t->same('cell-exceeds-declared-columns', $diagnostics[0]['code'] ?? null);
        $t->same('body', $diagnostics[0]['section'] ?? null);
        $t->same(1, $diagnostics[0]['row'] ?? null);
        $t->same(2, $diagnostics[0]['column'] ?? null);
        $t->same(1, $diagnostics[0]['colspan'] ?? null);
        $t->same(2, $diagnostics[0]['declaredColumns'] ?? null);
        $t->same(3, $diagnostics[0]['endColumn'] ?? null);
        $t->same(2, $diagnostics[1]['row'] ?? null);
        $t->same(0, $diagnostics[1]['column'] ?? null);
        $t->same(3, $diagnostics[1]['colspan'] ?? null);
        $t->contains('<tbody><tr><th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">Ready</td></tr><tr><td style="text-align:right">Needs media</td><td>Overflow note</td></tr><tr><th colspan="3" style="text-align:left">Full width audit note</th></tr></tbody>', $blocks);
        $t->contains('| Posts                 |                Ready |               |', $markdown);
        $t->contains('|                       |          Needs media | Overflow note |', $markdown);
        $t->contains('| Full width audit note |                      |               |', $markdown);
    },
    'reports source cell coordinates for rowspanned declared column conflicts' => static function (TestRunner $t) use ($buildSourceCoordinateOverflowDocument): void {
        $document = $buildSourceCoordinateOverflowDocument();
        $table = $document->children[0];
        $body = $table->children[0];
        $layout = TableGeometry::layoutRows($body->children, TableGeometry::columnCount($table));
        $diagnostics = TableGeometry::diagnostics($table);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same([0], array_map(static fn (array $cell): int => $cell['sourceCell'], $layout[0]['cells']));
        $t->same([0], array_map(static fn (array $cell): int => $cell['sourceColumn'], $layout[0]['cells']));
        $t->same([2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['sourceCell'], $layout[1]['cells']));
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['sourceColumn'], $layout[1]['cells']));
        $t->same(2, count($diagnostics));
        $t->same('cell-exceeds-declared-columns', $diagnostics[0]['code'] ?? null);
        $t->same(1, $diagnostics[0]['row'] ?? null);
        $t->same(2, $diagnostics[0]['column'] ?? null);
        $t->same(0, $diagnostics[0]['sourceCell'] ?? null);
        $t->same(0, $diagnostics[0]['sourceColumn'] ?? null);
        $t->same(3, $diagnostics[0]['endColumn'] ?? null);
        $t->same(3, $diagnostics[1]['column'] ?? null);
        $t->same(1, $diagnostics[1]['sourceCell'] ?? null);
        $t->same(1, $diagnostics[1]['sourceColumn'] ?? null);
        $t->same(4, $diagnostics[1]['endColumn'] ?? null);
        $t->contains('<tbody><tr><td colspan="2" rowspan="2" style="text-align:left">Merged source</td></tr><tr><td>Unexpected source cell</td><td>Second conflict</td></tr></tbody>', $blocks);
    },
    'builds section grids with covered and missing visual slots for importer audits' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $document = $buildSectionGridDocument();
        $table = $document->children[0];
        $sectionGrids = TableGeometry::sectionGrids($table);
        $bodyGrid = $sectionGrids[1]['rows'];
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(['head', 'body'], array_map(static fn (array $grid): string => $grid['section'], $sectionGrids));
        $t->same(4, $sectionGrids[0]['columnCount']);
        $t->same('cell', $sectionGrids[0]['rows'][0][0]['kind']);
        $t->same('covered', $sectionGrids[0]['rows'][0][1]['kind']);
        $t->same('colspan', $sectionGrids[0]['rows'][0][1]['covering']);
        $t->same('missing', $sectionGrids[0]['rows'][0][3]['kind']);
        $t->same('cell', $bodyGrid[0][0]['kind']);
        $t->same('covered', $bodyGrid[0][1]['kind']);
        $t->same('colspan', $bodyGrid[0][1]['covering']);
        $t->same('covered', $bodyGrid[1][0]['kind']);
        $t->same('rowspan', $bodyGrid[1][0]['covering']);
        $t->same('covered', $bodyGrid[1][1]['kind']);
        $t->same('rowspan-colspan', $bodyGrid[1][1]['covering']);
        $t->same(0, $bodyGrid[1][1]['anchorRow']);
        $t->same(0, $bodyGrid[1][1]['anchorColumn']);
        $t->same(0, $bodyGrid[1][1]['sourceCell']);
        $t->same(0, $bodyGrid[1][1]['sourceColumn']);
        $t->same('cell', $bodyGrid[1][2]['kind']);
        $t->same(0, $bodyGrid[1][2]['sourceCell']);
        $t->same(0, $bodyGrid[1][2]['sourceColumn']);
        $t->same('missing', $bodyGrid[1][3]['kind']);
        $t->contains('<tbody><tr><td colspan="2" rowspan="2" style="text-align:left">Posts</td><td style="text-align:right">Ready</td></tr><tr><td style="text-align:right">Needs media</td></tr></tbody>', $blocks);
        $t->contains('| Posts      |            |       Ready |            |', $markdown);
        $t->contains('|            |            | Needs media |            |', $markdown);
    },
];
