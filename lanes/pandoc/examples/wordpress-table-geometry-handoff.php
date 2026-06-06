<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$readerHandoffDocument = (new MarkdownReader())->read(implode("\n", [
    '| Source | Count | State |',
    '|:-------|------:|:----:|',
    '| Posts | 42 | Ready |',
    '| Media | 7 | Review |',
    '',
    ': Reader packet import metrics',
]));
$readerHandoffTables = array_values(array_filter(
    $readerHandoffDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$rowspanZeroDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="rowspan-zero-grid" data-source="html-reader">
<tbody id="posts-body">
<tr data-row="posts-total"><th rowspan="0" align="left">Posts</th><td align="right">42</td></tr>
<tr data-row="posts-media"><td align="right">7</td><td>Needs media</td></tr>
<tr data-row="posts-review"><td align="right">3</td><td>Review</td></tr>
</tbody>
<tbody id="pages-body">
<tr data-row="pages-total"><th>Pages</th><td align="right">5</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$rowspanZeroTables = array_values(array_filter(
    $rowspanZeroDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$colgroupAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="colgroup-alignment-grid" data-source="html-reader">
<caption>Colgroup alignment review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 25%; text-align: right; vertical-align: bottom" data-origin="col-a" />
<col width="50%" align="center" valign="top" data-origin="col-b" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
<tr><td>Media</td><td>7</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$colgroupAlignmentTables = array_values(array_filter(
    $colgroupAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$colgroupMismatchDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="colgroup-underdeclared-grid" data-source="html-reader">
<caption>Colgroup mismatch review</caption>
<colgroup data-source="legacy-doc">
<col span="2" style="width: 20%; text-align: right" data-origin="declared-pair" />
</colgroup>
<thead>
<tr><th>Scope</th><th>Items</th><th>State</th></tr>
</thead>
<tbody>
<tr><td>Posts</td><td>42</td><td>Ready</td></tr>
</tbody>
</table>
HTML);
$colgroupMismatchTables = array_values(array_filter(
    $colgroupMismatchDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$inheritedAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="inherited-alignment-grid" data-source="html-reader">
<caption>Inherited alignment review</caption>
<thead align="center">
<tr><th>Scope</th><th style="text-align: right">Items</th><th>State</th></tr>
</thead>
<tbody style="text-align: right" data-section="body">
<tr data-row="posts"><th>Posts</th><td>42</td><td align="center">Ready</td></tr>
<tr style="text-align: left" data-row="media"><th>Media</th><td>7</td><td>Review</td></tr>
</tbody>
<tfoot align="center">
<tr><td>Total</td><td>49</td><td>Review</td></tr>
</tfoot>
</table>
HTML);
$inheritedAlignmentTables = array_values(array_filter(
    $inheritedAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$verticalAlignmentDocument = (new MarkdownReader())->read(<<<'HTML'
<table id="vertical-alignment-grid" data-source="html-reader">
<caption>Vertical alignment review</caption>
<thead valign="top">
<tr><th>Scope</th><th style="vertical-align: bottom">State</th></tr>
</thead>
<tbody data-section="body" valign="baseline">
<tr><td valign="middle">Posts</td><td>Ready</td></tr>
<tr style="vertical-align: top"><td>Total</td><td>Review</td></tr>
</tbody>
</table>
HTML);
$verticalAlignmentTables = array_values(array_filter(
    $verticalAlignmentDocument->children,
    static fn (AstNode $node): bool => $node->type === 'table'
));
$captionMetadataTables = [
    new AstNode('table', [
        'caption' => 'Long caption for reviewer',
        'captionInlines' => [
            new AstNode('text', ['text' => 'Long ']),
            new AstNode('emph', [], [new AstNode('text', ['text' => 'caption'])]),
            new AstNode('text', ['text' => ' for ']),
            new AstNode('link', ['url' => 'https://example.test/review', 'title' => 'Review'], [
                new AstNode('text', ['text' => 'reviewer']),
            ]),
        ],
        'shortCaption' => 'Queue short',
        'shortCaptionInlines' => [
            new AstNode('text', ['text' => 'Queue ']),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'short'])]),
        ],
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
];

$blockCaptionTable = new AstNode('table', [
    'caption' => 'Fallback block caption text',
    'captionBlocks' => [
        new AstNode('paragraph', [], [
            new AstNode('text', ['text' => 'Block ']),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'caption'])]),
            new AstNode('text', ['text' => ' for reviewer']),
        ]),
        new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Queue note'])]),
            ]),
        ]),
    ],
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$overfullWidthTable = new AstNode('table', [
    'caption' => 'Overfull source width audit',
    'alignments' => ['left', 'right', 'center'],
    'widths' => [0.6, 0.6, 0.3],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$underfullWidthTable = new AstNode('table', [
    'caption' => 'Underfull source width audit',
    'alignments' => ['left', 'right', 'center'],
    'widths' => [0.2, 0.3, 0.4],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$invalidWidthTable = new AstNode('table', [
    'caption' => 'Invalid source width audit',
    'alignments' => ['left', 'right', 'center', 'default'],
    'widths' => [0.25, 'auto', -0.1, null],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
            new AstNode('table_cell', ['text' => 'Notes'], [new AstNode('text', ['text' => 'Notes'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            new AstNode('table_cell', ['text' => 'Review widths'], [new AstNode('text', ['text' => 'Review widths'])]),
        ]),
    ]),
]);

$malformedSpanTable = new AstNode('table', [
    'caption' => 'Malformed source span review',
    'alignments' => ['left', 'right', 'center'],
    'id' => 'malformed-source-span-grid',
], [
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Posts',
                'colspan' => '0',
                'rowspan' => 'many',
            ], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [
                'text' => 'Media',
                'rowspan' => -3,
            ], [new AstNode('text', ['text' => 'Media'])]),
            new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
            new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
        ]),
    ]),
]);

$blockContentTable = new AstNode('table', [
    'caption' => 'Cell block content audit',
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Packet'], [new AstNode('text', ['text' => 'Packet'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Review source'], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Review ']),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'source'])]),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Image alt text'])]),
                    ]),
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [
                            new AstNode('strong', [], [new AstNode('text', ['text' => 'Resolve captions'])]),
                        ]),
                    ]),
                ]),
            ]),
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$latexNestedTable = new AstNode('table', [
    'caption' => 'Nested LaTeX audit',
    'alignments' => ['left', 'right'],
    'widths' => [0.5, 0.5],
], [
    new AstNode('table_head'),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Inner scope'], [new AstNode('text', ['text' => 'Inner scope'])]),
            new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
        ]),
    ]),
]);

$latexRequirementTable = new AstNode('table', [
    'caption' => 'LaTeX table requirement audit',
    'alignments' => ['left', 'right', 'center'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Document', 'colspan' => 2], [new AstNode('text', ['text' => 'Document'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Posts', 'rowspan' => 2], [new AstNode('text', ['text' => 'Posts'])]),
            new AstNode('table_cell', ['text' => 'Review source', 'colspan' => 2], [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Review ']),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'source'])]),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Resolve media'])]),
                    ]),
                ]),
            ]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            new AstNode('table_cell', ['text' => 'Nested packet'], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Nested packet'])]),
                $latexNestedTable,
            ]),
        ]),
    ]),
]);

$latexFooterTable = new AstNode('table', [
    'caption' => 'LaTeX footer audit',
    'alignments' => ['left', 'right'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
            new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
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
            new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
        ]),
    ]),
]);

