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

$buildDefaultColumnSpecDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Column spec audit',
            'alignments' => ['left', 'bogus', 'right'],
            'widths' => [0.1, 0, '0.45'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Declared'], [new AstNode('text', ['text' => 'Declared'])]),
                    new AstNode('table_cell', ['text' => 'Default'], [new AstNode('text', ['text' => 'Default'])]),
                    new AstNode('table_cell', ['text' => 'Right'], [new AstNode('text', ['text' => 'Right'])]),
                    new AstNode('table_cell', ['text' => 'Implicit'], [new AstNode('text', ['text' => 'Implicit'])]),
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

$buildCellCoverageDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Cell coverage review',
            'alignments' => ['left', 'center', 'right', 'default'],
            'widths' => [0.15, 0.2, 0.25, 0.4],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope', 'colspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2, 'align' => 'right'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42', 'colspan' => 2], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Needs media', 'colspan' => 4], [new AstNode('text', ['text' => 'Needs media'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildBodyHeadRowRoleDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Body-local head row review',
            'alignments' => ['left', 'right', 'center'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Document'], [new AstNode('text', ['text' => 'Document'])]),
                    new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'rowHeadColumns' => 1,
                'headRows' => [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                        new AstNode('table_cell', ['text' => 'Queue'], [new AstNode('text', ['text' => 'Queue'])]),
                        new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                    ]),
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', ['text' => 'Import'], [new AstNode('text', ['text' => 'Import'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildAccessibleHeaderDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Accessible review grid',
            'alignments' => ['left', 'right', 'center'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Migration Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Document', 'colspan' => 2], [new AstNode('text', ['text' => 'Document'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'rowHeadColumns' => 1,
                'headRows' => [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                        new AstNode('table_cell', ['text' => 'Queue'], [new AstNode('text', ['text' => 'Queue'])]),
                        new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                    ]),
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', ['text' => 'Import'], [new AstNode('text', ['text' => 'Import'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildRowspanOverlapDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Malformed overlap review',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', ['rowHeadColumns' => 1], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2, 'colspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Unexpected source cell'], [new AstNode('text', ['text' => 'Unexpected source cell'])]),
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
        $t->same([], TableGeometry::diagnostics($table));
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
        $columnSpecs = TableGeometry::columnSpecs($table, 5);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same(3, TableGeometry::columnCountForRows($table->children[0]->children));
        $t->same(['left', 'center', 'right', 'left'], TableGeometry::alignments($table, 4));
        $t->same([0, 1, 2, 3, 4], array_map(static fn (array $spec): int => $spec['column'], $columnSpecs));
        $t->same(['left', 'center', 'right', 'left', 'default'], array_map(static fn (array $spec): string => $spec['alignment'], $columnSpecs));
        $t->same([0.2, 0.25, 0.25, 0.3, null], array_map(static fn (array $spec): ?float => $spec['width'], $columnSpecs));
        $t->same([true, true, true, true, false], array_map(static fn (array $spec): bool => $spec['declared'], $columnSpecs));
        $t->contains('| Scope    |   Items    |     Status |              |', $markdown);
        $t->contains('|:-------|:--------:|---------:|:-----------|', $markdown);
        $t->contains('| Posts    |     42     |      Ready |              |', $markdown);
        $t->contains('<colgroup><col style="width:20%"/><col style="width:25%"/><col style="width:25%"/><col style="width:30%"/></colgroup>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Import queue with reserved audit column</figcaption>', $blocks);
    },
    'normalizes declared and implicit pandoc column specs for review handoff' => static function (TestRunner $t) use ($buildDefaultColumnSpecDocument): void {
        $document = $buildDefaultColumnSpecDocument();
        $table = $document->children[0];
        $columnSpecs = TableGeometry::columnSpecs($table, 5);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same([0, 1, 2, 3, 4], array_map(static fn (array $spec): int => $spec['column'], $columnSpecs));
        $t->same(['left', 'default', 'right', 'default', 'default'], array_map(static fn (array $spec): string => $spec['alignment'], $columnSpecs));
        $t->same([0.1, null, 0.45, null, null], array_map(static fn (array $spec): ?float => $spec['width'], $columnSpecs));
        $t->same([true, true, true, false, false], array_map(static fn (array $spec): bool => $spec['declared'], $columnSpecs));
        $t->same([], TableGeometry::columnSpecs($table, -2));
        $t->contains('<tbody><tr><td style="text-align:left">Declared</td><td>Default</td><td style="text-align:right">Right</td><td>Implicit</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<colgroup>'), 'Invalid/default Pandoc widths should not emit a misleading WordPress colgroup');
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
        $t->same(4, count($diagnostics));
        $t->same('cell-overlaps-rowspan', $diagnostics[0]['code'] ?? null);
        $t->same([0], $diagnostics[0]['overlapColumns'] ?? null);
        $t->same(2, $diagnostics[0]['column'] ?? null);
        $t->same('cell-overlaps-rowspan', $diagnostics[1]['code'] ?? null);
        $t->same([1], $diagnostics[1]['overlapColumns'] ?? null);
        $t->same(3, $diagnostics[1]['column'] ?? null);
        $t->same('cell-exceeds-declared-columns', $diagnostics[2]['code'] ?? null);
        $t->same(1, $diagnostics[2]['row'] ?? null);
        $t->same(2, $diagnostics[2]['column'] ?? null);
        $t->same(0, $diagnostics[2]['sourceCell'] ?? null);
        $t->same(0, $diagnostics[2]['sourceColumn'] ?? null);
        $t->same(3, $diagnostics[2]['endColumn'] ?? null);
        $t->same(3, $diagnostics[3]['column'] ?? null);
        $t->same(1, $diagnostics[3]['sourceCell'] ?? null);
        $t->same(1, $diagnostics[3]['sourceColumn'] ?? null);
        $t->same(4, $diagnostics[3]['endColumn'] ?? null);
        $t->contains('<tbody><tr><td colspan="2" rowspan="2" style="text-align:left">Merged source</td></tr><tr><td>Unexpected source cell</td><td>Second conflict</td></tr></tbody>', $blocks);
    },
    'diagnoses physical cells that overlap active rowspans without dropping content' => static function (TestRunner $t) use ($buildRowspanOverlapDocument): void {
        $document = $buildRowspanOverlapDocument();
        $table = $document->children[0];
        $body = $table->children[0];
        $layout = TableGeometry::layoutRows($body->children, TableGeometry::columnCount($table));
        $diagnostics = TableGeometry::diagnostics($table);
        $coverage = TableGeometry::cellCoverage($table);
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(3, TableGeometry::columnCount($table));
        $t->same([2], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->same([0], array_map(static fn (array $cell): int => $cell['sourceColumn'], $layout[1]['cells']));
        $t->same(2, count($diagnostics));
        $t->same('cell-overlaps-rowspan', $diagnostics[0]['code'] ?? null);
        $t->same('body', $diagnostics[0]['section'] ?? null);
        $t->same(1, $diagnostics[0]['row'] ?? null);
        $t->same(2, $diagnostics[0]['column'] ?? null);
        $t->same(0, $diagnostics[0]['sourceCell'] ?? null);
        $t->same(0, $diagnostics[0]['sourceColumn'] ?? null);
        $t->same(1, $diagnostics[0]['sourceEndColumn'] ?? null);
        $t->same([0], $diagnostics[0]['overlapColumns'] ?? null);
        $t->same(1, $diagnostics[0]['overlapColumnCount'] ?? null);
        $t->same(2, $diagnostics[0]['visualShift'] ?? null);
        $t->same(2, $diagnostics[0]['declaredColumns'] ?? null);
        $t->same(0, $diagnostics[0]['coveredBy'][0]['row'] ?? null);
        $t->same(0, $diagnostics[0]['coveredBy'][0]['column'] ?? null);
        $t->same(0, $diagnostics[0]['coveredBy'][0]['sourceCell'] ?? null);
        $t->same(0, $diagnostics[0]['coveredBy'][0]['sourceColumn'] ?? null);
        $t->same('cell-exceeds-declared-columns', $diagnostics[1]['code'] ?? null);
        $t->same(2, $diagnostics[1]['column'] ?? null);
        $t->same(0, $diagnostics[1]['sourceColumn'] ?? null);
        $t->same(2, $coverage[1]['column']);
        $t->same(0, $coverage[1]['sourceColumn']);
        $t->same(false, $coverage[1]['headerCell']);
        $t->contains('<tbody><tr><th colspan="2" rowspan="2" style="text-align:left">Posts</th></tr><tr><td>Unexpected source cell</td></tr></tbody>', $blocks);
        $t->contains('|       |     | Unexpected source cell |', $markdown);
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
    'reports cell coverage with visual column specs for importer audits' => static function (TestRunner $t) use ($buildCellCoverageDocument): void {
        $document = $buildCellCoverageDocument();
        $table = $document->children[0];
        $coverage = TableGeometry::cellCoverage($table);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, count($coverage));
        $t->same('head', $coverage[0]['section']);
        $t->same([0, 1], $coverage[0]['columns']);
        $t->same(['left', 'center'], $coverage[0]['columnAlignments']);
        $t->same([0.15, 0.2], $coverage[0]['widths']);
        $t->same([true, true], $coverage[0]['declaredColumns']);
        $t->same(2, $coverage[0]['colspan']);
        $t->same(2, $coverage[0]['rawColspan']);
        $t->same(2, $coverage[0]['endColumn']);
        $t->same(2, $coverage[0]['rawEndColumn']);

        $posts = $coverage[2];
        $t->same('body', $posts['section']);
        $t->same(0, $posts['row']);
        $t->same(0, $posts['sourceCell']);
        $t->same([0], $posts['columns']);
        $t->same('right', $posts['alignment']);
        $t->same(['left'], $posts['columnAlignments']);
        $t->same(2, $posts['rowspan']);
        $t->same(2, $posts['rawRowspan']);

        $needsMedia = $coverage[5];
        $t->same(1, $needsMedia['row']);
        $t->same(0, $needsMedia['sourceCell']);
        $t->same(0, $needsMedia['sourceColumn']);
        $t->same(1, $needsMedia['column']);
        $t->same([1, 2, 3, 4], $needsMedia['columns']);
        $t->same(4, $needsMedia['colspan']);
        $t->same(4, $needsMedia['rawColspan']);
        $t->same(5, $needsMedia['endColumn']);
        $t->same(5, $needsMedia['rawEndColumn']);
        $t->same(['center', 'right', 'default', 'default'], $needsMedia['columnAlignments']);
        $t->same([0.2, 0.25, 0.4, null], $needsMedia['widths']);
        $t->same([true, true, true, false], $needsMedia['declaredColumns']);
        $t->contains('<tr><td colspan="4" style="text-align:center">Needs media</td></tr>', $blocks);
    },
    'marks pandoc body-local head rows in geometry audits and wordpress tbody output' => static function (TestRunner $t) use ($buildBodyHeadRowRoleDocument): void {
        $document = $buildBodyHeadRowRoleDocument();
        $table = $document->children[0];
        $groups = TableGeometry::sectionRowEntryGroups($table);
        $sectionGrids = TableGeometry::sectionGrids($table);
        $coverage = TableGeometry::cellCoverage($table);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['head', 'body'], array_map(static fn (array $group): string => $group['section'], $groups));
        $t->same(['head'], array_map(static fn (array $entry): string => $entry['rowRole'], $groups[0]['rowEntries']));
        $t->same(['body-head', 'body', 'body'], array_map(static fn (array $entry): string => $entry['rowRole'], $groups[1]['rowEntries']));
        $t->same([true, false, false], array_map(static fn (array $entry): bool => $entry['header'], $groups[1]['rowEntries']));
        $t->same([0, 1, 1], array_map(static fn (array $entry): int => $entry['rowHeadColumns'], $groups[1]['rowEntries']));

        $bodyGrid = $sectionGrids[1]['rows'];
        $t->same('body-head', $bodyGrid[0][0]['rowRole']);
        $t->same(true, $bodyGrid[0][0]['headerRow']);
        $t->same(true, $bodyGrid[0][2]['headerCell']);
        $t->same('body', $bodyGrid[1][0]['rowRole']);
        $t->same(false, $bodyGrid[1][0]['headerRow']);
        $t->same(true, $bodyGrid[1][0]['headerCell']);
        $t->same(false, $bodyGrid[1][1]['headerCell']);
        $t->same(true, $bodyGrid[2][0]['headerCell']);
        $t->same('rowspan', $bodyGrid[2][0]['covering']);

        $t->same('body-head', $coverage[3]['rowRole']);
        $t->same(true, $coverage[3]['headerRow']);
        $t->same(true, $coverage[3]['headerCell']);
        $t->same(0, $coverage[3]['rowHeadColumns']);
        $t->same('body', $coverage[6]['rowRole']);
        $t->same(false, $coverage[6]['headerRow']);
        $t->same(true, $coverage[6]['headerCell']);
        $t->same(1, $coverage[6]['rowHeadColumns']);
        $t->same(false, $coverage[7]['headerCell']);

        $t->contains('<tbody><tr><th style="text-align:left">Batch</th><th style="text-align:right">Queue</th><th style="text-align:center">Decision</th></tr><tr><th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Review</td></tr><tr><td style="text-align:right">7</td><td style="text-align:center">Import</td></tr></tbody>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Body-local head row review</figcaption>', $blocks);
    },
    'computes accessible header scopes and wordpress headers attributes across visual spans' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument): void {
        $document = $buildAccessibleHeaderDocument();
        $table = $document->children[0];
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Migration Grid');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('migration-grid-head-r1c1', $accessibility['head:0:0:0']['id'] ?? null);
        $t->same('colgroup', $accessibility['head:0:0:0']['scope'] ?? null);
        $t->same([], $accessibility['head:0:0:0']['headers'] ?? null);
        $t->same('migration-grid-head-r1c3', $accessibility['head:0:1:2']['id'] ?? null);
        $t->same('col', $accessibility['head:0:1:2']['scope'] ?? null);
        $t->same('migration-grid-body-r1c2', $accessibility['body:0:1:1']['id'] ?? null);
        $t->same('col', $accessibility['body:0:1:1']['scope'] ?? null);
        $t->same('migration-grid-body-r2c1', $accessibility['body:1:0:0']['id'] ?? null);
        $t->same('rowgroup', $accessibility['body:1:0:0']['scope'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $accessibility['body:1:1:1']['headers'] ?? null);
        $t->same(['migration-grid-head-r1c3', 'migration-grid-body-r1c3', 'migration-grid-body-r2c1'], $accessibility['body:1:2:2']['headers'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $accessibility['body:2:0:0']['headers'] ?? null);
        $t->same(['migration-grid-head-r1c3', 'migration-grid-body-r1c3', 'migration-grid-body-r2c1'], $accessibility['body:2:1:1']['headers'] ?? null);

        $t->contains('<th id="migration-grid-head-r1c1" scope="colgroup" colspan="2" style="text-align:left">Document</th><th id="migration-grid-head-r1c3" scope="col" style="text-align:center">State</th>', $blocks);
        $t->contains('<th id="migration-grid-body-r1c1" scope="col" style="text-align:left">Batch</th><th id="migration-grid-body-r1c2" scope="col" style="text-align:right">Queue</th><th id="migration-grid-body-r1c3" scope="col" style="text-align:center">Decision</th>', $blocks);
        $t->contains('<th id="migration-grid-body-r2c1" scope="rowgroup" rowspan="2" style="text-align:left">Posts</th><td headers="migration-grid-head-r1c1 migration-grid-body-r1c2 migration-grid-body-r2c1" style="text-align:right">42</td><td headers="migration-grid-head-r1c3 migration-grid-body-r1c3 migration-grid-body-r2c1" style="text-align:center">Review</td>', $blocks);
        $t->contains('<tr><td headers="migration-grid-head-r1c1 migration-grid-body-r1c2 migration-grid-body-r2c1" style="text-align:right">7</td><td headers="migration-grid-head-r1c3 migration-grid-body-r1c3 migration-grid-body-r2c1" style="text-align:center">Import</td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Accessible review grid</figcaption>', $blocks);
    },
    'builds serializable review packets for importer table geometry handoff' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument, $buildDeclaredColumnOverflowDocument): void {
        $accessibleTable = $buildAccessibleHeaderDocument()->children[0];
        $packet = TableGeometry::reviewPacket($accessibleTable, ['idPrefix' => 'Migration Grid']);

        $t->same('Accessible review grid', $packet['caption']);
        $t->same(3, $packet['columnCount']);
        $t->same(3, $packet['declaredColumnCount']);
        $t->same(['left', 'right', 'center'], array_map(static fn (array $spec): string => $spec['alignment'], $packet['columns']));
        $t->same([null, null, null], array_map(static fn (array $spec): ?float => $spec['width'], $packet['columns']));
        $t->same(['head', 'body'], array_map(static fn (array $section): string => $section['section'], $packet['sections']));
        $t->same(1, $packet['sections'][0]['rowCount']);
        $t->same(3, $packet['sections'][1]['rowCount']);
        $t->same('head', $packet['sections'][0]['rows'][0]['rowRole']);
        $t->same('body-head', $packet['sections'][1]['rows'][0]['rowRole']);
        $t->same('body', $packet['sections'][1]['rows'][1]['rowRole']);
        $t->same(true, $packet['sections'][1]['rows'][0]['header']);
        $t->same(false, $packet['sections'][1]['rows'][1]['header']);
        $t->same(1, $packet['sections'][1]['rows'][1]['rowHeadColumns']);
        $t->same('Document', $packet['sections'][0]['rows'][0]['slots'][0]['text']);
        $t->same('covered', $packet['sections'][0]['rows'][0]['slots'][1]['kind']);
        $t->same('colspan', $packet['sections'][0]['rows'][0]['slots'][1]['covering']);
        $t->same('Posts', $packet['sections'][1]['rows'][1]['slots'][0]['text']);
        $t->same('covered', $packet['sections'][1]['rows'][2]['slots'][0]['kind']);
        $t->same('rowspan', $packet['sections'][1]['rows'][2]['slots'][0]['covering']);
        $t->same(10, $packet['summary']['cellCount']);
        $t->same(6, $packet['summary']['headerCellCount']);
        $t->same(2, $packet['summary']['coveredSlotCount']);
        $t->same(0, $packet['summary']['missingSlotCount']);
        $t->same([], $packet['summary']['diagnosticCodes']);
        $t->same(true, $packet['summary']['hasSpans']);
        $t->same(false, array_key_exists('node', $packet['coverage'][0]));
        $t->same('Document', $packet['coverage'][0]['text']);
        $t->same('Posts', $packet['coverage'][5]['text']);
        $t->same('migration-grid-head-r1c1', $packet['accessibility']['head:0:0:0']['id'] ?? null);
        $t->same('colgroup', $packet['accessibility']['head:0:0:0']['scope'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $packet['accessibility']['body:1:1:1']['headers'] ?? null);
        $encoded = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->contains('"sections"', $encoded);
        $t->contains('"accessibility"', $encoded);

        $overflowPacket = TableGeometry::reviewPacket($buildDeclaredColumnOverflowDocument()->children[0], ['accessibility' => false]);
        $t->same(3, $overflowPacket['columnCount']);
        $t->same(2, $overflowPacket['declaredColumnCount']);
        $t->same(2, $overflowPacket['summary']['diagnosticCount']);
        $t->same(['cell-exceeds-declared-columns'], $overflowPacket['summary']['diagnosticCodes']);
        $t->same([], $overflowPacket['accessibility']);
        $t->same('Full width audit note', $overflowPacket['coverage'][6]['text']);
        $t->same(true, $overflowPacket['coverage'][6]['headerCell']);
        $t->same(3, $overflowPacket['coverage'][6]['colspan']);
        $t->same(1, $overflowPacket['sections'][1]['rows'][0]['rowHeadColumns']);
        $t->same('missing', $overflowPacket['sections'][0]['rows'][0]['slots'][2]['kind']);
        $t->same(false, array_key_exists('node', $overflowPacket['sections'][1]['rows'][2]['slots'][0]));
    },
];