$document = new AstNode('document', [], [
    new AstNode('table', [
        'caption' => 'Migration review grid',
        'alignments' => ['left', 'right', 'center', 'default'],
        'widths' => [0.25, 0.25, 0.25, 0.25],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Scope', 'colspan' => 2], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
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
                new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                new AstNode('table_cell', ['text' => 'Needs review'], [new AstNode('text', ['text' => 'Needs review'])]),
            ]),
        ]),
    ]),
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
    new AstNode('table', [
        'caption' => 'Source attributed grid',
        'alignments' => ['left', 'right'],
        'accessibilityHeaders' => true,
        'accessibilityIdPrefix' => 'Source Grid',
        'id' => 'source-grid',
        'classes' => ['wp-import', 'needs-review'],
        'attributes' => [
            'origin' => 'docx',
        ],
        'htmlAttributes' => [
            'id' => 'source-grid',
            'class' => 'wp-import needs-review',
            'data-origin' => 'docx',
            'aria-label' => 'Source attributed review grid',
        ],
    ], [
        new AstNode('table_head', [
            'htmlAttributes' => [
                'id' => 'source-grid-head',
                'data-section' => 'thead',
            ],
        ], [
            new AstNode('table_row', [
                'htmlAttributes' => [
                    'data-row' => 'source-head-1',
                ],
            ], [
                new AstNode('table_cell', [
                    'text' => 'Scope',
                    'htmlAttributes' => [
                        'id' => 'docx-source-scope',
                        'class' => 'source-cell',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Scope'])]),
                new AstNode('table_cell', [
                    'text' => 'Status',
                    'id' => 'ast-status-source',
                    'classes' => ['ast-header'],
                ], [new AstNode('text', ['text' => 'Status'])]),
            ]),
        ]),
        new AstNode('table_body', [
            'htmlAttributes' => [
                'id' => 'source-grid-body',
                'data-section' => 'tbody',
            ],
        ], [
            new AstNode('table_row', [
                'htmlAttributes' => [
                    'data-row' => 'source-body-1',
                ],
            ], [
                new AstNode('table_cell', [
                    'text' => 'Posts',
                    'htmlAttributes' => [
                        'class' => 'body-source',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', [
                    'text' => 'Ready',
                    'htmlAttributes' => [
                        'headers' => 'legacy-status',
                        'data-origin' => 'docx',
                    ],
                ], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Source scoped accessibility grid',
        'alignments' => ['left', 'right', 'center'],
        'accessibilityHeaders' => true,
        'accessibilityIdPrefix' => 'Source Scope Grid',
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', [
                    'text' => 'Document',
                    'htmlAttributes' => [
                        'id' => 'source-document',
                        'scope' => 'col',
                    ],
                ], [new AstNode('text', ['text' => 'Document'])]),
                new AstNode('table_cell', [
                    'text' => 'Count',
                    'htmlAttributes' => [
                        'id' => 'source-count',
                        'scope' => 'col',
                    ],
                ], [new AstNode('text', ['text' => 'Count'])]),
                new AstNode('table_cell', [
                    'text' => 'State',
                    'htmlAttributes' => [
                        'id' => 'source-state',
                        'scope' => 'col',
                        'headers' => 'source-document',
                    ],
                ], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', ['rowHeadColumns' => 1], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', [
                    'text' => 'Posts',
                    'rowspan' => 2,
                    'htmlAttributes' => [
                        'id' => 'source-posts',
                        'scope' => 'row',
                    ],
                ], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', [
                    'text' => '42',
                    'htmlAttributes' => [
                        'headers' => 'legacy-count source-posts',
                    ],
                ], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Nested table packet review',
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Packet', 'header' => true], [new AstNode('text', ['text' => 'Packet'])]),
                new AstNode('table_cell', ['text' => 'State', 'header' => true], [new AstNode('text', ['text' => 'State'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => 'Nested review packet'], [
                    new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Nested review packet'])]),
                    new AstNode('table', [
                        'caption' => 'Nested queue audit',
                        'alignments' => ['left', 'right'],
                        'widths' => [0.5, 0.5],
                    ], [
                        new AstNode('table_head'),
                        new AstNode('table_body', [], [
                            new AstNode('table_row', [], [
                                new AstNode('table_cell', ['text' => 'Inner posts'], [new AstNode('text', ['text' => 'Inner posts'])]),
                                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                            ]),
                        ]),
                    ]),
                ]),
                new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
            ]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Implicit source shift review',
        'id' => 'implicit-source-shift-grid',
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
    ...$rowspanZeroTables,
    ...$colgroupAlignmentTables,
    ...$colgroupMismatchTables,
    ...$inheritedAlignmentTables,
    ...$verticalAlignmentTables,
    ...$readerHandoffTables,
    ...$captionMetadataTables,
    $blockCaptionTable,
    $malformedSpanTable,
    $underfullWidthTable,
    $invalidWidthTable,
    $blockContentTable,
    $latexRequirementTable,
    $latexFooterTable,
]);

$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $overfullWidthPacket = TableGeometry::reviewPacket($overfullWidthTable, ['accessibility' => false]);
    if (($overfullWidthPacket['widthSummary']['normalizedWidths'] ?? null) !== [0.4, 0.4, 0.2]) {
        throw new RuntimeException('Table geometry self-test missing normalized overfull source width metadata');
    }
    if (($overfullWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-exceed-full-width']) {
        throw new RuntimeException('Table geometry self-test missing overfull source width diagnostic');
    }
    if (($overfullWidthPacket['columns'][0]['percentWidth'] ?? null) !== 60.0 || ($overfullWidthPacket['columns'][2]['normalizedWidth'] ?? null) !== 0.2) {
        throw new RuntimeException('Table geometry self-test missing per-column width percentages');
    }
    json_encode($overfullWidthPacket, JSON_THROW_ON_ERROR);

    $underfullWidthPacket = TableGeometry::reviewPacket($underfullWidthTable, ['accessibility' => false]);
    if (($underfullWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-underfill-full-width']) {
        throw new RuntimeException('Table geometry self-test missing underfull source width diagnostic');
    }
    if (($underfullWidthPacket['widthSummary']['underflowAmount'] ?? null) !== 0.1 || ($underfullWidthPacket['widthSummary']['normalizedWidths'] ?? null) !== [0.222222, 0.333333, 0.444444]) {
        throw new RuntimeException('Table geometry self-test missing normalized underfull source width metadata');
    }
    if (($underfullWidthPacket['columns'][1]['percentWidth'] ?? null) !== 30.0 || ($underfullWidthPacket['columns'][2]['normalizedWidth'] ?? null) !== 0.444444) {
        throw new RuntimeException('Table geometry self-test missing per-column underfull width percentages');
    }
    $underfullWidthBlock = '<figure class="wp-block-table"><table><colgroup><col style="width:20%"/><col style="width:30%"/><col style="width:40%"/></colgroup><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr></tbody></table><figcaption class="wp-element-caption">Underfull source width audit</figcaption></figure>';
    if (!str_contains($blocks, $underfullWidthBlock)) {
        throw new RuntimeException('Table geometry self-test missing underfull source width review table');
    }
    json_encode($underfullWidthPacket, JSON_THROW_ON_ERROR);

    $invalidWidthPacket = TableGeometry::reviewPacket($invalidWidthTable, ['accessibility' => false]);
    if (($invalidWidthPacket['summary']['diagnosticCodes'] ?? null) !== ['table-widths-have-invalid-values']) {
        throw new RuntimeException('Table geometry self-test missing invalid width diagnostic');
    }
    if (($invalidWidthPacket['widthSummary']['invalidWidthColumns'] ?? null) !== [1, 2]) {
        throw new RuntimeException('Table geometry self-test missing invalid width column summary');
    }
    if (($invalidWidthPacket['widthSummary']['invalidWidths'][0]['rawValue'] ?? null) !== 'auto' || ($invalidWidthPacket['widthSummary']['invalidWidths'][1]['rawValue'] ?? null) !== -0.1) {
        throw new RuntimeException('Table geometry self-test missing invalid width raw values');
    }
    if (($invalidWidthPacket['widthSummary']['validWidthColumns'] ?? null) !== [0] || ($invalidWidthPacket['widthSummary']['missingColumns'] ?? null) !== [1, 2, 3]) {
        throw new RuntimeException('Table geometry self-test missing valid/missing width provenance');
    }
    $invalidWidthBlock = '<figure class="wp-block-table"><table><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th><th>Notes</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td><td>Review widths</td></tr></tbody></table><figcaption class="wp-element-caption">Invalid source width audit</figcaption></figure>';
    if (!str_contains($blocks, $invalidWidthBlock)) {
        throw new RuntimeException('Table geometry self-test missing invalid source width review table');
    }
    json_encode($invalidWidthPacket, JSON_THROW_ON_ERROR);

    $migrationGrids = TableGeometry::sectionGrids($document->children[0]);
    $columnSpecs = TableGeometry::columnSpecs($document->children[0], 5);
    $cellCoverage = TableGeometry::cellCoverage($document->children[0]);
    if (array_map(static fn (array $spec): string => $spec['alignment'], $columnSpecs) !== ['left', 'right', 'center', 'default', 'default']) {
        throw new RuntimeException('Table geometry self-test missing normalized column alignment specs');
    }
    if (array_map(static fn (array $spec): ?float => $spec['width'], $columnSpecs) !== [0.25, 0.25, 0.25, 0.25, null]) {
        throw new RuntimeException('Table geometry self-test missing normalized column width specs');
    }
    if (array_map(static fn (array $spec): bool => $spec['declared'], $columnSpecs) !== [true, true, true, true, false]) {
        throw new RuntimeException('Table geometry self-test missing implicit column spec marker');
    }

    if (($migrationGrids[0]['rows'][0][1]['kind'] ?? null) !== 'covered' || ($migrationGrids[0]['rows'][0][1]['covering'] ?? null) !== 'colspan') {
        throw new RuntimeException('Table geometry self-test missing head colspan covered-slot report');
    }
    if (($migrationGrids[0]['rows'][0][3]['kind'] ?? null) !== 'missing') {
        throw new RuntimeException('Table geometry self-test missing head trailing missing-slot report');
    }
    if (($migrationGrids[1]['rows'][1][0]['kind'] ?? null) !== 'covered' || ($migrationGrids[1]['rows'][1][0]['covering'] ?? null) !== 'rowspan') {
        throw new RuntimeException('Table geometry self-test missing body rowspan covered-slot report');
    }
    if (($migrationGrids[1]['rows'][1][3]['kind'] ?? null) !== 'missing') {
        throw new RuntimeException('Table geometry self-test missing body trailing missing-slot report');
    }
    $expectedOccupiedSlots = [
        ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
        ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
    ];
    if (($migrationGrids[1]['rows'][0][0]['occupiedSlots'] ?? null) !== $expectedOccupiedSlots) {
        throw new RuntimeException('Table geometry self-test missing anchor-cell occupied-slot report');
    }
    if (($cellCoverage[0]['section'] ?? null) !== 'head' || ($cellCoverage[0]['columns'] ?? null) !== [0, 1]) {
        throw new RuntimeException('Table geometry self-test missing head cell visual coverage report');
    }
    if (($cellCoverage[0]['columnAlignments'] ?? null) !== ['left', 'right'] || ($cellCoverage[0]['widths'] ?? null) !== [0.25, 0.25]) {
        throw new RuntimeException('Table geometry self-test missing covered column specs');
    }
    if (($cellCoverage[2]['section'] ?? null) !== 'body' || ($cellCoverage[2]['rowspan'] ?? null) !== 2 || ($cellCoverage[2]['columns'] ?? null) !== [0]) {
        throw new RuntimeException('Table geometry self-test missing rowspanned body cell coverage report');
    }
    if (($cellCoverage[2]['occupiedSlots'] ?? null) !== $expectedOccupiedSlots) {
        throw new RuntimeException('Table geometry self-test missing rowspanned coverage occupied-slot report');
    }
    if (($cellCoverage[5]['sourceCell'] ?? null) !== 0 || ($cellCoverage[5]['sourceColumn'] ?? null) !== 0 || ($cellCoverage[5]['column'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual coverage coordinates');
    }
    $writerDowngrades = TableGeometry::writerDowngradeDiagnostics($document->children[0], 'markdown');
    if (array_map(static fn (array $diagnostic): string => $diagnostic['code'], $writerDowngrades) !== ['markdown-colspan-flattened', 'markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing Markdown writer downgrade diagnostics');
    }
    $rstWriterRequirements = TableGeometry::writerDowngradeDiagnostics($document->children[0], 'rst-grid-table');
    if (
        array_map(static fn (array $diagnostic): string => $diagnostic['code'], $rstWriterRequirements) !== ['rst-grid-table-required']
        || ($rstWriterRequirements[0]['requiredFeature'] ?? null) !== 'grid-table'
        || ($rstWriterRequirements[0]['requiredSlots'] ?? null) !== [['row' => 1, 'column' => 0, 'covering' => 'rowspan']]
    ) {
        throw new RuntimeException('Table geometry self-test missing RST grid-table writer requirement diagnostics');
    }
    $migrationPacket = TableGeometry::reviewPacket($document->children[0], ['idPrefix' => 'Migration Grid']);
    if (($migrationPacket['summary']['writerDowngradeCount'] ?? null) !== 2 || ($migrationPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-colspan-flattened', 'markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing review-packet writer downgrade summary');
    }
    if (($migrationPacket['writerDowngrades']['markdown'][0]['flattenedSlots'] ?? null) !== [['row' => 0, 'column' => 1, 'covering' => 'colspan']]) {
        throw new RuntimeException('Table geometry self-test missing flattened span slot report');
    }
    $multiWriterPacket = TableGeometry::reviewPacket($document->children[0], [
        'idPrefix' => 'Migration Grid',
        'writers' => ['markdown', 'restructuredtext'],
    ]);
    if (
        ($multiWriterPacket['summary']['writerDowngradeCount'] ?? null) !== 3
        || ($multiWriterPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-colspan-flattened', 'markdown-rowspan-flattened', 'rst-grid-table-required']
        || ($multiWriterPacket['summary']['writerDowngradeWriters'] ?? null) !== ['markdown', 'rst']
    ) {
        throw new RuntimeException('Table geometry self-test missing multi-writer downgrade summary');
    }
    if (($multiWriterPacket['writerDowngrades']['rst'][0]['requiredSlots'] ?? null) !== [['row' => 1, 'column' => 0, 'covering' => 'rowspan']]) {
        throw new RuntimeException('Table geometry self-test missing RST grid-table required-slot report');
    }
    json_encode($multiWriterPacket, JSON_THROW_ON_ERROR);

    $sectionDiagnostics = TableGeometry::diagnostics($document->children[1]);
    if (!str_contains($blocks, '<colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:25%"/><col style="width:25%"/></colgroup>')) {
        throw new RuntimeException('Table geometry self-test missing trailing colspec width');
    }
    if (!str_contains($blocks, '<th colspan="2" style="text-align:left">Scope</th><th style="text-align:center">Status</th>')) {
        throw new RuntimeException('Table geometry self-test missing visual-column header alignment');
    }
    if (!str_contains($blocks, '<th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing rowspan body alignment');
    }
    if (($sectionDiagnostics[0]['code'] ?? null) !== 'rowspan-crosses-section-boundary') {
        throw new RuntimeException('Table geometry self-test missing section-boundary rowspan diagnostic');
    }
    if (!str_contains($blocks, '<thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">Status</th></tr></thead><tbody><tr><td style="text-align:left">Pages</td><td style="text-align:right">Needs review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing section-scoped rowspan clamp');
    }
    $overflowDiagnostics = TableGeometry::diagnostics($document->children[2]);
    if (($overflowDiagnostics[0]['code'] ?? null) !== 'cell-exceeds-declared-columns') {
        throw new RuntimeException('Table geometry self-test missing declared-column overflow diagnostic');
    }
    if (($overflowDiagnostics[0]['sourceCell'] ?? null) !== 1 || ($overflowDiagnostics[0]['sourceColumn'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing overflow source-cell coordinates');
    }
    if (($overflowDiagnostics[1]['colspan'] ?? null) !== 3) {
        throw new RuntimeException('Table geometry self-test missing over-wide colspan diagnostic');
    }
    if (($overflowDiagnostics[1]['sourceCell'] ?? null) !== 0 || ($overflowDiagnostics[1]['sourceColumn'] ?? null) !== 0) {
        throw new RuntimeException('Table geometry self-test missing colspan source-cell coordinates');
    }
    if (!str_contains($blocks, '<tr><td style="text-align:right">Needs media</td><td>Overflow note</td></tr><tr><th colspan="3" style="text-align:left">Full width audit note</th></tr>')) {
        throw new RuntimeException('Table geometry self-test dropped malformed declared-column overflow content');
    }

    $bodyHeadGroups = TableGeometry::sectionRowEntryGroups($document->children[3]);
    if (($bodyHeadGroups[1]['rowEntries'][0]['rowRole'] ?? null) !== 'body-head') {
        throw new RuntimeException('Table geometry self-test missing body-local head row role');
    }
    if (($bodyHeadGroups[1]['rowEntries'][1]['rowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body row-head column metadata');
    }
    $bodyHeadGrid = TableGeometry::sectionGrids($document->children[3]);
    if (($bodyHeadGrid[1]['rows'][0][2]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-head visual header-cell marker');
    }
    if (($bodyHeadGrid[1]['rows'][2][0]['headerCell'] ?? null) !== true || ($bodyHeadGrid[1]['rows'][2][0]['covering'] ?? null) !== 'rowspan') {
        throw new RuntimeException('Table geometry self-test missing row-head covered-slot marker');
    }
    $bodyHeadCoverage = TableGeometry::cellCoverage($document->children[3]);
    if (($bodyHeadCoverage[3]['rowRole'] ?? null) !== 'body-head' || ($bodyHeadCoverage[3]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-head coverage metadata');
    }
    if (($bodyHeadCoverage[6]['rowRole'] ?? null) !== 'body' || ($bodyHeadCoverage[6]['rowHeadColumns'] ?? null) !== 1 || ($bodyHeadCoverage[6]['headerCell'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing row-head coverage metadata');
    }
    $bodyHeadPacket = TableGeometry::reviewPacket($document->children[3], ['accessibility' => false]);
    if (($bodyHeadPacket['summary']['rowGroupCount'] ?? null) !== 2 || ($bodyHeadPacket['summary']['hasBodyHeadRows'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group summary');
    }
    if (($bodyHeadPacket['summary']['bodyHeadRowCount'] ?? null) !== 1 || ($bodyHeadPacket['summary']['rowHeadGroupCount'] ?? null) !== 1 || ($bodyHeadPacket['summary']['maxRowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group counters');
    }
    if (($bodyHeadPacket['rowGroups'][1]['rowRoles'] ?? null) !== ['body-head', 'body'] || ($bodyHeadPacket['rowGroups'][1]['bodyHeadRowCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local row-group roles');
    }
    if (!str_contains($blocks, '<tbody><tr><th style="text-align:left">Batch</th><th style="text-align:right">Queue</th><th style="text-align:center">Decision</th></tr><tr><th rowspan="2" style="text-align:left">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Review</td></tr><tr><td style="text-align:right">7</td><td style="text-align:center">Import</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing body-local head rows in WordPress tbody output');
    }
    $overlapDiagnostics = TableGeometry::diagnostics($document->children[4]);
    if (($overlapDiagnostics[0]['code'] ?? null) !== 'cell-overlaps-rowspan') {
        throw new RuntimeException('Table geometry self-test missing rowspanned overlap diagnostic');
    }
    if (($overlapDiagnostics[0]['column'] ?? null) !== 2 || ($overlapDiagnostics[0]['sourceColumn'] ?? null) !== 0 || ($overlapDiagnostics[0]['overlapColumns'] ?? null) !== [0]) {
        throw new RuntimeException('Table geometry self-test missing overlap source-cell coordinates');
    }
    if (($overlapDiagnostics[0]['coveredBy'][0]['colspan'] ?? null) !== 2 || ($overlapDiagnostics[0]['declaredColumns'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing overlap anchor metadata');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Malformed overlap review</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing malformed overlap review table');
    }
    $accessibleHeaders = TableGeometry::accessibilityAttributes($document->children[5], 'Migration Grid');
    if (($accessibleHeaders['body:1:1:1']['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']) {
        throw new RuntimeException('Table geometry self-test missing computed accessible header relationships');
    }
    if (!str_contains($blocks, '<th id="migration-grid-head-r1c1" scope="colgroup" colspan="2" style="text-align:left">Document</th><th id="migration-grid-head-r1c3" scope="col" style="text-align:center">State</th>')) {
        throw new RuntimeException('Table geometry self-test missing accessible header scope attributes');
    }
    if (!str_contains($blocks, '<td headers="migration-grid-head-r1c1 migration-grid-body-r1c2 migration-grid-body-r2c1" style="text-align:right">42</td>')) {
        throw new RuntimeException('Table geometry self-test missing accessible data-cell headers attributes');
    }
    $reviewPacket = TableGeometry::reviewPacket($document->children[5], ['idPrefix' => 'Migration Grid']);
    if (($reviewPacket['summary']['cellCount'] ?? null) !== 10 || ($reviewPacket['summary']['coveredSlotCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing serializable review-packet summary');
    }
    if (($reviewPacket['sections'][1]['rows'][0]['rowRole'] ?? null) !== 'body-head' || ($reviewPacket['sections'][1]['rows'][1]['rowHeadColumns'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing review-packet row roles');
    }
    if (($reviewPacket['coverage'][5]['text'] ?? null) !== 'Posts' || array_key_exists('node', $reviewPacket['coverage'][5])) {
        throw new RuntimeException('Table geometry self-test missing serializable review-packet coverage text');
    }
    if (($reviewPacket['coverage'][5]['occupiedSlots'] ?? null) !== [
        ['row' => 1, 'column' => 0, 'covering' => 'anchor'],
        ['row' => 2, 'column' => 0, 'covering' => 'rowspan'],
    ]) {
        throw new RuntimeException('Table geometry self-test missing review-packet occupied slots');
    }
    if (($reviewPacket['accessibility']['body:1:1:1']['headers'] ?? null) !== ['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1']) {
        throw new RuntimeException('Table geometry self-test missing review-packet accessibility relationships');
    }
    json_encode($reviewPacket, JSON_THROW_ON_ERROR);

    $sourceAccessibility = TableGeometry::accessibilityAttributes($document->children[6], 'Source Grid');
    if (($sourceAccessibility['head:0:0:0']['id'] ?? null) !== 'docx-source-scope') {
        throw new RuntimeException('Table geometry self-test missing source header cell id in accessibility handoff');
    }
    if (($sourceAccessibility['head:0:1:1']['id'] ?? null) !== 'ast-status-source') {
        throw new RuntimeException('Table geometry self-test missing AST header cell id in accessibility handoff');
    }
    if (($sourceAccessibility['body:0:0:0']['headers'] ?? null) !== ['docx-source-scope']) {
        throw new RuntimeException('Table geometry self-test missing source header id reference on data cell');
    }
    if (!str_contains($blocks, '<th scope="col" id="docx-source-scope" class="source-cell" data-origin="docx" style="text-align:left">Scope</th>')) {
        throw new RuntimeException('Table geometry self-test missing source table cell attributes');
    }
    if (!str_contains($blocks, '<th scope="col" id="ast-status-source" class="ast-header" style="text-align:right">Status</th>')) {
        throw new RuntimeException('Table geometry self-test missing AST table cell attributes');
    }
    if (!str_contains($blocks, '<td headers="docx-source-scope" class="body-source" data-origin="docx" style="text-align:left">Posts</td>')) {
        throw new RuntimeException('Table geometry self-test missing source-id headers handoff');
    }
    if (!str_contains($blocks, '<td headers="legacy-status" data-origin="docx" style="text-align:right">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source headers override preservation');
    }
    $sourceAttributePacket = TableGeometry::reviewPacket($document->children[6], ['idPrefix' => 'Source Grid']);
    if (($sourceAttributePacket['sourceAttributes']['id'] ?? null) !== 'source-grid' || ($sourceAttributePacket['sourceAttributes']['classes'] ?? null) !== ['wp-import', 'needs-review']) {
        throw new RuntimeException('Table geometry self-test missing table source attribute packet');
    }
    if (($sourceAttributePacket['sections'][0]['sourceAttributes']['htmlAttributes']['data-section'] ?? null) !== 'thead') {
        throw new RuntimeException('Table geometry self-test missing section source attribute packet');
    }
    if (($sourceAttributePacket['sections'][0]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null) !== 'source-head-1') {
        throw new RuntimeException('Table geometry self-test missing row source attribute packet');
    }
    if (($sourceAttributePacket['coverage'][0]['sourceAttributes']['id'] ?? null) !== 'docx-source-scope') {
        throw new RuntimeException('Table geometry self-test missing cell source attribute packet');
    }
    json_encode($sourceAttributePacket, JSON_THROW_ON_ERROR);

    $sourceScopeAccessibility = TableGeometry::accessibilityAttributes($document->children[7], 'Source Scope Grid');
    if (($sourceScopeAccessibility['body:0:0:0']['scope'] ?? null) !== 'row') {
        throw new RuntimeException('Table geometry self-test missing source scope override in accessibility handoff');
    }
    if (($sourceScopeAccessibility['body:0:1:1']['headers'] ?? null) !== ['legacy-count', 'source-posts']) {
        throw new RuntimeException('Table geometry self-test missing source headers override in accessibility handoff');
    }
    if (in_array('source-posts', $sourceScopeAccessibility['body:1:0:0']['headers'] ?? [], true)) {
        throw new RuntimeException('Table geometry self-test treated source scope=row as rowgroup across rowspan');
    }
    if (!str_contains($blocks, '<th id="source-posts" scope="row" rowspan="2" style="text-align:left">Posts</th><td headers="legacy-count source-posts" style="text-align:right">42</td><td headers="source-state source-posts" style="text-align:center">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing source scope and headers WordPress output');
    }
    if (!str_contains($blocks, '<tr><td headers="source-count" style="text-align:right">7</td><td headers="source-state" style="text-align:center">Review</td></tr>')) {
        throw new RuntimeException('Table geometry self-test missing source scoped second-row headers output');
    }

    $nestedPacket = TableGeometry::reviewPacket($document->children[8], ['idPrefix' => 'Nested Packet']);
    if (($nestedPacket['summary']['nestedTableCount'] ?? null) !== 1 || ($nestedPacket['summary']['nestedTableCellCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing nested table summary counts');
    }
    if (($nestedPacket['sections'][1]['summary']['nestedTableCount'] ?? null) !== 1 || ($nestedPacket['sections'][1]['summary']['nestedTableCellCount'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing body-local nested table summary counts');
    }
    if (($nestedPacket['sections'][0]['summary']['hasNestedTables'] ?? null) !== false || ($nestedPacket['sections'][1]['summary']['hasNestedTables'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing per-section nested table flags');
    }
    if (($nestedPacket['sections'][1]['summary']['nestedTableCaptions'] ?? null) !== ['Nested queue audit']) {
        throw new RuntimeException('Table geometry self-test missing per-section nested table captions');
    }
    if (($nestedPacket['coverage'][2]['nestedTables'][0]['caption'] ?? null) !== 'Nested queue audit') {
        throw new RuntimeException('Table geometry self-test missing nested table caption rollup');
    }
    if (($nestedPacket['coverage'][2]['nestedTables'][0]['cellCount'] ?? null) !== 2) {
        throw new RuntimeException('Table geometry self-test missing nested table cell-count rollup');
    }
    json_encode($nestedPacket, JSON_THROW_ON_ERROR);
    $asciidocNestedRequirements = TableGeometry::writerDowngradeDiagnostics($document->children[8], 'asciidoctor');
    if (
        array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocNestedRequirements) !== ['asciidoc-nested-table-raw-html-required']
        || ($asciidocNestedRequirements[0]['requiredFeature'] ?? null) !== 'raw-html-table-passthrough'
        || ($asciidocNestedRequirements[0]['nestedTableCaptions'] ?? null) !== ['Nested queue audit']
    ) {
        throw new RuntimeException('Table geometry self-test missing AsciiDoc nested-table writer requirement diagnostics');
    }
    $asciidocNestedPacket = TableGeometry::reviewPacket($document->children[8], [
        'idPrefix' => 'Nested Packet',
        'writers' => ['markdown', 'asciidoc'],
    ]);
    if (
        ($asciidocNestedPacket['summary']['writerDowngradeCount'] ?? null) !== 1
        || ($asciidocNestedPacket['summary']['writerDowngradeCodes'] ?? null) !== ['asciidoc-nested-table-raw-html-required']
        || ($asciidocNestedPacket['summary']['writerDowngradeWriters'] ?? null) !== ['asciidoc']
    ) {
        throw new RuntimeException('Table geometry self-test missing AsciiDoc nested-table review-packet summary');
    }
    json_encode($asciidocNestedPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Nested table packet review</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing nested table packet WordPress output');
    }

    $sourceShiftTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'implicit-source-shift-grid') {
            $sourceShiftTable = $node;
            break;
        }
    }
    $sourceShiftPacket = $sourceShiftTable instanceof AstNode ? TableGeometry::reviewPacket($sourceShiftTable, ['accessibility' => false]) : null;
    if (
        !is_array($sourceShiftPacket)
        || ($sourceShiftPacket['summary']['hasSourceCoordinateShifts'] ?? null) !== true
        || ($sourceShiftPacket['summary']['sourceCoordinateShiftCount'] ?? null) !== 2
        || ($sourceShiftPacket['summary']['maxVisualShift'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual shift summary');
    }
    if (
        ($sourceShiftPacket['coverage'][1]['sourceColumns'] ?? null) !== [0]
        || ($sourceShiftPacket['coverage'][1]['visualShift'] ?? null) !== 2
        || ($sourceShiftPacket['coverage'][2]['sourceEndColumn'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual shift coverage metadata');
    }
    if (($sourceShiftPacket['summary']['diagnosticCodes'] ?? null) !== []) {
        throw new RuntimeException('Table geometry self-test incorrectly diagnosed normalized implicit source shifts');
    }
    if (!str_contains($blocks, '<table id="implicit-source-shift-grid"><tbody><tr><td colspan="2" rowspan="2">Merged source</td></tr><tr><td>Unexpected source cell</td><td>Second conflict</td></tr></tbody></table>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for implicit source shift table');
    }
    json_encode($sourceShiftPacket, JSON_THROW_ON_ERROR);

    $rowspanZeroTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'rowspan-zero-grid') {
            $rowspanZeroTable = $node;
            break;
        }
    }
    $rowspanZeroPacket = $rowspanZeroTable instanceof AstNode ? $rowspanZeroTable->attr('tableGeometry') : null;
    if (!$rowspanZeroTable instanceof AstNode || TableGeometry::columnCount($rowspanZeroTable) !== 3 || $rowspanZeroTable->attr('widths') !== [1 / 3, 1 / 3, 1 / 3]) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero visual column normalization');
    }
    if (!is_array($rowspanZeroPacket) || ($rowspanZeroPacket['coverage'][0]['rowspan'] ?? null) !== 3 || ($rowspanZeroPacket['coverage'][0]['rowspanToEnd'] ?? null) !== true) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero coverage packet');
    }
    if (($rowspanZeroPacket['sections'][1]['summary']['coveredSlotCount'] ?? null) !== 2 || ($rowspanZeroPacket['sections'][2]['summary']['coveredSlotCount'] ?? null) !== 0) {
        throw new RuntimeException('Table geometry self-test let HTML rowspan-zero cross tbody boundaries');
    }
    if (($rowspanZeroPacket['summary']['bodyGroupCount'] ?? null) !== 2 || ($rowspanZeroPacket['summary']['hasMultipleBodyGroups'] ?? null) !== true || ($rowspanZeroPacket['summary']['bodyRowCount'] ?? null) !== 4) {
        throw new RuntimeException('Table geometry self-test missing HTML row-group summary');
    }
    if (($rowspanZeroPacket['rowGroups'][1]['sourceAttributes']['id'] ?? null) !== 'posts-body' || ($rowspanZeroPacket['rowGroups'][2]['sourceAttributes']['id'] ?? null) !== 'pages-body') {
        throw new RuntimeException('Table geometry self-test missing HTML row-group source attributes');
    }
    if (($rowspanZeroPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-rowspan-flattened']) {
        throw new RuntimeException('Table geometry self-test missing HTML rowspan-zero Markdown downgrade packet');
    }
    if (!str_contains($blocks, '<tbody id="posts-body"><tr data-row="posts-total"><th rowspan="3" style="text-align:left">Posts</th><td style="text-align:right">42</td></tr><tr data-row="posts-media"><td style="text-align:right">7</td><td>Needs media</td></tr><tr data-row="posts-review"><td style="text-align:right">3</td><td>Review</td></tr></tbody><tbody id="pages-body"><tr data-row="pages-total"><th>Pages</th><td style="text-align:right">5</td><td>Ready</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing finite WordPress rowspan output for HTML rowspan-zero');
    }
    json_encode($rowspanZeroPacket, JSON_THROW_ON_ERROR);

    $colgroupAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'colgroup-alignment-grid') {
            $colgroupAlignmentTable = $node;
            break;
        }
    }
    $colgroupAlignmentPacket = $colgroupAlignmentTable instanceof AstNode ? $colgroupAlignmentTable->attr('tableGeometry') : null;
    if (
        !$colgroupAlignmentTable instanceof AstNode
        || $colgroupAlignmentTable->attr('alignments') !== ['right', 'right', 'center']
        || $colgroupAlignmentTable->attr('widths') !== [0.25, 0.25, 0.5]
        || ($colgroupAlignmentTable->children[0]->children[0]->children[0]->attr('valign') ?? null) !== 'bottom'
        || ($colgroupAlignmentTable->children[1]->children[0]->children[1]->attr('valign') ?? null) !== 'bottom'
        || ($colgroupAlignmentTable->children[1]->children[1]->children[2]->attr('valign') ?? null) !== 'top'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML colgroup span alignment width and vertical alignment expansion');
    }
    $columnSources = $colgroupAlignmentTable->attr('columnSources');
    if (!is_array($columnSources) || ($columnSources[1]['spanOffset'] ?? null) !== 1 || ($columnSources[2]['colIndex'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing HTML colgroup span provenance metadata');
    }
    if (
        ($columnSources[0]['colgroupAttributes']['htmlAttributes']['data-source'] ?? null) !== 'legacy-doc'
        || ($columnSources[0]['verticalAlignment'] ?? null) !== 'bottom'
        || ($columnSources[2]['verticalAlignment'] ?? null) !== 'top'
        || ($columnSources[2]['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-b'
    ) {
        throw new RuntimeException('Table geometry self-test missing source colgroup/col attributes or vertical alignment in provenance metadata');
    }
    if (!is_array($colgroupAlignmentPacket) || ($colgroupAlignmentPacket['coverage'][4]['columnAlignments'] ?? null) !== ['right'] || ($colgroupAlignmentPacket['coverage'][4]['verticalAlignment'] ?? null) !== 'bottom' || ($colgroupAlignmentPacket['coverage'][5]['widths'] ?? null) !== [0.5] || ($colgroupAlignmentPacket['coverage'][5]['verticalAlignment'] ?? null) !== 'top') {
        throw new RuntimeException('Table geometry self-test missing colgroup metadata in review-packet coverage');
    }
    if (($colgroupAlignmentPacket['columns'][1]['source']['spanOffset'] ?? null) !== 1 || ($colgroupAlignmentPacket['coverage'][5]['columnSources'][0]['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-b') {
        throw new RuntimeException('Table geometry self-test missing colgroup provenance in review-packet columns and coverage');
    }
    if (
        count($colgroupAlignmentPacket['columnGroups'] ?? []) !== 2
        || ($colgroupAlignmentPacket['columnGroups'][0]['columns'] ?? null) !== [0, 1]
        || ($colgroupAlignmentPacket['columnGroups'][0]['spanOffsets'] ?? null) !== [0, 1]
        || ($colgroupAlignmentPacket['columnGroups'][0]['source']['verticalAlignment'] ?? null) !== 'bottom'
        || ($colgroupAlignmentPacket['columnGroups'][0]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'col-a'
        || ($colgroupAlignmentPacket['columnGroups'][1]['columns'] ?? null) !== [2]
        || ($colgroupAlignmentPacket['columnGroups'][1]['source']['verticalAlignment'] ?? null) !== 'top'
        || ($colgroupAlignmentPacket['summary']['columnGroupCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing grouped colgroup source-span metadata');
    }
    if (!str_contains($blocks, '<table id="colgroup-alignment-grid" data-source="html-reader"><colgroup><col style="width:25%"/><col style="width:25%"/><col style="width:50%"/></colgroup><thead><tr><th style="text-align:right; vertical-align:bottom">Scope</th><th style="text-align:right; vertical-align:bottom">Items</th><th style="text-align:center; vertical-align:top">State</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for expanded colgroup alignment metadata');
    }
    json_encode($colgroupAlignmentPacket, JSON_THROW_ON_ERROR);

    $colgroupMismatchTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'colgroup-underdeclared-grid') {
            $colgroupMismatchTable = $node;
            break;
        }
    }
    $colgroupMismatchPacket = $colgroupMismatchTable instanceof AstNode ? $colgroupMismatchTable->attr('tableGeometry') : null;
    if (
        !$colgroupMismatchTable instanceof AstNode
        || $colgroupMismatchTable->attr('alignments') !== ['right', 'right', 'default']
        || $colgroupMismatchTable->attr('widths') !== [0.2, 0.2, null]
    ) {
        throw new RuntimeException('Table geometry self-test missing underdeclared HTML colgroup partial metadata');
    }
    if (
        !is_array($colgroupMismatchPacket)
        || ($colgroupMismatchPacket['summary']['diagnosticCodes'] ?? null) !== ['html-colgroup-underdeclares-columns']
        || ($colgroupMismatchPacket['diagnostics'][0]['missingColumns'] ?? null) !== [2]
    ) {
        throw new RuntimeException('Table geometry self-test missing underdeclared HTML colgroup diagnostics');
    }
    if (($colgroupMismatchPacket['columns'][1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null) !== 'declared-pair') {
        throw new RuntimeException('Table geometry self-test missing partial colgroup source provenance');
    }
    if (isset($colgroupMismatchPacket['columns'][2]['source']) || isset($colgroupMismatchPacket['coverage'][5]['columnSources'])) {
        throw new RuntimeException('Table geometry self-test leaked partial colgroup provenance into missing source columns');
    }
    if (str_contains($blocks, '<table id="colgroup-underdeclared-grid" data-source="html-reader"><colgroup>')) {
        throw new RuntimeException('Table geometry self-test emitted a misleading colgroup for incomplete source widths');
    }
    if (!str_contains($blocks, '<table id="colgroup-underdeclared-grid" data-source="html-reader"><thead><tr><th style="text-align:right">Scope</th><th style="text-align:right">Items</th><th>State</th></tr></thead>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for underdeclared colgroup metadata');
    }
    json_encode($colgroupMismatchPacket, JSON_THROW_ON_ERROR);

    $inheritedAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'inherited-alignment-grid') {
            $inheritedAlignmentTable = $node;
            break;
        }
    }
    $inheritedAlignmentPacket = $inheritedAlignmentTable instanceof AstNode ? $inheritedAlignmentTable->attr('tableGeometry') : null;
    if (
        !$inheritedAlignmentTable instanceof AstNode
        || $inheritedAlignmentTable->attr('alignments') !== ['default', 'default', 'default']
        || ($inheritedAlignmentTable->children[0]->children[0]->children[0]->attr('align') ?? null) !== 'center'
        || ($inheritedAlignmentTable->children[1]->children[0]->children[1]->attr('align') ?? null) !== 'right'
        || ($inheritedAlignmentTable->children[1]->children[1]->children[2]->attr('align') ?? null) !== 'left'
        || ($inheritedAlignmentTable->children[2]->children[0]->children[2]->attr('align') ?? null) !== 'center'
    ) {
        throw new RuntimeException('Table geometry self-test missing inherited HTML row group and row alignment metadata');
    }
    if (
        !is_array($inheritedAlignmentPacket)
        || array_map(static fn (array $coverage): string => (string) ($coverage['alignment'] ?? ''), $inheritedAlignmentPacket['coverage'] ?? []) !== [
            'center',
            'right',
            'center',
            'right',
            'right',
            'center',
            'left',
            'left',
            'left',
            'center',
            'center',
            'center',
        ]
        || ($inheritedAlignmentPacket['coverage'][3]['headerCell'] ?? null) !== true
        || ($inheritedAlignmentPacket['coverage'][3]['rowHeadColumns'] ?? null) !== 1
    ) {
        throw new RuntimeException('Table geometry self-test missing inherited alignment review-packet coverage');
    }
    if (!str_contains($blocks, '<table id="inherited-alignment-grid" data-source="html-reader"><colgroup><col style="width:33.3333%"/><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><thead><tr><th style="text-align:center">Scope</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead><tbody data-section="body"><tr data-row="posts"><th style="text-align:right">Posts</th><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr data-row="media"><th style="text-align:left">Media</th><td style="text-align:left">7</td><td style="text-align:left">Review</td></tr></tbody><tfoot><tr><td style="text-align:center">Total</td><td style="text-align:center">49</td><td style="text-align:center">Review</td></tr></tfoot></table>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for inherited HTML alignment handoff');
    }
    json_encode($inheritedAlignmentPacket, JSON_THROW_ON_ERROR);

    $verticalAlignmentTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('id') === 'vertical-alignment-grid') {
            $verticalAlignmentTable = $node;
            break;
        }
    }
    $verticalAlignmentPacket = $verticalAlignmentTable instanceof AstNode ? $verticalAlignmentTable->attr('tableGeometry') : null;
    if (
        !$verticalAlignmentTable instanceof AstNode
        || ($verticalAlignmentTable->children[0]->children[0]->children[0]->attr('valign') ?? null) !== 'top'
        || ($verticalAlignmentTable->children[0]->children[0]->children[1]->attr('valign') ?? null) !== 'bottom'
        || ($verticalAlignmentTable->children[1]->children[0]->children[0]->attr('valign') ?? null) !== 'middle'
        || ($verticalAlignmentTable->children[1]->children[0]->children[1]->attr('valign') ?? null) !== 'baseline'
    ) {
        throw new RuntimeException('Table geometry self-test missing HTML vertical alignment metadata');
    }
    if (
        !is_array($verticalAlignmentPacket)
        || array_map(static fn (array $coverage): string => (string) ($coverage['verticalAlignment'] ?? ''), $verticalAlignmentPacket['coverage'] ?? []) !== [
            'top',
            'bottom',
            'middle',
            'baseline',
            'top',
            'top',
        ]
        || ($verticalAlignmentPacket['sections'][1]['rows'][0]['slots'][1]['verticalAlignment'] ?? null) !== 'baseline'
    ) {
        throw new RuntimeException('Table geometry self-test missing vertical alignment review-packet coverage');
    }
    if (!str_contains($blocks, '<thead valign="top"><tr><th style="vertical-align:top">Scope</th><th style="vertical-align: bottom">State</th></tr></thead>') || !str_contains($blocks, '<tbody data-section="body" valign="baseline"><tr><td valign="middle">Posts</td><td style="vertical-align:baseline">Ready</td></tr><tr style="vertical-align: top"><td style="vertical-align:top">Total</td><td style="vertical-align:top">Review</td></tr></tbody>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for vertical alignment handoff');
    }
    json_encode($verticalAlignmentPacket, JSON_THROW_ON_ERROR);

    $readerTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('caption') === 'Reader packet import metrics') {
            $readerTable = $node;
            break;
        }
    }
    $readerPacket = $readerTable instanceof AstNode ? $readerTable->attr('tableGeometry') : null;
    if (!is_array($readerPacket) || ($readerPacket['summary']['cellCount'] ?? null) !== 9 || ($readerPacket['coverage'][6]['text'] ?? null) !== 'Media') {
        throw new RuntimeException('Table geometry self-test missing Markdown reader attached review packet');
    }
    json_encode($readerPacket, JSON_THROW_ON_ERROR);
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Reader packet import metrics</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing Markdown reader table WordPress output');
    }

    $captionMetadataTable = null;
    foreach ($document->children as $node) {
        if ($node->type === 'table' && $node->attr('shortCaption') === 'Queue short') {
            $captionMetadataTable = $node;
            break;
        }
    }
    $captionPacket = $captionMetadataTable instanceof AstNode ? TableGeometry::reviewPacket($captionMetadataTable, ['accessibility' => false]) : null;
    if (
        !is_array($captionPacket)
        || ($captionPacket['captions']['long']['inlineTypes'] ?? null) !== ['text', 'emph', 'link']
        || ($captionPacket['captions']['short']['inlineTypes'] ?? null) !== ['text', 'strong']
        || ($captionPacket['summary']['hasShortCaption'] ?? null) !== true
    ) {
        throw new RuntimeException('Table geometry self-test missing long/short caption review-packet metadata');
    }
    if (($captionPacket['captions']['long']['inlines'][3]['url'] ?? null) !== 'https://example.test/review') {
        throw new RuntimeException('Table geometry self-test missing caption inline link metadata');
    }
    if (!str_contains($blocks, '<figure class="wp-block-table" data-pandoc-short-caption="Queue short"><table><thead><tr><th style="text-align:left">Scope</th><th style="text-align:right">State</th></tr></thead><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">Ready</td></tr></tbody></table><figcaption class="wp-element-caption">Long <em>caption</em> for <a href="https://example.test/review" title="Review">reviewer</a></figcaption></figure>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for caption metadata handoff');
    }
    json_encode($captionPacket, JSON_THROW_ON_ERROR);

    $blockCaptionPacket = TableGeometry::reviewPacket($blockCaptionTable, ['accessibility' => false]);
    if (
        ($blockCaptionPacket['captions']['long']['source'] ?? null) !== 'captionBlocks'
        || ($blockCaptionPacket['captions']['long']['blockTypes'] ?? null) !== ['paragraph', 'bullet_list']
        || ($blockCaptionPacket['summary']['hasCaptionBlocks'] ?? null) !== true
        || ($blockCaptionPacket['summary']['captionBlockCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level caption review-packet metadata');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption"><p>Block <strong>caption</strong> for reviewer</p><ul><li>Queue note</li></ul></figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for block-level table caption');
    }
    json_encode($blockCaptionPacket, JSON_THROW_ON_ERROR);

    $malformedSpanPacket = TableGeometry::reviewPacket($malformedSpanTable, ['accessibility' => false]);
    if (
        ($malformedSpanPacket['summary']['diagnosticCodes'] ?? null) !== ['cell-span-normalized']
        || ($malformedSpanPacket['summary']['hasNormalizedSpans'] ?? null) !== true
        || ($malformedSpanPacket['summary']['normalizedSpanCount'] ?? null) !== 3
    ) {
        throw new RuntimeException('Table geometry self-test missing malformed source span normalization diagnostics');
    }
    if (
        array_map(static fn (array $diagnostic): string => (string) $diagnostic['attribute'], $malformedSpanPacket['diagnostics'] ?? []) !== ['colspan', 'rowspan', 'rowspan']
        || array_map(static fn (array $diagnostic): mixed => $diagnostic['rawValue'] ?? null, $malformedSpanPacket['diagnostics'] ?? []) !== ['0', 'many', -3]
    ) {
        throw new RuntimeException('Table geometry self-test missing raw malformed source span values');
    }
    if (($malformedSpanPacket['coverage'][0]['colspan'] ?? null) !== 1 || ($malformedSpanPacket['coverage'][0]['rowspan'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing normalized malformed source span coverage');
    }
    if (!str_contains($blocks, '<table id="malformed-source-span-grid"><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:left">Media</td><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr></tbody></table>')) {
        throw new RuntimeException('Table geometry self-test missing normalized WordPress output for malformed source spans');
    }
    if (str_contains($blocks, 'colspan="0"') || str_contains($blocks, 'rowspan="-3"')) {
        throw new RuntimeException('Table geometry self-test leaked malformed span attributes into WordPress output');
    }
    json_encode($malformedSpanPacket, JSON_THROW_ON_ERROR);

    $blockContentPacket = TableGeometry::reviewPacket($blockContentTable, [
        'accessibility' => false,
        'writers' => ['markdown', 'asciidoc'],
    ]);
    if (
        ($blockContentPacket['summary']['hasBlockContentCells'] ?? null) !== true
        || ($blockContentPacket['summary']['blockContentCellCount'] ?? null) !== 1
        || ($blockContentPacket['summary']['multiBlockCellCount'] ?? null) !== 1
        || ($blockContentPacket['summary']['cellBlockTypes'] ?? null) !== ['paragraph', 'bullet_list']
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level table cell review metadata');
    }
    if (
        ($blockContentPacket['coverage'][2]['content']['blockTypes'] ?? null) !== ['paragraph', 'bullet_list']
        || ($blockContentPacket['sections'][1]['rows'][0]['slots'][0]['content']['blockCount'] ?? null) !== 2
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level table cell coverage metadata');
    }
    if (
        ($blockContentPacket['summary']['writerDowngradeCodes'] ?? null) !== ['markdown-cell-blocks-flattened', 'asciidoc-block-cell-required']
        || ($blockContentPacket['writerDowngrades']['markdown'][0]['requiredFeature'] ?? null) !== 'multiline-or-grid-table-cell'
        || ($blockContentPacket['writerDowngrades']['asciidoc'][0]['requiredFeature'] ?? null) !== 'asciidoc-block-cell'
    ) {
        throw new RuntimeException('Table geometry self-test missing block-level cell writer handoff diagnostics');
    }
    if (!str_contains($blocks, '<td style="text-align:left"><p>Review <em>source</em></p><ul><li>Image alt text</li><li><strong>Resolve captions</strong></li></ul></td><td style="text-align:right">Ready</td>')) {
        throw new RuntimeException('Table geometry self-test missing WordPress output for block-level table cell content');
    }
    json_encode($blockContentPacket, JSON_THROW_ON_ERROR);

    $latexRequirementPacket = TableGeometry::reviewPacket($latexRequirementTable, [
        'accessibility' => false,
        'writers' => ['latex'],
    ]);
    if (
        ($latexRequirementPacket['summary']['writerDowngradeCodes'] ?? null) !== [
            'latex-multicolumn-required',
            'latex-multirow-required',
            'latex-cell-block-required',
            'latex-nested-table-required',
        ]
        || ($latexRequirementPacket['writerDowngrades']['latex'][3]['requiredFeature'] ?? null) !== 'parbox-or-minipage-cell'
        || ($latexRequirementPacket['writerDowngrades']['latex'][4]['requiredFeature'] ?? null) !== 'nested-tabular-minipage'
        || ($latexRequirementPacket['writerDowngrades']['latex'][4]['nestedTableCaptions'] ?? null) !== ['Nested LaTeX audit']
    ) {
        throw new RuntimeException('Table geometry self-test missing LaTeX table writer requirement diagnostics');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">LaTeX table requirement audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing LaTeX requirement review table output');
    }
    json_encode($latexRequirementPacket, JSON_THROW_ON_ERROR);

    $latexFooterPacket = TableGeometry::reviewPacket($latexFooterTable, [
        'accessibility' => false,
        'writers' => ['latex'],
    ]);
    if (
        ($latexFooterPacket['summary']['writerDowngradeCodes'] ?? null) !== ['latex-longtable-footer-required']
        || ($latexFooterPacket['writerDowngrades']['latex'][0]['requiredFeature'] ?? null) !== 'longtable-footer'
        || ($latexFooterPacket['writerDowngrades']['latex'][0]['footRowCount'] ?? null) !== 1
        || ($latexFooterPacket['writerDowngrades']['latex'][0]['sections'] ?? null) !== [
            ['section' => 'head', 'rowCount' => 1, 'rowRole' => 'head'],
            ['section' => 'body', 'rowCount' => 1, 'rowRole' => 'body'],
            ['section' => 'foot', 'rowCount' => 1, 'rowRole' => 'foot'],
        ]
    ) {
        throw new RuntimeException('Table geometry self-test missing LaTeX longtable footer writer requirement diagnostics');
    }
    if (!str_contains($blocks, '<tfoot><tr><td style="text-align:left">Total</td><td style="text-align:right">Ready</td></tr></tfoot></table><figcaption class="wp-element-caption">LaTeX footer audit</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing LaTeX footer review table output');
    }
    json_encode($latexFooterPacket, JSON_THROW_ON_ERROR);

    echo "table geometry handoff self-test ok\n";
    return;
}

echo $blocks . "\n";
