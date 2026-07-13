<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
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

$buildPandocAlignmentAliasDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Pandoc alignment constructor audit',
            'alignments' => ['AlignLeft', 'AlignRight', 'AlignCenter', 'AlignDefault'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Field'], [new AstNode('text', ['text' => 'Field'])]),
                    new AstNode('table_cell', ['text' => 'Count'], [new AstNode('text', ['text' => 'Count'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                    new AstNode('table_cell', ['text' => 'Notes'], [new AstNode('text', ['text' => 'Notes'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                    new AstNode('table_cell', ['text' => 'Needs alt text', 'align' => 'align-right'], [new AstNode('text', ['text' => 'Needs alt text'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildOverfullColumnWidthDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
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
        ]),
    ]);
};

$buildUnderfullColumnWidthDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
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
        ]),
    ]);
};

$buildInvalidColumnWidthDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
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

$buildAttributedCellDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Source attributed grid',
            'alignments' => ['left', 'right'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Source Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
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
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
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
    ]);
};

$buildAbbreviatedHeaderDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Abbreviated header review grid',
            'alignments' => ['left', 'right'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Abbr Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Document',
                        'htmlAttributes' => [
                            'id' => 'source-document',
                            'abbr' => 'Doc',
                        ],
                    ], [new AstNode('text', ['text' => 'Document'])]),
                    new AstNode('table_cell', [
                        'text' => 'Status',
                        'attributes' => [
                            'abbr' => 'St',
                        ],
                    ], [new AstNode('text', ['text' => 'Status'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Migration packet'], [new AstNode('text', ['text' => 'Migration packet'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSourceScopedHeaderDocument = static function (): AstNode {
    return new AstNode('document', [], [
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
    ]);
};

$buildSourceHeaderReferenceGeometryDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Source header reference geometry audit',
            'alignments' => ['left', 'right', 'center'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Reference Geometry Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Migration scope',
                        'colspan' => 2,
                        'htmlAttributes' => [
                            'id' => 'source-scope-span',
                            'scope' => 'colgroup',
                        ],
                    ], [new AstNode('text', ['text' => 'Migration scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'htmlAttributes' => [
                            'id' => 'source-state-span',
                            'scope' => 'col',
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
                            'id' => 'source-posts-group',
                            'scope' => 'rowgroup',
                        ],
                    ], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', [
                        'text' => '42',
                        'htmlAttributes' => [
                            'headers' => 'source-scope-span source-posts-group',
                        ],
                    ], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', [
                        'text' => 'Ready',
                        'htmlAttributes' => [
                            'headers' => 'source-state-span source-posts-group',
                        ],
                    ], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => '7',
                        'htmlAttributes' => [
                            'headers' => 'source-scope-span source-posts-group',
                        ],
                    ], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', [
                        'text' => 'Review',
                        'htmlAttributes' => [
                            'headers' => 'source-state-span source-posts-group',
                        ],
                    ], [new AstNode('text', ['text' => 'Review'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildInvalidSourceScopeDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Invalid source scope accessibility grid',
            'alignments' => ['left', 'right'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Invalid Scope Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Document',
                        'htmlAttributes' => [
                            'id' => 'invalid-scope-document',
                            'scope' => 'columnish',
                        ],
                    ], [new AstNode('text', ['text' => 'Document'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'htmlAttributes' => [
                            'id' => 'valid-scope-state',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSourceRowgroupHeaderDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Source rowgroup accessibility grid',
            'alignments' => ['left', 'right', 'center'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Source Rowgroup Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Scope',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-rg-scope',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'Count',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-rg-count',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Count'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-rg-state',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'media-body',
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Media',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-media-group',
                            'scope' => 'rowgroup',
                        ],
                    ], [new AstNode('text', ['text' => 'Media'])]),
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', ['text' => 'Needs alt'], [new AstNode('text', ['text' => 'Needs alt'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Images'], [new AstNode('text', ['text' => 'Images'])]),
                    new AstNode('table_cell', ['text' => '3'], [new AstNode('text', ['text' => '3'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'pages-body',
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                    new AstNode('table_cell', ['text' => '5'], [new AstNode('text', ['text' => '5'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildSourceColgroupHeaderDocument = static function (): AstNode {
    $importColumnGroupAttributes = [
        'htmlAttributes' => [
            'id' => 'source-import-columns',
            'data-origin' => 'legacy-doc',
        ],
    ];
    $stateColumnGroupAttributes = [
        'htmlAttributes' => [
            'id' => 'source-state-column',
            'data-origin' => 'legacy-doc',
        ],
    ];

    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Source colgroup accessibility grid',
            'alignments' => ['left', 'right', 'center'],
            'widths' => [1 / 3, 1 / 3, 1 / 3],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Source Colgroup Grid',
            'columnSources' => [
                [
                    'kind' => 'colgroup',
                    'column' => 0,
                    'colgroupIndex' => 0,
                    'sourceSpan' => 2,
                    'spanOffset' => 0,
                    'colgroupAttributes' => $importColumnGroupAttributes,
                ],
                [
                    'kind' => 'colgroup',
                    'column' => 1,
                    'colgroupIndex' => 0,
                    'sourceSpan' => 2,
                    'spanOffset' => 1,
                    'colgroupAttributes' => $importColumnGroupAttributes,
                ],
                [
                    'kind' => 'colgroup',
                    'column' => 2,
                    'colgroupIndex' => 1,
                    'sourceSpan' => 1,
                    'spanOffset' => 0,
                    'colgroupAttributes' => $stateColumnGroupAttributes,
                ],
            ],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Import scope',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-import-scope',
                            'scope' => 'colgroup',
                        ],
                    ], [new AstNode('text', ['text' => 'Import scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'Items',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-items',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Items'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-state',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildDuplicateSourceHeaderDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Duplicate source header id audit',
            'alignments' => ['left', 'right', 'center'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Duplicate Header Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Document A',
                        'htmlAttributes' => [
                            'id' => 'duplicate-document',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Document A'])]),
                    new AstNode('table_cell', [
                        'text' => 'Document B',
                        'htmlAttributes' => [
                            'id' => 'duplicate-document',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Document B'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'htmlAttributes' => [
                            'id' => 'duplicate-state',
                            'scope' => 'col',
                            'headers' => 'duplicate-document',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', [
                        'text' => '42',
                        'htmlAttributes' => [
                            'headers' => 'duplicate-document missing-document',
                        ],
                    ], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildDuplicateSourceHeaderTokenDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Duplicate source headers token audit',
            'alignments' => ['left', 'right', 'center'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Duplicate Token Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Document',
                        'htmlAttributes' => [
                            'id' => 'dup-token-document',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Document'])]),
                    new AstNode('table_cell', [
                        'text' => 'Count',
                        'htmlAttributes' => [
                            'id' => 'dup-token-count',
                            'scope' => 'col',
                        ],
                    ], [new AstNode('text', ['text' => 'Count'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'htmlAttributes' => [
                            'id' => 'dup-token-state',
                            'scope' => 'col',
                            'headers' => 'dup-token-document dup-token-document',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', [
                        'text' => '42',
                        'htmlAttributes' => [
                            'headers' => 'dup-token-document dup-token-count dup-token-count',
                        ],
                    ], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
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

$buildImplicitColumnRowspanOverlapDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Implicit column overlap review',
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

$buildSourceAttributedReviewPacketDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Attributed source audit',
            'alignments' => ['left', 'right'],
            'id' => 'source-audit',
            'classes' => ['wp-import', 'needs-review'],
            'attributes' => [
                'origin' => 'html-reader',
                'batch' => '42',
            ],
            'htmlAttributes' => [
                'id' => 'source-audit',
                'class' => 'wp-import needs-review',
                'data-origin' => 'html-reader',
                'aria-label' => 'Source audit table',
            ],
        ], [
            new AstNode('table_head', [
                'htmlAttributes' => [
                    'id' => 'source-head',
                    'data-section' => 'thead',
                ],
            ], [
                new AstNode('table_row', [
                    'htmlAttributes' => [
                        'data-row' => 'head-1',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Scope',
                        'header' => true,
                        'htmlAttributes' => [
                            'id' => 'source-scope',
                            'class' => 'review-header',
                            'data-origin' => 'docx',
                        ],
                    ], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'header' => true,
                        'attributes' => [
                            'source' => 'manual',
                        ],
                        'htmlAttributes' => [
                            'data-origin' => 'manual',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'source-body',
                    'data-section' => 'tbody',
                ],
            ], [
                new AstNode('table_row', [
                    'htmlAttributes' => [
                        'data-row' => 'body-1',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Posts',
                        'htmlAttributes' => [
                            'title' => 'Imported posts',
                            'data-origin' => 'docx',
                        ],
                    ], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildAstAttributeHandoffDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Native attribute audit',
            'alignments' => ['left', 'right'],
            'id' => 'native-attr-table',
            'classes' => ['source-table', 'needs-review'],
            'attributes' => [
                'data-pandoc-source' => 'native-ast',
                'aria-label' => 'Pandoc section attribute audit',
                'data-html-overlap' => 'from-attr',
                'onclick' => 'blocked',
            ],
            'htmlAttributes' => [
                'data-html-overlap' => 'from-html',
            ],
        ], [
            new AstNode('table_head', [
                'id' => 'native-head',
                'classes' => ['table-source-head'],
                'attributes' => [
                    'data-section-role' => 'head',
                    'aria-label' => 'Head rows',
                    'onmouseover' => 'blocked',
                ],
            ], [
                new AstNode('table_row', [
                    'id' => 'native-head-row',
                    'classes' => ['source-row'],
                    'attributes' => [
                        'data-row-role' => 'head',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Scope',
                        'id' => 'native-scope',
                        'classes' => ['source-cell'],
                        'attributes' => [
                            'data-field' => 'scope',
                            'aria-sort' => 'ascending',
                            'onclick' => 'blocked',
                        ],
                    ], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'attributes' => [
                            'data-field' => 'state',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'id' => 'native-body',
                'classes' => ['table-source-body'],
                'rowHeadColumns' => 1,
                'attributes' => [
                    'data-section-role' => 'body',
                ],
            ], [
                new AstNode('table_row', [
                    'id' => 'native-body-row',
                    'attributes' => [
                        'data-row-role' => 'body',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Posts',
                        'attributes' => [
                            'data-field' => 'posts',
                        ],
                    ], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', [
                        'text' => 'Ready',
                        'attributes' => [
                            'data-field' => 'ready',
                            'aria-label' => 'Ready state',
                        ],
                    ], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
            new AstNode('table_foot', [
                'id' => 'native-foot',
                'attributes' => [
                    'data-section-role' => 'foot',
                ],
            ], [
                new AstNode('table_row', [
                    'attributes' => [
                        'data-row-role' => 'foot',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Total',
                        'attributes' => [
                            'data-field' => 'total',
                        ],
                    ], [new AstNode('text', ['text' => 'Total'])]),
                    new AstNode('table_cell', [
                        'text' => 'Ready',
                        'attributes' => [
                            'data-field' => 'foot-state',
                        ],
                    ], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]),
    ]);
};

$buildMalformedSpanNormalizationDocument = static function (): AstNode {
    return new AstNode('document', [], [
        new AstNode('table', [
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
        ]),
    ]);
};

$buildColgroupWriterHandoffDocument = static function (): AstNode {
    $colgroupAttributes = [
        'htmlAttributes' => [
            'data-source' => 'legacy-doc',
            'data-review' => 'import',
        ],
    ];

    return new AstNode('document', [], [
        new AstNode('table', [
            'caption' => 'Column source writer audit',
            'alignments' => ['right', 'right', 'center'],
            'widths' => [0.25, 0.25, 0.5],
            'columnSources' => [
                [
                    'column' => 0,
                    'kind' => 'col',
                    'colgroupIndex' => 0,
                    'colIndex' => 0,
                    'sourceSpan' => 2,
                    'spanOffset' => 0,
                    'verticalAlignment' => 'bottom',
                    'colgroupAttributes' => $colgroupAttributes,
                    'colAttributes' => [
                        'htmlAttributes' => [
                            'data-origin' => 'col-a',
                            'title' => 'Scope pair',
                        ],
                    ],
                ],
                [
                    'column' => 1,
                    'kind' => 'col',
                    'colgroupIndex' => 0,
                    'colIndex' => 0,
                    'sourceSpan' => 2,
                    'spanOffset' => 1,
                    'verticalAlignment' => 'bottom',
                    'colgroupAttributes' => $colgroupAttributes,
                    'colAttributes' => [
                        'htmlAttributes' => [
                            'data-origin' => 'col-a',
                            'title' => 'Scope pair',
                        ],
                    ],
                ],
                [
                    'column' => 2,
                    'kind' => 'col',
                    'colgroupIndex' => 0,
                    'colIndex' => 1,
                    'sourceSpan' => 1,
                    'spanOffset' => 0,
                    'verticalAlignment' => 'top',
                    'colgroupAttributes' => $colgroupAttributes,
                    'colAttributes' => [
                        'htmlAttributes' => [
                            'data-origin' => 'col-b',
                        ],
                    ],
                ],
            ],
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

        $t->contains('<th colspan="2" style="text-align:left">Scope</th><th style="text-align:center">Status</th>', $blocks);
        $t->contains('<tr><td rowspan="2" style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Migration review grid</figcaption>', $blocks);
    },
    'preserves pandoc table colspec columns beyond physical row cells' => static function (TestRunner $t) use ($buildColspecTableDocument): void {
        $document = $buildColspecTableDocument();
        $table = $document->children[0];
        $columnSpecs = TableGeometry::columnSpecs($table, 5);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same(3, TableGeometry::columnCountForRows($table->children[0]->children));
        $t->same(['left', 'center', 'right', 'left'], TableGeometry::alignments($table, 4));
        $t->same([0, 1, 2, 3, 4], array_map(static fn (array $spec): int => $spec['column'], $columnSpecs));
        $t->same(['left', 'center', 'right', 'left', 'default'], array_map(static fn (array $spec): string => $spec['alignment'], $columnSpecs));
        $t->same([0.2, 0.25, 0.25, 0.3, null], array_map(static fn (array $spec): ?float => $spec['width'], $columnSpecs));
        $t->same([true, true, true, true, false], array_map(static fn (array $spec): bool => $spec['declared'], $columnSpecs));
        $t->contains('<colgroup><col style="width:20%"/><col style="width:25%"/><col style="width:25%"/><col style="width:30%"/></colgroup>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Import queue with reserved audit column</figcaption>', $blocks);
    },
    'reports markdown pipe-table width approximation for explicit colspecs' => static function (TestRunner $t) use ($buildColspecTableDocument, $buildDefaultColumnSpecDocument): void {
        $table = $buildColspecTableDocument()->children[0];
        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown'],
        ]);
        $partialTable = $buildDefaultColumnSpecDocument()->children[0];

        $t->same(['markdown-column-widths-approximated'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same('markdown', $diagnostics[0]['writer'] ?? null);
        $t->same('column-widths', $diagnostics[0]['reason'] ?? null);
        $t->same('pipe-table-character-padding', $diagnostics[0]['requiredFeature'] ?? null);
        $t->same('table-widths', $diagnostics[0]['source'] ?? null);
        $t->same(4, $diagnostics[0]['columnCount'] ?? null);
        $t->same(4, $diagnostics[0]['explicitWidthCount'] ?? null);
        $t->same(4, $diagnostics[0]['validWidthCount'] ?? null);
        $t->same(0, $diagnostics[0]['missingWidthCount'] ?? null);
        $t->same([0, 1, 2, 3], $diagnostics[0]['validWidthColumns'] ?? null);
        $t->same([], $diagnostics[0]['missingColumns'] ?? null);
        $t->same(1.0, $diagnostics[0]['widthTotal'] ?? null);
        $t->same([0.2, 0.25, 0.25, 0.3], $diagnostics[0]['normalizedWidths'] ?? null);
        $t->same([20.0, 25.0, 25.0, 30.0], $diagnostics[0]['percentWidths'] ?? null);
        $t->same([8, 10, 10, 12], $diagnostics[0]['pipeCharacterWidths'] ?? null);
        $t->same(true, $diagnostics[0]['hasCompleteWidths'] ?? null);
        $t->same(false, $diagnostics[0]['hasPartialWidths'] ?? null);
        $t->same(false, $diagnostics[0]['overfull'] ?? null);
        $t->same(false, $diagnostics[0]['underfull'] ?? null);
        $t->same($diagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same(1, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['markdown-column-widths-approximated'], $packet['summary']['writerDowngradeCodes'] ?? null);

        $partialDiagnostics = TableGeometry::writerDowngradeDiagnostics($partialTable, 'markdown');
        $t->same(['markdown-column-widths-approximated'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $partialDiagnostics));
        $t->same(3, $partialDiagnostics[0]['explicitWidthCount'] ?? null);
        $t->same(2, $partialDiagnostics[0]['validWidthCount'] ?? null);
        $t->same(2, $partialDiagnostics[0]['missingWidthCount'] ?? null);
        $t->same([0, 2], $partialDiagnostics[0]['validWidthColumns'] ?? null);
        $t->same([1, 3], $partialDiagnostics[0]['missingColumns'] ?? null);
        $t->same([4, null, 18, null], $partialDiagnostics[0]['pipeCharacterWidths'] ?? null);
        $t->same(false, $partialDiagnostics[0]['hasCompleteWidths'] ?? null);
        $t->same(true, $partialDiagnostics[0]['hasPartialWidths'] ?? null);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($partialDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports colgroup provenance writer handoff diagnostics for non-html table writers' => static function (TestRunner $t) use ($buildColgroupWriterHandoffDocument): void {
        $table = $buildColgroupWriterHandoffDocument()->children[0];
        $columnGroups = TableGeometry::columnGroups($table);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);

        $t->same(2, count($columnGroups));
        $t->same([0, 1], $columnGroups[0]['columns'] ?? null);
        $t->same([0, 1], $columnGroups[0]['spanOffsets'] ?? null);
        $t->same('legacy-doc', $columnGroups[0]['source']['colgroupAttributes']['htmlAttributes']['data-source'] ?? null);
        $t->same('col-a', $columnGroups[0]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);
        $t->same([2], $columnGroups[1]['columns'] ?? null);
        $t->same('top', $columnGroups[1]['source']['verticalAlignment'] ?? null);
        $t->same('col-b', $columnGroups[1]['source']['colAttributes']['htmlAttributes']['data-origin'] ?? null);

        $t->same([
            'markdown-column-widths-approximated',
            'markdown-colgroup-provenance-require-raw-html',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(['asciidoc-colgroup-provenance-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same(['latex-colgroup-provenance-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same($asciidocDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'adoc'));
        $t->same($latexDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));

        $colgroupDiagnostic = $markdownDiagnostics[1];
        $t->same('colgroup-provenance', $colgroupDiagnostic['reason'] ?? null);
        $t->same('raw-html-colgroup-provenance', $colgroupDiagnostic['requiredFeature'] ?? null);
        $t->same('pandoc-column-sources', $colgroupDiagnostic['source'] ?? null);
        $t->same('Column source writer audit', $colgroupDiagnostic['caption'] ?? null);
        $t->same(true, $colgroupDiagnostic['hasCaption'] ?? null);
        $t->same(3, $colgroupDiagnostic['columnCount'] ?? null);
        $t->same(2, $colgroupDiagnostic['columnGroupCount'] ?? null);
        $t->same(3, $colgroupDiagnostic['groupedColumnCount'] ?? null);
        $t->same(2, $colgroupDiagnostic['sourceAttributeGroupCount'] ?? null);
        $t->same(7, $colgroupDiagnostic['sourceAttributeCount'] ?? null);
        $t->same(['col'], $colgroupDiagnostic['groupKinds'] ?? null);
        $t->same($columnGroups, $colgroupDiagnostic['groups'] ?? null);

        $t->same('colgroup-provenance-review', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(2, $asciidocDiagnostics[0]['columnGroupCount'] ?? null);
        $t->same('colgroup-provenance-review', $latexDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(7, $latexDiagnostics[0]['sourceAttributeCount'] ?? null);

        $t->same(['markdown', 'asciidoc', 'latex', 'wordpress'], array_keys($packet['writerDowngrades']));
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->same(4, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-column-widths-approximated',
            'markdown-colgroup-provenance-require-raw-html',
            'asciidoc-colgroup-provenance-review-required',
            'latex-colgroup-provenance-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
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
    'normalizes pandoc alignment constructor names for markdown and wordpress handoff' => static function (TestRunner $t) use ($buildPandocAlignmentAliasDocument): void {
        $document = $buildPandocAlignmentAliasDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['left', 'right', 'center', 'default'], TableGeometry::alignments($table, 4));
        $t->same(['left', 'right', 'center', 'default'], array_map(static fn (array $spec): string => $spec['alignment'], TableGeometry::columnSpecs($table, 4)));
        $t->same('right', TableGeometry::cellAlignment($table, 3, $table->children[1]->children[0]->children[3]));
        $t->same(['left', 'right', 'center', 'default'], array_map(static fn (array $spec): string => $spec['alignment'], $packet['columns'] ?? []));
        $t->contains('<th style="text-align:left">Field</th><th style="text-align:right">Count</th><th style="text-align:center">State</th><th>Notes</th>', $blocks);
        $t->contains('<td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td><td style="text-align:right">Needs alt text</td>', $blocks);
    },
    'reports normalized relative widths when source colspecs exceed full table width' => static function (TestRunner $t) use ($buildOverfullColumnWidthDocument): void {
        $table = $buildOverfullColumnWidthDocument()->children[0];
        $summary = TableGeometry::columnWidthSummary($table);
        $specs = TableGeometry::columnSpecs($table, 3);
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same(3, $summary['columnCount']);
        $t->same(true, $summary['hasExplicitWidths']);
        $t->same(true, $summary['hasCompleteWidths']);
        $t->same(false, $summary['hasPartialWidths']);
        $t->same(1.5, $summary['widthTotal']);
        $t->same(0.5, $summary['overflowAmount']);
        $t->same(true, $summary['overfull']);
        $t->same(false, $summary['underfull']);
        $t->same([], $summary['missingColumns']);
        $t->same([0.4, 0.4, 0.2], $summary['normalizedWidths']);
        $t->same([60.0, 60.0, 30.0], $summary['percentWidths']);

        $t->same([0.4, 0.4, 0.2], array_map(static fn (array $spec): ?float => $spec['normalizedWidth'], $specs));
        $t->same([60.0, 60.0, 30.0], array_map(static fn (array $spec): ?float => $spec['percentWidth'], $specs));
        $t->same(['table-widths-exceed-full-width'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same(1.5, $diagnostics[0]['widthTotal'] ?? null);
        $t->same(0.5, $diagnostics[0]['overflowAmount'] ?? null);
        $t->same([0, 1, 2], $diagnostics[0]['columns'] ?? null);
        $t->same($summary, $packet['widthSummary'] ?? null);
        $t->same(['table-widths-exceed-full-width'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same([0.4, 0.4, 0.2], array_map(static fn (array $spec): ?float => $spec['normalizedWidth'], $packet['columns']));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports underfull relative widths without changing source colgroup handoff' => static function (TestRunner $t) use ($buildUnderfullColumnWidthDocument): void {
        $document = $buildUnderfullColumnWidthDocument();
        $table = $document->children[0];
        $summary = TableGeometry::columnWidthSummary($table);
        $specs = TableGeometry::columnSpecs($table, 3);
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, $summary['columnCount']);
        $t->same(true, $summary['hasExplicitWidths']);
        $t->same(true, $summary['hasCompleteWidths']);
        $t->same(false, $summary['hasPartialWidths']);
        $t->same(0.9, $summary['widthTotal']);
        $t->same(0.0, $summary['overflowAmount']);
        $t->same(0.1, $summary['underflowAmount']);
        $t->same(false, $summary['overfull']);
        $t->same(true, $summary['underfull']);
        $t->same([], $summary['missingColumns']);
        $t->same([0.222222, 0.333333, 0.444444], $summary['normalizedWidths']);
        $t->same([20.0, 30.0, 40.0], $summary['percentWidths']);

        $t->same([0.222222, 0.333333, 0.444444], array_map(static fn (array $spec): ?float => $spec['normalizedWidth'], $specs));
        $t->same([20.0, 30.0, 40.0], array_map(static fn (array $spec): ?float => $spec['percentWidth'], $specs));
        $t->same(['table-widths-underfill-full-width'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same(0.9, $diagnostics[0]['widthTotal'] ?? null);
        $t->same(0.1, $diagnostics[0]['underflowAmount'] ?? null);
        $t->same([0, 1, 2], $diagnostics[0]['columns'] ?? null);
        $t->same($summary, $packet['widthSummary'] ?? null);
        $t->same(['table-widths-underfill-full-width'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same([0.222222, 0.333333, 0.444444], array_map(static fn (array $spec): ?float => $spec['normalizedWidth'], $packet['columns']));
        $t->contains('<colgroup><col style="width:20%"/><col style="width:30%"/><col style="width:40%"/></colgroup>', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Underfull source width audit</figcaption>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports invalid relative width values without losing usable column metadata' => static function (TestRunner $t) use ($buildInvalidColumnWidthDocument): void {
        $document = $buildInvalidColumnWidthDocument();
        $table = $document->children[0];
        $summary = TableGeometry::columnWidthSummary($table);
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, $summary['columnCount']);
        $t->same(true, $summary['hasExplicitWidths']);
        $t->same(4, $summary['explicitWidthCount']);
        $t->same(1, $summary['validWidthCount']);
        $t->same([0], $summary['validWidthColumns']);
        $t->same([1, 2], $summary['invalidWidthColumns']);
        $t->same([1, 2, 3], $summary['missingColumns']);
        $t->same([
            ['column' => 1, 'rawType' => 'string', 'rawValue' => 'auto'],
            ['column' => 2, 'rawType' => 'float', 'rawValue' => -0.1],
        ], $summary['invalidWidths']);
        $t->same([0.25, null, null, null], array_map(static fn (array $spec): ?float => $spec['width'], TableGeometry::columnSpecs($table, 4)));
        $t->same(['table-widths-have-invalid-values'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same([1, 2], $diagnostics[0]['invalidColumns'] ?? null);
        $t->same(2, $diagnostics[0]['invalidWidthCount'] ?? null);
        $t->same('auto', $diagnostics[0]['invalidWidths'][0]['rawValue'] ?? null);
        $t->same(-0.1, $diagnostics[0]['invalidWidths'][1]['rawValue'] ?? null);
        $t->same(['table-widths-have-invalid-values'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same($summary, $packet['widthSummary'] ?? null);
        $t->same([1, 2], $packet['diagnostics'][0]['invalidColumns'] ?? null);
        $t->same(false, $packet['widthSummary']['hasCompleteWidths'] ?? true);
        $t->same(true, $packet['widthSummary']['hasPartialWidths'] ?? false);
        $t->contains('<tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td><td>Review widths</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<colgroup>'), 'Invalid partial widths should not emit a misleading WordPress colgroup');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'renders table body row head columns by visual column for wordpress handoff' => static function (TestRunner $t) use ($buildRowHeadColumnDocument): void {
        $document = $buildRowHeadColumnDocument();
        $table = $document->children[0];
        $body = $table->children[1];
        $layout = TableGeometry::layoutRows($body->children, 4);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, TableGeometry::rowHeadColumns($body, 4));
        $t->same(1, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => '1'], []), 4));
        $t->same(0, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => 'many'], []), 4));
        $t->same(4, TableGeometry::rowHeadColumns(new AstNode('table_body', ['rowHeadColumns' => 9], []), 4));
        $t->same([0, 1, 2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[0]['cells']));
        $t->same([1, 2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->contains('<tbody><tr><th rowspan="2" style="text-align:left">Pandoc</th><th style="text-align:left">Table geometry</th><td style="text-align:right">4</td><td style="text-align:center">Mapped</td></tr><tr><th style="text-align:left">DOCX handoff</th><td style="text-align:right">10</td><td style="text-align:center">Accepted</td></tr></tbody>', $blocks);
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
    },
    'diagnoses cells that exceed declared pandoc table columns without dropping content' => static function (TestRunner $t) use ($buildDeclaredColumnOverflowDocument): void {
        $document = $buildDeclaredColumnOverflowDocument();
        $table = $document->children[0];
        $body = $table->children[1];
        $diagnostics = TableGeometry::diagnostics($table);
        $layout = TableGeometry::layoutRows($body->children, TableGeometry::columnCount($table));
        $blocks = (new WordPressBlockWriter())->write($document);

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
    },
    'serializes source-to-visual column shifts for implicit rowspan handoffs' => static function (TestRunner $t) use ($buildImplicitColumnRowspanOverlapDocument): void {
        $document = $buildImplicitColumnRowspanOverlapDocument();
        $table = $document->children[0];
        $body = $table->children[0];
        $layout = TableGeometry::layoutRows($body->children, TableGeometry::columnCount($table));
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, TableGeometry::columnCount($table));
        $t->same(0, $packet['declaredColumnCount']);
        $t->same([2, 3], array_map(static fn (array $cell): int => $cell['column'], $layout[1]['cells']));
        $t->same([0, 1], array_map(static fn (array $cell): int => $cell['sourceColumn'], $layout[1]['cells']));
        $t->same([], $diagnostics);
        $t->same([], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(0, $packet['summary']['diagnosticCount'] ?? null);
        $t->same(true, $packet['summary']['hasSourceCoordinateShifts'] ?? null);
        $t->same(2, $packet['summary']['sourceCoordinateShiftCount'] ?? null);
        $t->same(2, $packet['summary']['maxVisualShift'] ?? null);
        $t->same([0, 1], $packet['coverage'][0]['sourceColumns'] ?? null);
        $t->same([0, 1], $packet['coverage'][0]['columns'] ?? null);
        $t->same(0, $packet['coverage'][0]['visualShift'] ?? null);
        $t->same('Unexpected source cell', $packet['coverage'][1]['text'] ?? null);
        $t->same(2, $packet['coverage'][1]['column'] ?? null);
        $t->same(0, $packet['coverage'][1]['sourceColumn'] ?? null);
        $t->same(1, $packet['coverage'][1]['sourceEndColumn'] ?? null);
        $t->same([0], $packet['coverage'][1]['sourceColumns'] ?? null);
        $t->same(2, $packet['coverage'][1]['visualShift'] ?? null);
        $t->same('Second conflict', $packet['coverage'][2]['text'] ?? null);
        $t->same(3, $packet['coverage'][2]['column'] ?? null);
        $t->same(1, $packet['coverage'][2]['sourceColumn'] ?? null);
        $t->same(2, $packet['coverage'][2]['sourceEndColumn'] ?? null);
        $t->same([1], $packet['coverage'][2]['sourceColumns'] ?? null);
        $t->same(2, $packet['coverage'][2]['visualShift'] ?? null);
        $t->contains('<tbody><tr><td colspan="2" rowspan="2">Merged source</td></tr><tr><td>Unexpected source cell</td><td>Second conflict</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes source-to-visual shift records for importer audits' => static function (TestRunner $t) use ($buildImplicitColumnRowspanOverlapDocument): void {
        $document = $buildImplicitColumnRowspanOverlapDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $shifts = is_array($packet['sourceCoordinateShifts'] ?? null)
            ? $packet['sourceCoordinateShifts']
            : [];

        $t->same(2, count($shifts));
        $t->same(2, $packet['summary']['sourceCoordinateShiftCount'] ?? null);
        $t->same(2, $packet['summary']['maxVisualShift'] ?? null);
        $t->same(['Unexpected source cell', 'Second conflict'], array_map(static fn (array $record): string => (string) ($record['text'] ?? ''), $shifts));
        $t->same(['body', 'body'], array_map(static fn (array $record): string => (string) ($record['section'] ?? ''), $shifts));
        $t->same(['body', 'body'], array_map(static fn (array $record): string => (string) ($record['rowRole'] ?? ''), $shifts));
        $t->same([1, 1], array_map(static fn (array $record): int => (int) ($record['row'] ?? -1), $shifts));
        $t->same([0, 1], array_map(static fn (array $record): int => (int) ($record['sourceCell'] ?? -1), $shifts));
        $t->same([0, 1], array_map(static fn (array $record): int => (int) ($record['sourceColumn'] ?? -1), $shifts));
        $t->same([1, 2], array_map(static fn (array $record): int => (int) ($record['sourceEndColumn'] ?? -1), $shifts));
        $t->same([[0], [1]], array_map(static fn (array $record): array => $record['sourceColumns'] ?? [], $shifts));
        $t->same([2, 3], array_map(static fn (array $record): int => (int) ($record['column'] ?? -1), $shifts));
        $t->same([3, 4], array_map(static fn (array $record): int => (int) ($record['endColumn'] ?? -1), $shifts));
        $t->same([[2], [3]], array_map(static fn (array $record): array => $record['columns'] ?? [], $shifts));
        $t->same([2, 2], array_map(static fn (array $record): int => (int) ($record['visualShift'] ?? 0), $shifts));
        $t->same([2, 2], array_map(static fn (array $record): int => (int) ($record['absoluteVisualShift'] ?? 0), $shifts));
        $t->same([1, 1], array_map(static fn (array $record): int => (int) ($record['colspan'] ?? 0), $shifts));
        $t->same([1, 1], array_map(static fn (array $record): int => (int) ($record['rowspan'] ?? 0), $shifts));
        $t->same([false, false], array_map(static fn (array $record): bool => (bool) ($record['headerCell'] ?? true), $shifts));
        $t->same([false, false], array_map(static fn (array $record): bool => (bool) ($record['headerRow'] ?? true), $shifts));
        $t->same([0, 0], array_map(static fn (array $record): int => (int) ($record['rowHeadColumns'] ?? -1), $shifts));
        $t->same(false, array_key_exists('node', $shifts[0] ?? []));
        json_encode($shifts, JSON_THROW_ON_ERROR);
    },
    'diagnoses malformed source span attributes while preserving normalized table output' => static function (TestRunner $t) use ($buildMalformedSpanNormalizationDocument): void {
        $document = $buildMalformedSpanNormalizationDocument();
        $table = $document->children[0];
        $layout = TableGeometry::layoutRows($table->children[0]->children, TableGeometry::columnCount($table));
        $diagnostics = TableGeometry::diagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, TableGeometry::columnCount($table));
        $t->same([0, 1, 2], array_map(static fn (array $cell): int => $cell['column'], $layout[0]['cells']));
        $t->same([1, 1, 1], array_map(static fn (array $cell): int => $cell['colspan'], $layout[0]['cells']));
        $t->same([1, 1, 1], array_map(static fn (array $cell): int => $cell['rowspan'], $layout[0]['cells']));
        $t->same([0, 1, 2], array_map(static fn (array $cell): int => $cell['sourceColumn'], $layout[1]['cells']));

        $t->same(3, count($diagnostics));
        $t->same(['cell-span-normalized'], array_values(array_unique(array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics))));
        $t->same(['colspan', 'rowspan', 'rowspan'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['attribute'], $diagnostics));
        $t->same(['0', 'many', -3], array_map(static fn (array $diagnostic): mixed => $diagnostic['rawValue'] ?? null, $diagnostics));
        $t->same(['string', 'string', 'int'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['rawType'], $diagnostics));
        $t->same([1, 1, 1], array_map(static fn (array $diagnostic): int => (int) $diagnostic['normalizedValue'], $diagnostics));
        $t->same([1, 0, 0], array_map(static fn (array $diagnostic): int => (int) $diagnostic['minimumValue'], $diagnostics));
        $t->same([false, true, true], array_map(static fn (array $diagnostic): bool => (bool) $diagnostic['zeroMeansRowGroup'], $diagnostics));
        $t->same([0, 0, 1], array_map(static fn (array $diagnostic): int => (int) $diagnostic['row'], $diagnostics));
        $t->same([0, 0, 0], array_map(static fn (array $diagnostic): int => (int) $diagnostic['column'], $diagnostics));
        $t->same([0, 0, 0], array_map(static fn (array $diagnostic): int => (int) $diagnostic['sourceCell'], $diagnostics));
        $t->same([0, 0, 0], array_map(static fn (array $diagnostic): int => (int) $diagnostic['sourceColumn'], $diagnostics));

        $t->same(['cell-span-normalized'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(3, $packet['summary']['diagnosticCount'] ?? null);
        $t->same(true, $packet['summary']['hasNormalizedSpans'] ?? null);
        $t->same(3, $packet['summary']['normalizedSpanCount'] ?? null);
        $t->same($diagnostics, $packet['diagnostics'] ?? null);
        $t->same(1, $packet['coverage'][0]['colspan'] ?? null);
        $t->same(1, $packet['coverage'][0]['rowspan'] ?? null);
        $t->same(1, $packet['coverage'][3]['rowspan'] ?? null);
        $t->same(false, $packet['summary']['hasSpans'] ?? null);
        $t->contains('<table id="malformed-source-span-grid"><tbody><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Ready</td></tr><tr><td style="text-align:left">Media</td><td style="text-align:right">7</td><td style="text-align:center">Review</td></tr></tbody></table>', $blocks);
        $t->true(!str_contains($blocks, 'colspan="0"'), 'Malformed colspan must not leak into WordPress table output');
        $t->true(!str_contains($blocks, 'rowspan="-3"'), 'Malformed rowspan must not leak into WordPress table output');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'builds section grids with covered and missing visual slots for importer audits' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $document = $buildSectionGridDocument();
        $table = $document->children[0];
        $sectionGrids = TableGeometry::sectionGrids($table);
        $bodyGrid = $sectionGrids[1]['rows'];
        $blocks = (new WordPressBlockWriter())->write($document);

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
    },
    'summarizes visual row occupancy for table geometry review packets' => static function (TestRunner $t) use ($buildSectionGridDocument, $buildSpannedTableDocument): void {
        $packet = TableGeometry::reviewPacket($buildSectionGridDocument()->children[0], ['accessibility' => false]);
        $headSummary = $packet['sections'][0]['summary'] ?? [];
        $bodySummary = $packet['sections'][1]['summary'] ?? [];

        $t->same([4], $headSummary['rowSlotCounts'] ?? null);
        $t->same([3], $headSummary['rowVisualWidths'] ?? null);
        $t->same(0, $headSummary['completeRowCount'] ?? null);
        $t->same(1, $headSummary['incompleteRowCount'] ?? null);
        $t->same(1, $headSummary['coveredRowCount'] ?? null);
        $t->same(1, $headSummary['missingRowCount'] ?? null);
        $t->same(false, $headSummary['completeRectangle'] ?? null);
        $t->same([
            'row' => 0,
            'globalRow' => 0,
            'slotCount' => 4,
            'cellCount' => 2,
            'headerCellCount' => 2,
            'coveredSlotCount' => 1,
            'missingSlotCount' => 1,
            'occupiedSlotCount' => 3,
            'visualWidth' => 3,
            'complete' => false,
            'hasCoveredSlots' => true,
            'hasMissingSlots' => true,
        ], $headSummary['rowSummaries'][0] ?? null);

        $t->same([4, 4], $bodySummary['rowSlotCounts'] ?? null);
        $t->same([3, 3], $bodySummary['rowVisualWidths'] ?? null);
        $t->same(0, $bodySummary['completeRowCount'] ?? null);
        $t->same(2, $bodySummary['incompleteRowCount'] ?? null);
        $t->same(2, $bodySummary['coveredRowCount'] ?? null);
        $t->same(2, $bodySummary['missingRowCount'] ?? null);
        $t->same(3, $bodySummary['maxVisualWidth'] ?? null);
        $t->same(false, $bodySummary['completeRectangle'] ?? null);
        $t->same([1, 1, 1, 1], $headSummary['columnSlotCounts'] ?? null);
        $t->same([1, 0, 1, 0], $headSummary['columnCellCounts'] ?? null);
        $t->same([0, 1, 0, 0], $headSummary['columnCoveredSlotCounts'] ?? null);
        $t->same([0, 0, 0, 1], $headSummary['columnMissingSlotCounts'] ?? null);
        $t->same(3, $headSummary['completeColumnCount'] ?? null);
        $t->same(1, $headSummary['incompleteColumnCount'] ?? null);
        $t->same(1, $headSummary['coveredColumnCount'] ?? null);
        $t->same(1, $headSummary['missingColumnCount'] ?? null);
        $t->same([
            'column' => 1,
            'slotCount' => 1,
            'cellCount' => 0,
            'headerCellCount' => 0,
            'dataCellCount' => 0,
            'coveredSlotCount' => 1,
            'missingSlotCount' => 0,
            'occupiedSlotCount' => 1,
            'complete' => true,
            'hasCells' => false,
            'hasHeaderCells' => false,
            'hasDataCells' => false,
            'hasCoveredSlots' => true,
            'hasMissingSlots' => false,
            'rows' => [0],
            'globalRows' => [0],
            'cellRows' => [],
            'coveredRows' => [0],
            'missingRows' => [],
        ], $headSummary['columnSummaries'][1] ?? null);
        $t->same([1, 0, 2, 0], $bodySummary['columnCellCounts'] ?? null);
        $t->same([1, 2, 0, 0], $bodySummary['columnCoveredSlotCounts'] ?? null);
        $t->same([0, 0, 0, 2], $bodySummary['columnMissingSlotCounts'] ?? null);
        $t->same(3, $bodySummary['completeColumnCount'] ?? null);
        $t->same(1, $bodySummary['incompleteColumnCount'] ?? null);
        $t->same(2, $bodySummary['coveredColumnCount'] ?? null);
        $t->same(1, $bodySummary['missingColumnCount'] ?? null);

        $t->same(0, $packet['summary']['completeRowCount'] ?? null);
        $t->same(3, $packet['summary']['incompleteRowCount'] ?? null);
        $t->same(3, $packet['summary']['coveredRowCount'] ?? null);
        $t->same(3, $packet['summary']['missingRowCount'] ?? null);
        $t->same(3, $packet['summary']['maxVisualWidth'] ?? null);
        $t->same(false, $packet['summary']['completeRectangle'] ?? null);
        $t->same(true, $packet['summary']['hasIncompleteRows'] ?? null);
        $t->same(true, $packet['summary']['hasCoveredRows'] ?? null);
        $t->same(true, $packet['summary']['hasMissingRows'] ?? null);
        $t->same([3, 3, 3, 3], $packet['summary']['columnSlotCounts'] ?? null);
        $t->same([2, 0, 3, 0], $packet['summary']['columnCellCounts'] ?? null);
        $t->same([1, 0, 1, 0], $packet['summary']['columnHeaderCellCounts'] ?? null);
        $t->same([1, 0, 2, 0], $packet['summary']['columnDataCellCounts'] ?? null);
        $t->same([1, 3, 0, 0], $packet['summary']['columnCoveredSlotCounts'] ?? null);
        $t->same([0, 0, 0, 3], $packet['summary']['columnMissingSlotCounts'] ?? null);
        $t->same(3, $packet['summary']['completeColumnCount'] ?? null);
        $t->same(1, $packet['summary']['incompleteColumnCount'] ?? null);
        $t->same(2, $packet['summary']['coveredColumnCount'] ?? null);
        $t->same(1, $packet['summary']['missingColumnCount'] ?? null);
        $t->same(3, $packet['summary']['maxColumnCellCount'] ?? null);
        $t->same([
            'column' => 3,
            'slotCount' => 3,
            'cellCount' => 0,
            'headerCellCount' => 0,
            'dataCellCount' => 0,
            'coveredSlotCount' => 0,
            'missingSlotCount' => 3,
            'occupiedSlotCount' => 0,
            'complete' => false,
            'hasCells' => false,
            'hasHeaderCells' => false,
            'hasDataCells' => false,
            'hasCoveredSlots' => false,
            'hasMissingSlots' => true,
            'rows' => [0, 1, 2],
            'globalRows' => [0, 1, 2],
            'cellRows' => [],
            'coveredRows' => [],
            'missingRows' => [0, 1, 2],
        ], $packet['summary']['columnSummaries'][3] ?? null);

        $completePacket = TableGeometry::reviewPacket($buildSpannedTableDocument()->children[0], ['accessibility' => false]);
        $t->same([true, true], array_map(static fn (array $section): bool => (bool) ($section['summary']['completeRectangle'] ?? false), $completePacket['sections']));
        $t->same([1, 2], array_map(static fn (array $section): int => (int) ($section['summary']['completeRowCount'] ?? 0), $completePacket['sections']));
        $t->same(3, $completePacket['summary']['completeRowCount'] ?? null);
        $t->same(0, $completePacket['summary']['incompleteRowCount'] ?? null);
        $t->same(true, $completePacket['summary']['completeRectangle'] ?? null);
        $t->same(false, $completePacket['summary']['hasMissingRows'] ?? null);
        $t->same(3, $completePacket['summary']['completeColumnCount'] ?? null);
        $t->same(0, $completePacket['summary']['incompleteColumnCount'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($completePacket, JSON_THROW_ON_ERROR);
    },
    'reports flat grid fallback diagnostics for importer visual-slot handoff' => static function (TestRunner $t) use ($buildSectionGridDocument, $buildOverfullColumnWidthDocument): void {
        $table = $buildSectionGridDocument()->children[0];
        $fallbacks = TableGeometry::flatGridFallbackDiagnostics($table);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $completeFallbacks = TableGeometry::flatGridFallbackDiagnostics($buildOverfullColumnWidthDocument()->children[0]);
        $codes = array_map(static fn (array $fallback): string => $fallback['code'], $fallbacks);

        $t->same([
            'flat-grid-covered-slots-require-anchor-replay',
            'flat-grid-missing-slots-require-empty-placeholders',
        ], $codes);

        $coveredFallback = $fallbacks[0];
        $missingFallback = $fallbacks[1];
        $t->same('pandoc-flat-grid', $coveredFallback['source'] ?? null);
        $t->same('covered-slots', $coveredFallback['reason'] ?? null);
        $t->same('span-anchor-replay', $coveredFallback['requiredFeature'] ?? null);
        $t->same(4, $coveredFallback['slotCount'] ?? null);
        $t->same(['head', 'body'], $coveredFallback['sections'] ?? null);
        $t->same([0, 1], $coveredFallback['rows'] ?? null);
        $t->same([0, 1, 2], $coveredFallback['globalRows'] ?? null);
        $t->same([0, 1], $coveredFallback['columns'] ?? null);
        $t->same(['colspan', 'rowspan', 'rowspan-colspan'], $coveredFallback['coverings'] ?? null);
        $t->same('colspan', $coveredFallback['slots'][0]['covering'] ?? null);
        $t->same('head:0:0:0', $coveredFallback['slots'][0]['anchorKey'] ?? null);
        $t->same('Scope', $coveredFallback['slots'][0]['anchorText'] ?? null);
        $t->same([0, 1], $coveredFallback['slots'][0]['spanColumns'] ?? null);
        $t->same('rowspan-colspan', $coveredFallback['slots'][3]['covering'] ?? null);
        $t->same('body:0:0:0', $coveredFallback['slots'][3]['anchorKey'] ?? null);
        $t->same('Posts', $coveredFallback['slots'][3]['anchorText'] ?? null);
        $t->same([0, 1], $coveredFallback['slots'][3]['spanColumns'] ?? null);

        $t->same('pandoc-flat-grid', $missingFallback['source'] ?? null);
        $t->same('missing-slots', $missingFallback['reason'] ?? null);
        $t->same('empty-cell-placeholders', $missingFallback['requiredFeature'] ?? null);
        $t->same(3, $missingFallback['slotCount'] ?? null);
        $t->same(['head', 'body'], $missingFallback['sections'] ?? null);
        $t->same([0, 1], $missingFallback['rows'] ?? null);
        $t->same([0, 1, 2], $missingFallback['globalRows'] ?? null);
        $t->same([3], $missingFallback['columns'] ?? null);
        $t->same('missing', $missingFallback['slots'][0]['kind'] ?? null);
        $t->same('head', $missingFallback['slots'][0]['section'] ?? null);
        $t->same(3, $missingFallback['slots'][0]['column'] ?? null);
        $t->same('', $missingFallback['slots'][0]['text'] ?? null);

        $t->same($fallbacks, $packet['flatGridFallbacks'] ?? null);
        $t->same(2, $packet['summary']['flatGridFallbackCount'] ?? null);
        $t->same(true, $packet['summary']['hasFlatGridFallbacks'] ?? null);
        $t->same($codes, $packet['summary']['flatGridFallbackCodes'] ?? null);
        $t->same(['head', 'body'], $packet['summary']['flatGridFallbackSections'] ?? null);
        $t->same([0, 1], $packet['summary']['flatGridFallbackRows'] ?? null);
        $t->same([0, 1, 2], $packet['summary']['flatGridFallbackGlobalRows'] ?? null);
        $t->same([0, 1, 3], $packet['summary']['flatGridFallbackColumns'] ?? null);
        $t->same(4, $packet['summary']['flatGridFallbackCoveredSlotCount'] ?? null);
        $t->same(3, $packet['summary']['flatGridFallbackMissingSlotCount'] ?? null);
        $t->same([], $completeFallbacks);
        json_encode($fallbacks, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'preserves flat grid missing visual slots as opt-in WordPress placeholders' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $document = $buildSectionGridDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $defaultBlocks = (new WordPressBlockWriter())->write($document);
        $placeholderBlocks = (new WordPressBlockWriter(['preserveTableMissingCells' => true]))->write($document);

        $t->same('flat-grid-missing-slots-require-empty-placeholders', $packet['flatGridFallbacks'][1]['code'] ?? null);
        $t->same(3, $packet['flatGridFallbacks'][1]['slotCount'] ?? null);
        $t->same('empty-cell-placeholders', $packet['flatGridFallbacks'][1]['requiredFeature'] ?? null);
        $t->true(!str_contains($defaultBlocks, 'data-pandoc-missing-cell'), 'Default WordPress table output should not synthesize empty visual-slot placeholders');
        $t->same(3, substr_count($placeholderBlocks, 'data-pandoc-missing-cell="true"'));
        $t->contains('<th colspan="2" style="text-align:left">Scope</th><th style="text-align:right">State</th><td data-pandoc-missing-cell="true" data-pandoc-missing-row="0" data-pandoc-missing-column="3" aria-hidden="true"></td>', $placeholderBlocks);
        $t->contains('<tr><td colspan="2" rowspan="2" style="text-align:left">Posts</td><td style="text-align:right">Ready</td><td data-pandoc-missing-cell="true" data-pandoc-missing-row="0" data-pandoc-missing-column="3" aria-hidden="true"></td></tr>', $placeholderBlocks);
        $t->contains('<tr><td style="text-align:right">Needs media</td><td data-pandoc-missing-cell="true" data-pandoc-missing-row="1" data-pandoc-missing-column="3" aria-hidden="true"></td></tr>', $placeholderBlocks);
        $t->true(!str_contains($placeholderBlocks, 'data-pandoc-missing-column="0"'), 'Covered anchors should not become missing-cell placeholders');
        $t->true(!str_contains($placeholderBlocks, 'data-pandoc-missing-column="1"'), 'Covered colspan/rowspan slots should not become missing-cell placeholders');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'preserves flat grid covered visual slots as opt-in WordPress span-anchor metadata' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $document = $buildSectionGridDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $defaultBlocks = (new WordPressBlockWriter())->write($document);
        $coveredBlocks = (new WordPressBlockWriter(['preserveTableCoveredSlots' => true]))->write($document);
        $combinedBlocks = (new WordPressBlockWriter([
            'preserveTableCoveredSlots' => true,
            'preserveTableMissingCells' => true,
        ]))->write($document);

        $t->same('flat-grid-covered-slots-require-anchor-replay', $packet['flatGridFallbacks'][0]['code'] ?? null);
        $t->same(4, $packet['flatGridFallbacks'][0]['slotCount'] ?? null);
        $t->same('span-anchor-replay', $packet['flatGridFallbacks'][0]['requiredFeature'] ?? null);
        $t->true(!str_contains($defaultBlocks, 'data-pandoc-covered-slots'), 'Default WordPress table output should not expose covered-slot replay metadata');
        $t->true(!str_contains($coveredBlocks, 'data-pandoc-missing-cell'), 'Covered-slot replay should not synthesize missing-cell placeholders unless separately requested');
        $t->same(2, substr_count($coveredBlocks, 'data-pandoc-span-anchor="true"'));
        $t->same(1, substr_count($coveredBlocks, 'data-pandoc-covered-slots="0:1:colspan"'));
        $t->same(1, substr_count($coveredBlocks, 'data-pandoc-covered-slots="0:1:colspan;1:0:rowspan;1:1:rowspan-colspan"'));
        $t->contains('<th data-pandoc-span-anchor="true" data-pandoc-covered-slot-count="1" data-pandoc-covered-slots="0:1:colspan" colspan="2" style="text-align:left">Scope</th>', $coveredBlocks);
        $t->contains('<td data-pandoc-span-anchor="true" data-pandoc-covered-slot-count="3" data-pandoc-covered-slots="0:1:colspan;1:0:rowspan;1:1:rowspan-colspan" colspan="2" rowspan="2" style="text-align:left">Posts</td>', $coveredBlocks);
        $t->true(!str_contains($coveredBlocks, '<td data-pandoc-span-anchor="true" data-pandoc-covered-slot-count="0"'), 'Only anchors with covered visual slots should be annotated');
        $t->same(2, substr_count($combinedBlocks, 'data-pandoc-span-anchor="true"'));
        $t->same(3, substr_count($combinedBlocks, 'data-pandoc-missing-cell="true"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes spanned cell occupied slots for importer geometry audits' => static function (TestRunner $t) use ($buildSectionGridDocument, $buildSectionScopedRowspanDocument): void {
        $document = $buildSectionGridDocument();
        $table = $document->children[0];
        $sectionGrids = TableGeometry::sectionGrids($table);
        $coverage = TableGeometry::cellCoverage($table);
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Normalized Grid']);

        $expectedHeadSlots = [
            ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
            ['row' => 0, 'column' => 1, 'covering' => 'colspan'],
        ];
        $expectedBodySlots = [
            ['row' => 0, 'column' => 0, 'covering' => 'anchor'],
            ['row' => 0, 'column' => 1, 'covering' => 'colspan'],
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 1, 'column' => 1, 'covering' => 'rowspan-colspan'],
        ];

        $t->same($expectedHeadSlots, $sectionGrids[0]['rows'][0][0]['occupiedSlots'] ?? null);
        $t->same($expectedBodySlots, $sectionGrids[1]['rows'][0][0]['occupiedSlots'] ?? null);
        $t->same($expectedBodySlots, $coverage[2]['occupiedSlots'] ?? null);
        $t->same($expectedBodySlots, $packet['sections'][1]['rows'][0]['slots'][0]['occupiedSlots'] ?? null);
        $t->same($expectedBodySlots, $packet['coverage'][2]['occupiedSlots'] ?? null);
        $t->same([['row' => 0, 'column' => 2, 'covering' => 'anchor']], $packet['coverage'][3]['occupiedSlots'] ?? null);

        $boundaryPacket = TableGeometry::reviewPacket($buildSectionScopedRowspanDocument()->children[0], ['idPrefix' => 'Boundary Grid']);
        $t->same(2, $boundaryPacket['coverage'][0]['rawRowspan'] ?? null);
        $t->same(1, $boundaryPacket['coverage'][0]['rowspan'] ?? null);
        $t->same([['row' => 0, 'column' => 0, 'covering' => 'anchor']], $boundaryPacket['coverage'][0]['occupiedSlots'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($boundaryPacket, JSON_THROW_ON_ERROR);
    },
    'reports markdown pipe-table writer downgrades for visual spans' => static function (TestRunner $t) use ($buildSectionGridDocument, $buildSpannedTableDocument): void {
        $table = $buildSectionGridDocument()->children[0];
        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Downgrade Grid']);
        $plainTable = $buildSpannedTableDocument()->children[0];

        $t->same(['markdown-column-widths-approximated', 'markdown-colspan-flattened', 'markdown-colspan-flattened', 'markdown-rowspan-flattened'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $diagnostics));
        $t->same(['markdown'], array_values(array_unique(array_map(static fn (array $diagnostic): string => $diagnostic['writer'], $diagnostics))));
        $t->same('column-widths', $diagnostics[0]['reason']);
        $t->same([10, 10, 10, 10], $diagnostics[0]['pipeCharacterWidths']);
        $t->same('head', $diagnostics[1]['section']);
        $t->same(0, $diagnostics[1]['row']);
        $t->same(0, $diagnostics[1]['column']);
        $t->same([0, 1], $diagnostics[1]['columns']);
        $t->same(2, $diagnostics[1]['rawColspan']);
        $t->same(1, $diagnostics[1]['rawRowspan']);
        $t->same([['row' => 0, 'column' => 1, 'covering' => 'colspan']], $diagnostics[1]['flattenedSlots']);
        $t->same('body', $diagnostics[2]['section']);
        $t->same([0, 1], $diagnostics[2]['columns']);
        $t->same([['row' => 0, 'column' => 1, 'covering' => 'colspan']], $diagnostics[2]['flattenedSlots']);
        $t->same('markdown-rowspan-flattened', $diagnostics[3]['code']);
        $t->same(2, $diagnostics[3]['rawRowspan']);
        $t->same([
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 1, 'column' => 1, 'covering' => 'rowspan-colspan'],
        ], $diagnostics[3]['flattenedSlots']);
        $t->same($diagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same(4, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['markdown-column-widths-approximated', 'markdown-colspan-flattened', 'markdown-rowspan-flattened'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($plainTable, 'wordpress'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports rst grid-table requirements for spanned writer handoff' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $table = $buildSectionGridDocument()->children[0];
        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'restructuredtext');
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'RST Grid',
            'writers' => ['markdown', 'rst'],
        ]);

        $t->same([
            'rst-grid-table-required',
            'rst-grid-table-required',
            'rst-grid-table-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $diagnostics));
        $t->same(['rst'], array_values(array_unique(array_map(static fn (array $diagnostic): string => $diagnostic['writer'], $diagnostics))));
        $t->same('head', $diagnostics[0]['section']);
        $t->same(0, $diagnostics[0]['row']);
        $t->same(0, $diagnostics[0]['column']);
        $t->same([0, 1], $diagnostics[0]['columns']);
        $t->same(2, $diagnostics[0]['rawColspan']);
        $t->same('colspan', $diagnostics[0]['reason']);
        $t->same('grid-table', $diagnostics[0]['requiredFeature']);
        $t->same([
            ['row' => 0, 'column' => 1, 'covering' => 'colspan'],
        ], $diagnostics[0]['requiredSlots']);
        $t->same('body', $diagnostics[1]['section']);
        $t->same(0, $diagnostics[1]['row']);
        $t->same(0, $diagnostics[1]['column']);
        $t->same([0, 1], $diagnostics[1]['columns']);
        $t->same(2, $diagnostics[1]['rawColspan']);
        $t->same('colspan', $diagnostics[1]['reason']);
        $t->same('grid-table', $diagnostics[1]['requiredFeature']);
        $t->same([
            ['row' => 0, 'column' => 1, 'covering' => 'colspan'],
        ], $diagnostics[1]['requiredSlots']);
        $t->same('body', $diagnostics[2]['section']);
        $t->same(0, $diagnostics[2]['row']);
        $t->same(0, $diagnostics[2]['column']);
        $t->same([0, 1], $diagnostics[2]['columns']);
        $t->same(2, $diagnostics[2]['rawRowspan']);
        $t->same('rowspan', $diagnostics[2]['reason']);
        $t->same('grid-table', $diagnostics[2]['requiredFeature']);
        $t->same([
            ['row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['row' => 1, 'column' => 1, 'covering' => 'rowspan-colspan'],
        ], $diagnostics[2]['requiredSlots']);
        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'rst-grid-table'));
        $t->same(['markdown', 'rst'], array_keys($packet['writerDowngrades']));
        $t->same($diagnostics, $packet['writerDowngrades']['rst'] ?? null);
        $t->same(7, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['markdown-column-widths-approximated', 'markdown-colspan-flattened', 'markdown-rowspan-flattened', 'rst-grid-table-required'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['markdown', 'rst'], $packet['summary']['writerDowngradeWriters'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports markdown grid-table extension requirements without pipe-table span flattening' => static function (TestRunner $t) use ($buildSectionGridDocument): void {
        $table = $buildSectionGridDocument()->children[0];
        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown-grid-table');
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Markdown Grid',
            'writers' => ['markdown', 'markdown-grid-table'],
        ]);

        $t->same(['markdown-grid-table-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $diagnostics));
        $t->same('markdown-grid-table', $diagnostics[0]['writer'] ?? null);
        $t->same('spans', $diagnostics[0]['reason'] ?? null);
        $t->same('grid_tables', $diagnostics[0]['requiredFeature'] ?? null);
        $t->same('Normalized table grid review', $diagnostics[0]['caption'] ?? null);
        $t->same(4, $diagnostics[0]['columnCount'] ?? null);
        $t->same(['colspan', 'rowspan'], $diagnostics[0]['spanTypes'] ?? null);
        $t->same(2, $diagnostics[0]['spannedCellCount'] ?? null);
        $t->same(4, $diagnostics[0]['requiredSlotCount'] ?? null);
        $t->same([
            ['section' => 'head', 'row' => 0, 'column' => 0, 'columns' => [0, 1], 'rawColspan' => 2, 'rawRowspan' => 1],
            ['section' => 'body', 'row' => 0, 'column' => 0, 'columns' => [0, 1], 'rawColspan' => 2, 'rawRowspan' => 2],
        ], $diagnostics[0]['spannedCells'] ?? null);
        $t->same([
            ['section' => 'head', 'row' => 0, 'column' => 1, 'covering' => 'colspan'],
            ['section' => 'body', 'row' => 0, 'column' => 1, 'covering' => 'colspan'],
            ['section' => 'body', 'row' => 1, 'column' => 0, 'covering' => 'rowspan'],
            ['section' => 'body', 'row' => 1, 'column' => 1, 'covering' => 'rowspan-colspan'],
        ], $diagnostics[0]['requiredSlots'] ?? null);

        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'markdown+grid_tables'));
        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'pandoc-markdown-grid-table'));
        $plainTable = new AstNode('table', ['caption' => 'Plain Markdown grid table'], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($plainTable, 'markdown-grid-table'));
        $t->same(['markdown', 'markdown-grid-table'], array_keys($packet['writerDowngrades']));
        $t->same($diagnostics, $packet['writerDowngrades']['markdown-grid-table'] ?? null);
        $t->same(5, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-column-widths-approximated',
            'markdown-colspan-flattened',
            'markdown-rowspan-flattened',
            'markdown-grid-table-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['markdown', 'markdown-grid-table'], $packet['summary']['writerDowngradeWriters'] ?? null);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
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
        $t->same([0.15, 0.2], $coverage[0]['normalizedWidths']);
        $t->same([15.0, 20.0], $coverage[0]['percentWidths']);
        $t->same(0.35, $coverage[0]['widthTotal']);
        $t->same(0.35, $coverage[0]['normalizedWidthTotal']);
        $t->same(35.0, $coverage[0]['percentWidthTotal']);
        $t->same(true, $coverage[0]['hasCompleteWidths']);
        $t->same(false, $coverage[0]['hasPartialWidths']);
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
        $t->same([0.2, 0.25, 0.4, null], $needsMedia['normalizedWidths']);
        $t->same([20.0, 25.0, 40.0, null], $needsMedia['percentWidths']);
        $t->same(0.85, $needsMedia['widthTotal']);
        $t->same(0.85, $needsMedia['normalizedWidthTotal']);
        $t->same(85.0, $needsMedia['percentWidthTotal']);
        $t->same(false, $needsMedia['hasCompleteWidths']);
        $t->same(true, $needsMedia['hasPartialWidths']);
        $t->same([true, true, true, false], $needsMedia['declaredColumns']);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $t->same($coverage[0]['normalizedWidths'], $packet['coverage'][0]['normalizedWidths'] ?? null);
        $t->same($coverage[5]['percentWidthTotal'], $packet['coverage'][5]['percentWidthTotal'] ?? null);
        $t->contains('<tr><td colspan="4" style="text-align:center">Needs media</td></tr>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
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
    'serializes source row coordinates for spanned table geometry handoff' => static function (TestRunner $t) use ($buildBodyHeadRowRoleDocument, $buildSectionScopedRowspanDocument): void {
        $document = $buildBodyHeadRowRoleDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $coverage = TableGeometry::cellCoverage($table);
        $markdownDowngrades = array_values(array_filter(
            TableGeometry::writerDowngradeDiagnostics($table, 'markdown'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'markdown-rowspan-flattened'
        ));

        $posts = $coverage[6];
        $t->same('body', $posts['section']);
        $t->same(1, $posts['sourceRow']);
        $t->same(2, $posts['sourceRowspan']);
        $t->same(3, $posts['sourceRowEnd']);
        $t->same([1, 3], $posts['sourceRowRange']);
        $t->same([1, 2], $posts['sourceRows']);
        $t->same([2, 4], $posts['globalRowRange']);
        $t->same([2, 3], $posts['globalRows']);

        $bodyHeadSlot = $packet['sections'][1]['rows'][0]['slots'][0] ?? [];
        $anchorSlot = $packet['sections'][1]['rows'][1]['slots'][0] ?? [];
        $coveredSlot = $packet['sections'][1]['rows'][2]['slots'][0] ?? [];
        $t->same('cell', $bodyHeadSlot['kind'] ?? null);
        $t->same(0, $bodyHeadSlot['sourceRow'] ?? null);
        $t->same([0], $bodyHeadSlot['sourceRows'] ?? null);
        $t->same('cell', $anchorSlot['kind'] ?? null);
        $t->same(1, $anchorSlot['sourceRow'] ?? null);
        $t->same([1, 3], $anchorSlot['sourceRowRange'] ?? null);
        $t->same(1, $anchorSlot['anchorSourceRow'] ?? null);
        $t->same([1, 2], $anchorSlot['anchorSourceRows'] ?? null);
        $t->same('covered', $coveredSlot['kind'] ?? null);
        $t->same(1, $coveredSlot['sourceRow'] ?? null);
        $t->same([1, 3], $coveredSlot['sourceRowRange'] ?? null);
        $t->same(1, $coveredSlot['anchorSourceRow'] ?? null);
        $t->same([1, 2], $coveredSlot['anchorSourceRows'] ?? null);
        $t->same(2, $coveredSlot['anchorGlobalRow'] ?? null);

        $t->same(1, count($markdownDowngrades));
        $t->same(1, $markdownDowngrades[0]['sourceRow'] ?? null);
        $t->same([1, 3], $markdownDowngrades[0]['sourceRowRange'] ?? null);
        $t->same([1, 2], $markdownDowngrades[0]['sourceRows'] ?? null);

        $sectionBoundaryDocument = $buildSectionScopedRowspanDocument();
        $sectionDiagnostics = TableGeometry::diagnostics($sectionBoundaryDocument->children[0]);
        $sectionBoundaryDiagnostics = array_values(array_filter(
            $sectionDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'rowspan-crosses-section-boundary'
        ));
        $t->same(1, count($sectionBoundaryDiagnostics));
        $t->same(0, $sectionBoundaryDiagnostics[0]['sourceRow'] ?? null);
        $t->same(2, $sectionBoundaryDiagnostics[0]['sourceRowspan'] ?? null);
        $t->same([0, 2], $sectionBoundaryDiagnostics[0]['sourceRowRange'] ?? null);
        $t->same([0, 1], $sectionBoundaryDiagnostics[0]['sourceRows'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes pandoc table row groups for importer review packets' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Grouped table review',
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
                'htmlAttributes' => ['data-group' => 'posts'],
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
            new AstNode('table_body', [
                'htmlAttributes' => ['id' => 'pages-group'],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                    new AstNode('table_cell', ['text' => '5'], [new AstNode('text', ['text' => '5'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
            new AstNode('table_foot', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Total'], [new AstNode('text', ['text' => 'Total'])]),
                    new AstNode('table_cell', ['text' => '54'], [new AstNode('text', ['text' => '54'])]),
                    new AstNode('table_cell', ['text' => 'Complete'], [new AstNode('text', ['text' => 'Complete'])]),
                ]),
            ]),
        ]);

        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $rowGroups = $packet['rowGroups'] ?? [];

        $t->same(['head', 'body', 'body1', 'foot'], array_map(static fn (array $group): string => $group['section'], $rowGroups));
        $t->same(['table-head', 'table-body', 'table-body', 'table-foot'], array_map(static fn (array $group): string => $group['kind'], $rowGroups));
        $t->same([1, 3, 1, 1], array_map(static fn (array $group): int => $group['rowCount'], $rowGroups));
        $t->same([3, 8, 3, 3], array_map(static fn (array $group): int => $group['cellCount'], $rowGroups));
        $t->same([0, 1, 4, 5], array_map(static fn (array $group): int => $group['globalRowStart'], $rowGroups));
        $t->same([1, 4, 5, 6], array_map(static fn (array $group): int => $group['globalRowEnd'], $rowGroups));
        $t->same([[0, 1], [1, 4], [4, 5], [5, 6]], array_map(static fn (array $group): array => $group['rowRange'], $rowGroups));
        $t->same([0, 1, 2, 3], array_map(static fn (array $group): int => $group['ordinal'], $rowGroups));
        $t->same([1, 1, 0, 0], array_map(static fn (array $group): int => $group['headerLikeRowCount'], $rowGroups));
        $t->same([0, 2, 1, 1], array_map(static fn (array $group): int => $group['dataLikeRowCount'], $rowGroups));
        $t->same(['body-head', 'body'], $rowGroups[1]['rowRoles'] ?? null);
        $t->same(0, $rowGroups[1]['bodyIndex'] ?? null);
        $t->same(0, $rowGroups[1]['bodyOrdinal'] ?? null);
        $t->same(1, $rowGroups[1]['bodyHeadRowCount'] ?? null);
        $t->same(2, $rowGroups[1]['bodyRowCount'] ?? null);
        $t->same(['body-head' => 1, 'body' => 2], $rowGroups[1]['rowRoleCounts'] ?? null);
        $t->same(1, $rowGroups[1]['rowHeadColumns'] ?? null);
        $t->same(true, $rowGroups[1]['hasBodyHeadRows'] ?? null);
        $t->same(true, $rowGroups[1]['hasRowHeadColumns'] ?? null);
        $t->same('posts', $rowGroups[1]['sourceAttributes']['htmlAttributes']['data-group'] ?? null);
        $t->same(1, $rowGroups[2]['bodyIndex'] ?? null);
        $t->same(1, $rowGroups[2]['bodyOrdinal'] ?? null);
        $t->same(0, $rowGroups[2]['bodyHeadRowCount'] ?? null);
        $t->same(1, $rowGroups[2]['bodyRowCount'] ?? null);
        $t->same(['body' => 1], $rowGroups[2]['rowRoleCounts'] ?? null);
        $t->same('pages-group', $rowGroups[2]['sourceAttributes']['id'] ?? null);
        $t->same(['foot' => 1], $rowGroups[3]['rowRoleCounts'] ?? null);
        $t->same(4, $packet['summary']['rowGroupCount'] ?? null);
        $t->same(2, $packet['summary']['bodyGroupCount'] ?? null);
        $t->same(true, $packet['summary']['hasMultipleBodyGroups'] ?? null);
        $t->same(1, $packet['summary']['tableHeadRowCount'] ?? null);
        $t->same(1, $packet['summary']['bodyHeadRowCount'] ?? null);
        $t->same(3, $packet['summary']['bodyRowCount'] ?? null);
        $t->same(1, $packet['summary']['tableFootRowCount'] ?? null);
        $t->same(true, $packet['summary']['hasTableFoot'] ?? null);
        $t->same(true, $packet['summary']['hasBodyHeadRows'] ?? null);
        $t->same(1, $packet['summary']['bodyHeadRowGroupCount'] ?? null);
        $t->same(1, $packet['summary']['rowHeadGroupCount'] ?? null);
        $t->same(1, $packet['summary']['maxRowHeadColumns'] ?? null);
        $t->same(2, $packet['summary']['headerLikeRowCount'] ?? null);
        $t->same(4, $packet['summary']['dataLikeRowCount'] ?? null);
        $t->same(3, $packet['summary']['maxRowGroupRowCount'] ?? null);
        $t->same(4, $packet['summary']['nonEmptyRowGroupCount'] ?? null);
        $t->same(0, $packet['summary']['emptyRowGroupCount'] ?? null);
        $t->same(['head' => 1, 'body-head' => 1, 'body' => 3, 'foot' => 1], $packet['summary']['rowRoleCounts'] ?? null);
        $t->same(['head', 'body', 'body1', 'foot'], $packet['summary']['rowGroupSections'] ?? null);
        $t->same([
            ['section' => 'head', 'kind' => 'table-head', 'rowRange' => [0, 1], 'rowCount' => 1],
            ['section' => 'body', 'kind' => 'table-body', 'rowRange' => [1, 4], 'rowCount' => 3],
            ['section' => 'body1', 'kind' => 'table-body', 'rowRange' => [4, 5], 'rowCount' => 1],
            ['section' => 'foot', 'kind' => 'table-foot', 'rowRange' => [5, 6], 'rowCount' => 1],
        ], $packet['summary']['rowGroupRanges'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes global row coordinates for multi section table geometry packets' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Global row coordinate audit',
            'alignments' => ['left', 'right', 'center'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Count'], [new AstNode('text', ['text' => 'Count'])]),
                    new AstNode('table_cell', ['text' => 'State'], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'headRows' => [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                        new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
                        new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                    ]),
                ],
                'rowHeadColumns' => 1,
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
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                    new AstNode('table_cell', ['text' => '5'], [new AstNode('text', ['text' => '5'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
            new AstNode('table_foot', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Total'], [new AstNode('text', ['text' => 'Total'])]),
                    new AstNode('table_cell', ['text' => '54'], [new AstNode('text', ['text' => '54'])]),
                    new AstNode('table_cell', ['text' => 'Complete'], [new AstNode('text', ['text' => 'Complete'])]),
                ]),
            ]),
        ]);

        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same([[0, 1], [1, 4], [4, 5], [5, 6]], array_map(
            static fn (array $section): array => $section['rowRange'],
            $packet['sections'] ?? []
        ));
        $t->same([0, 1, 4, 5], array_map(
            static fn (array $section): int => $section['globalRowStart'],
            $packet['sections'] ?? []
        ));
        $t->same([1, 4, 5, 6], array_map(
            static fn (array $section): int => $section['globalRowEnd'],
            $packet['sections'] ?? []
        ));
        $t->same([0, 1, 2, 3, 4, 5], array_merge(
            array_map(static fn (array $row): int => $row['globalRow'], $packet['sections'][0]['rows'] ?? []),
            array_map(static fn (array $row): int => $row['globalRow'], $packet['sections'][1]['rows'] ?? []),
            array_map(static fn (array $row): int => $row['globalRow'], $packet['sections'][2]['rows'] ?? []),
            array_map(static fn (array $row): int => $row['globalRow'], $packet['sections'][3]['rows'] ?? [])
        ));
        $t->same([0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 4, 4, 4, 5, 5, 5], array_map(
            static fn (array $cell): int => $cell['globalRow'],
            $packet['coverage'] ?? []
        ));
        $t->same([2, 4], $packet['coverage'][6]['globalRowRange'] ?? null);
        $t->same([2, 3], $packet['coverage'][6]['globalRows'] ?? null);
        $t->same(2, $packet['sections'][1]['rows'][1]['slots'][0]['globalRow'] ?? null);
        $t->same(2, $packet['sections'][1]['rows'][2]['slots'][0]['anchorGlobalRow'] ?? null);
        $t->same([2, 4], $packet['sections'][1]['rows'][1]['slots'][0]['globalRowRange'] ?? null);
        $t->same([2, 3], $packet['sections'][1]['rows'][1]['slots'][0]['globalRows'] ?? null);
        $t->same([2, 4], $packet['sections'][1]['rows'][2]['slots'][0]['anchorGlobalRowRange'] ?? null);
        $t->same([2, 3], $packet['sections'][1]['rows'][2]['slots'][0]['anchorGlobalRows'] ?? null);
        $t->same(5, $packet['sections'][3]['summary']['rowSummaries'][0]['globalRow'] ?? null);
        $t->same(5, $packet['summary']['maxGlobalRow'] ?? null);
        $t->same(6, $packet['summary']['globalRowCount'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports body-local head row writer handoff diagnostics' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Body head writer audit',
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
                'headRows' => [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Batch'], [new AstNode('text', ['text' => 'Batch'])]),
                        new AstNode('table_cell', ['text' => 'Queue'], [new AstNode('text', ['text' => 'Queue'])]),
                        new AstNode('table_cell', ['text' => 'Decision'], [new AstNode('text', ['text' => 'Decision'])]),
                    ]),
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                    new AstNode('table_cell', ['text' => 'Review'], [new AstNode('text', ['text' => 'Review'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(['markdown-body-head-rows-flattened'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $markdownDiagnostics));
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('body-head-rows', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('body-local-header-row-boundaries', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('pandoc-table-body-head-rows', $markdownDiagnostics[0]['source'] ?? null);
        $t->same('Body head writer audit', $markdownDiagnostics[0]['caption'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['columnCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['sectionCount'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['rowCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['bodyCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['tableHeadRowCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['bodyHeadRowCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['bodyHeadRowGroupCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['bodyRowCount'] ?? null);
        $t->same(['body'], $markdownDiagnostics[0]['bodySections'] ?? null);
        $t->same([1], $markdownDiagnostics[0]['bodyHeadRowCounts'] ?? null);
        $t->same([1], $markdownDiagnostics[0]['bodySectionRowCounts'] ?? null);
        $t->same([
            ['section' => 'head', 'rowRange' => [0, 1], 'rowCount' => 1, 'rowRole' => 'head'],
            ['section' => 'body', 'rowRange' => [1, 3], 'rowCount' => 2, 'rowRole' => 'body'],
        ], $markdownDiagnostics[0]['sectionRanges'] ?? null);
        $t->same([
            ['section' => 'body', 'rowRange' => [1, 3], 'rowCount' => 2, 'rowRole' => 'body'],
        ], $markdownDiagnostics[0]['bodySectionRanges'] ?? null);
        $t->same([
            [
                'section' => 'body',
                'rowRange' => [1, 3],
                'rowCount' => 2,
                'rowRole' => 'body',
                'bodyHeadRowCount' => 1,
                'bodyHeadRowRange' => [1, 2],
                'bodyRowCount' => 1,
            ],
        ], $markdownDiagnostics[0]['bodyHeadRowRanges'] ?? null);
        $t->same(['head', 'body'], array_map(static fn (array $section): string => (string) ($section['section'] ?? ''), $markdownDiagnostics[0]['sections'] ?? []));

        $t->same(['asciidoc-body-head-rows-review-required'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $asciidocDiagnostics));
        $t->same('body-local-header-rows', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same([], $latexDiagnostics);

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(2, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-body-head-rows-flattened',
            'asciidoc-body-head-rows-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(true, $packet['summary']['hasBodyHeadRows'] ?? null);
        $t->same(1, $packet['summary']['bodyHeadRowCount'] ?? null);
        $t->contains('<thead><tr><th style="text-align:left">Document</th><th style="text-align:right">Items</th><th style="text-align:center">State</th></tr></thead><tbody><tr><th style="text-align:left">Batch</th><th style="text-align:right">Queue</th><th style="text-align:center">Decision</th></tr><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td><td style="text-align:center">Review</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($asciidocDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($latexDiagnostics, JSON_THROW_ON_ERROR);
    },
    'reports multiple table body group writer handoff diagnostics' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Multiple body group audit',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Count'], [new AstNode('text', ['text' => 'Count'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'posts-body',
                    'data-group' => 'posts',
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'pages-body',
                    'data-group' => 'pages',
                ],
            ], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Pages'], [new AstNode('text', ['text' => 'Pages'])]),
                    new AstNode('table_cell', ['text' => '5'], [new AstNode('text', ['text' => '5'])]),
                ]),
            ]),
        ]);
        $singleBodyTable = new AstNode('table', [
            'caption' => 'Single body group audit',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(['markdown-table-bodies-flattened'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(['asciidoc-table-bodies-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same(['latex-table-bodies-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same($asciidocDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'adoc'));
        $t->same($latexDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));

        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('multiple-table-bodies', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('body-row-group-boundaries', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('pandoc-table-bodies', $markdownDiagnostics[0]['source'] ?? null);
        $t->same('Multiple body group audit', $markdownDiagnostics[0]['caption'] ?? null);
        $t->same(true, $markdownDiagnostics[0]['hasCaption'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['columnCount'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['sectionCount'] ?? null);
        $t->same(4, $markdownDiagnostics[0]['rowCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['bodyCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['headRowCount'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['bodyRowCount'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['footRowCount'] ?? null);
        $t->same(['body', 'body1'], $markdownDiagnostics[0]['bodySections'] ?? null);
        $t->same([2, 1], $markdownDiagnostics[0]['bodySectionRowCounts'] ?? null);
        $t->same([
            ['section' => 'head', 'rowRange' => [0, 1], 'rowCount' => 1, 'rowRole' => 'head'],
            ['section' => 'body', 'rowRange' => [1, 3], 'rowCount' => 2, 'rowRole' => 'body'],
            ['section' => 'body1', 'rowRange' => [3, 4], 'rowCount' => 1, 'rowRole' => 'body'],
        ], $markdownDiagnostics[0]['sectionRanges'] ?? null);
        $t->same([
            ['section' => 'body', 'rowRange' => [1, 3], 'rowCount' => 2, 'rowRole' => 'body'],
            ['section' => 'body1', 'rowRange' => [3, 4], 'rowCount' => 1, 'rowRole' => 'body'],
        ], $markdownDiagnostics[0]['bodySectionRanges'] ?? null);
        $t->same([
            ['section' => 'head', 'rowCount' => 1, 'rowRole' => 'head'],
            ['section' => 'body', 'rowCount' => 2, 'rowRole' => 'body'],
            ['section' => 'body1', 'rowCount' => 1, 'rowRole' => 'body'],
        ], $markdownDiagnostics[0]['sections'] ?? null);

        $t->same('table-body-groups', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('longtable-body-group-review', $latexDiagnostics[0]['requiredFeature'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($singleBodyTable, 'markdown'));

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(3, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-table-bodies-flattened',
            'asciidoc-table-bodies-review-required',
            'latex-table-bodies-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(2, $packet['summary']['bodyGroupCount'] ?? null);
        $t->same(true, $packet['summary']['hasMultipleBodyGroups'] ?? null);
        $t->same('posts-body', $packet['rowGroups'][1]['sourceAttributes']['id'] ?? null);
        $t->same('pages-body', $packet['rowGroups'][2]['sourceAttributes']['id'] ?? null);
        $t->contains('<tbody id="posts-body" data-group="posts"><tr><td style="text-align:left">Posts</td><td style="text-align:right">42</td></tr><tr><td style="text-align:left">Media</td><td style="text-align:right">7</td></tr></tbody><tbody id="pages-body" data-group="pages"><tr><td style="text-align:left">Pages</td><td style="text-align:right">5</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
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
    'preserves source table cell attributes while computing accessibility handoff ids' => static function (TestRunner $t) use ($buildAttributedCellDocument): void {
        $document = $buildAttributedCellDocument();
        $table = $document->children[0];
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Source Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Source Grid']);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('docx-source-scope', $accessibility['head:0:0:0']['id'] ?? null);
        $t->same('col', $accessibility['head:0:0:0']['scope'] ?? null);
        $t->same('ast-status-source', $accessibility['head:0:1:1']['id'] ?? null);
        $t->same(['docx-source-scope'], $accessibility['body:0:0:0']['headers'] ?? null);
        $t->same(['legacy-status'], $accessibility['body:0:1:1']['headers'] ?? null);
        $t->same('docx-source-scope', $packet['accessibility']['head:0:0:0']['id'] ?? null);
        $t->same('ast-status-source', $packet['accessibility']['head:0:1:1']['id'] ?? null);
        $t->same(['docx-source-scope'], $packet['accessibility']['body:0:0:0']['headers'] ?? null);
        $t->same('Scope', $packet['sections'][0]['rows'][0]['slots'][0]['text']);
        $t->contains('<th scope="col" id="docx-source-scope" class="source-cell" data-origin="docx" style="text-align:left">Scope</th>', $blocks);
        $t->contains('<th scope="col" id="ast-status-source" class="ast-header" style="text-align:right">Status</th>', $blocks);
        $t->contains('<td headers="docx-source-scope" class="body-source" data-origin="docx" style="text-align:left">Posts</td>', $blocks);
        $t->contains('<td headers="legacy-status" data-origin="docx" style="text-align:right">Ready</td>', $blocks);
        $t->true(!str_contains($blocks, 'headers="source-grid-head-r1c2" data-origin="docx" headers="legacy-status"'), 'Source headers attribute must not be duplicated by computed accessibility headers');
    },
    'serializes source header abbreviations for accessibility review packets' => static function (TestRunner $t) use ($buildAbbreviatedHeaderDocument): void {
        $document = $buildAbbreviatedHeaderDocument();
        $table = $document->children[0];
        $associations = TableGeometry::headerAssociations($table, 'Abbr Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Abbr Grid']);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, $associations['summary']['headerCellCount'] ?? null);
        $t->same(2, $associations['summary']['headerAbbreviationCount'] ?? null);
        $t->same(true, $associations['summary']['hasHeaderAbbreviations'] ?? null);
        $t->same('Doc', $associations['headerCells'][0]['abbr'] ?? null);
        $t->same('St', $associations['headerCells'][1]['abbr'] ?? null);
        $t->same('source-document', $associations['headerCells'][0]['id'] ?? null);
        $t->same('abbr-grid-head-r1c2', $associations['headerCells'][1]['id'] ?? null);
        $t->same(['source-document'], $associations['dataCells'][0]['headers'] ?? null);
        $t->same(['abbr-grid-head-r1c2'], $associations['dataCells'][1]['headers'] ?? null);
        $t->same($associations, $packet['headerAssociations'] ?? null);
        $t->same(2, $packet['summary']['headerAbbreviationCount'] ?? null);
        $t->same(true, $packet['summary']['hasHeaderAbbreviations'] ?? null);
        $t->contains('<th scope="col" id="source-document" abbr="Doc" style="text-align:left">Document</th>', $blocks);
        $t->contains('<th id="abbr-grid-head-r1c2" scope="col" abbr="St" style="text-align:right">Status</th>', $blocks);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports source header abbreviation writer handoff diagnostics' => static function (TestRunner $t) use ($buildAbbreviatedHeaderDocument): void {
        $table = $buildAbbreviatedHeaderDocument()->children[0];
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Abbr Grid',
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);

        $t->same([
            'markdown-table-source-attributes-require-raw-html',
            'markdown-header-abbreviation-require-raw-html',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same([
            'asciidoc-table-source-attributes-require-raw-html',
            'asciidoc-header-abbreviation-review-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same([
            'latex-table-source-attributes-review-required',
            'latex-header-abbreviation-review-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));
        $t->same($asciidocDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'adoc'));
        $t->same($latexDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));

        $diagnostic = $markdownDiagnostics[1] ?? [];
        $t->same('markdown', $diagnostic['writer'] ?? null);
        $t->same('header-abbreviation', $diagnostic['reason'] ?? null);
        $t->same('raw-html-table-header-abbr', $diagnostic['requiredFeature'] ?? null);
        $t->same('html-table-abbr', $diagnostic['source'] ?? null);
        $t->same('Abbreviated header review grid', $diagnostic['caption'] ?? null);
        $t->same(2, $diagnostic['headerAbbreviationCount'] ?? null);
        $t->same(true, $diagnostic['hasHeaderAbbreviations'] ?? null);
        $t->same(['Doc', 'St'], $diagnostic['abbreviations'] ?? null);
        $t->same(2, count($diagnostic['headerCells'] ?? []));
        $t->same('source-document', $diagnostic['headerCells'][0]['id'] ?? null);
        $t->same('Doc', $diagnostic['headerCells'][0]['abbr'] ?? null);
        $t->same('abbr-grid-head-r1c2', $diagnostic['headerCells'][1]['id'] ?? null);
        $t->same('St', $diagnostic['headerCells'][1]['abbr'] ?? null);

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->same(6, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-table-source-attributes-require-raw-html',
            'markdown-header-abbreviation-require-raw-html',
            'asciidoc-table-source-attributes-require-raw-html',
            'asciidoc-header-abbreviation-review-required',
            'latex-table-source-attributes-review-required',
            'latex-header-abbreviation-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($asciidocDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($latexDiagnostics, JSON_THROW_ON_ERROR);
    },
    'honors source scope and headers attributes in table accessibility packets' => static function (TestRunner $t) use ($buildSourceScopedHeaderDocument): void {
        $document = $buildSourceScopedHeaderDocument();
        $table = $document->children[0];
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Source Scope Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Source Scope Grid']);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('row', $accessibility['body:0:0:0']['scope'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $accessibility['body:0:1:1']['headers'] ?? null);
        $t->same(['source-state', 'source-posts'], $accessibility['body:0:2:2']['headers'] ?? null);
        $t->same(['source-count'], $accessibility['body:1:0:0']['headers'] ?? null);
        $t->same(['source-state'], $accessibility['body:1:1:1']['headers'] ?? null);
        $t->true(!in_array('source-posts', $accessibility['body:1:0:0']['headers'] ?? [], true), 'Source scope=row must not behave like computed rowgroup on rowspans');
        $t->same(['source-document'], $accessibility['head:0:2:2']['headers'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $packet['accessibility']['body:0:1:1']['headers'] ?? null);
        $t->same('row', $packet['accessibility']['body:0:0:0']['scope'] ?? null);
        $t->contains('<th id="source-posts" scope="row" rowspan="2" style="text-align:left">Posts</th><td headers="legacy-count source-posts" style="text-align:right">42</td><td headers="source-state source-posts" style="text-align:center">Ready</td>', $blocks);
        $t->contains('<tr><td headers="source-count" style="text-align:right">7</td><td headers="source-state" style="text-align:center">Review</td></tr>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports invalid source scope values while falling back to computed header scope' => static function (TestRunner $t) use ($buildInvalidSourceScopeDocument): void {
        $document = $buildInvalidSourceScopeDocument();
        $table = $document->children[0];
        $diagnostics = TableGeometry::diagnostics($table);
        $invalidScopeDiagnostics = array_values(array_filter(
            $diagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'table-header-scope-invalid'
        ));
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Invalid Scope Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Invalid Scope Grid']);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($invalidScopeDiagnostics));
        $diagnostic = $invalidScopeDiagnostics[0] ?? [];
        $t->same('html-table-scope', $diagnostic['source'] ?? null);
        $t->same('htmlAttributes', $diagnostic['attributeSource'] ?? null);
        $t->same('head', $diagnostic['section'] ?? null);
        $t->same(0, $diagnostic['row'] ?? null);
        $t->same(0, $diagnostic['column'] ?? null);
        $t->same(0, $diagnostic['sourceCell'] ?? null);
        $t->same(0, $diagnostic['sourceColumn'] ?? null);
        $t->same(0, $diagnostic['sourceRow'] ?? null);
        $t->same([0, 1], $diagnostic['sourceRowRange'] ?? null);
        $t->same('columnish', $diagnostic['rawScope'] ?? null);
        $t->same(['auto', 'col', 'row', 'colgroup', 'rowgroup'], $diagnostic['allowedScopes'] ?? null);
        $t->same('col', $diagnostic['fallbackScope'] ?? null);
        $t->same(true, $diagnostic['headerCell'] ?? null);
        $t->same('Document', $diagnostic['text'] ?? null);

        $t->same('invalid-scope-document', $accessibility['head:0:0:0']['id'] ?? null);
        $t->same('col', $accessibility['head:0:0:0']['scope'] ?? null);
        $t->same(['invalid-scope-document'], $accessibility['body:0:0:0']['headers'] ?? null);
        $t->same(['table-header-scope-invalid'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(true, $packet['summary']['hasInvalidSourceScopes'] ?? null);
        $t->same(1, $packet['summary']['invalidSourceScopeCount'] ?? null);
        $t->same(['columnish'], $packet['summary']['invalidSourceScopes'] ?? null);
        $t->same('columnish', $packet['sections'][0]['rows'][0]['slots'][0]['sourceAttributes']['htmlAttributes']['scope'] ?? null);
        $t->same('col', $packet['headerAssociations']['headerCells'][0]['scope'] ?? null);
        $t->true(!isset($packet['headerAssociations']['headerCells'][0]['sourceScope']), 'Invalid source scope must not override computed header scope');
        $t->contains('<th scope="col" id="invalid-scope-document" style="text-align:left">Document</th>', $blocks);
        $t->contains('<td headers="invalid-scope-document" style="text-align:left">Posts</td>', $blocks);
        $t->true(!str_contains($blocks, 'scope="columnish"'), 'Invalid source scope must not leak into WordPress output');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'applies explicit source rowgroup headers across the current row group only' => static function (TestRunner $t) use ($buildSourceRowgroupHeaderDocument): void {
        $document = $buildSourceRowgroupHeaderDocument();
        $table = $document->children[0];
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Source Rowgroup Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Source Rowgroup Grid']);
        $associations = $packet['headerAssociations'] ?? [];
        $rowHeaderMap = $packet['rowHeaderMap'] ?? [];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('rowgroup', $accessibility['body:0:0:0']['scope'] ?? null);
        $t->same([], $accessibility['body:0:0:0']['headers'] ?? null);
        $t->same(['source-rg-count', 'source-media-group'], $accessibility['body:0:1:1']['headers'] ?? null);
        $t->same(['source-rg-state', 'source-media-group'], $accessibility['body:0:2:2']['headers'] ?? null);
        $t->same(['source-rg-scope', 'source-media-group'], $accessibility['body:1:0:0']['headers'] ?? null);
        $t->same(['source-rg-count', 'source-media-group'], $accessibility['body:1:1:1']['headers'] ?? null);
        $t->same(['source-rg-state', 'source-media-group'], $accessibility['body:1:2:2']['headers'] ?? null);
        $t->same(['source-rg-scope'], $accessibility['body1:0:0:0']['headers'] ?? null);
        $t->same(['source-rg-count'], $accessibility['body1:0:1:1']['headers'] ?? null);
        $t->same(['source-rg-state'], $accessibility['body1:0:2:2']['headers'] ?? null);
        $t->true(!in_array('source-media-group', $accessibility['body1:0:0:0']['headers'] ?? [], true), 'Source scope=rowgroup must not cross tbody boundaries');

        $t->same(4, $associations['summary']['headerCellCount'] ?? null);
        $t->same(8, $associations['summary']['dataCellCount'] ?? null);
        $t->same(8, $associations['summary']['associatedDataCellCount'] ?? null);
        $t->same(13, $associations['summary']['associationCount'] ?? null);
        $t->same(['col', 'rowgroup'], $associations['summary']['headerScopes'] ?? null);
        $t->same('rowgroup', $associations['headerCells'][3]['scope'] ?? null);
        $t->same('rowgroup', $associations['headerCells'][3]['sourceScope'] ?? null);
        $t->same(['source-rg-scope', 'source-media-group'], $associations['dataCells'][2]['headers'] ?? null);
        $t->same(['source-rg-state', 'source-media-group'], $associations['dataCells'][4]['headers'] ?? null);
        $t->same(['source-rg-scope'], $associations['dataCells'][5]['headers'] ?? null);

        $t->same(3, $rowHeaderMap['summary']['dataRowCount'] ?? null);
        $t->same(2, $rowHeaderMap['summary']['labeledDataRowCount'] ?? null);
        $t->same(1, $rowHeaderMap['summary']['unlabeledDataRowCount'] ?? null);
        $t->same(1, $rowHeaderMap['summary']['rowHeaderCellCount'] ?? null);
        $t->same(2, $rowHeaderMap['summary']['rowHeaderReferenceCount'] ?? null);
        $t->same(['rowgroup'], $rowHeaderMap['summary']['rowHeaderScopes'] ?? null);
        $t->same(false, $rowHeaderMap['summary']['hasRowspanRowHeaders'] ?? null);
        $t->same(0, $rowHeaderMap['summary']['rowspannedRowHeaderReferenceCount'] ?? null);
        $t->same(['source-media-group'], $rowHeaderMap['rows'][0]['headerIds'] ?? null);
        $t->same(['source-media-group'], $rowHeaderMap['rows'][1]['headerIds'] ?? null);
        $t->same([], $rowHeaderMap['rows'][2]['headerIds'] ?? null);
        $t->same(true, $rowHeaderMap['rows'][2]['unlabeled'] ?? null);
        $t->same('rowgroup', $rowHeaderMap['rows'][0]['headers'][0]['sourceScope'] ?? null);

        $t->same(13, $packet['summary']['headerAssociationCount'] ?? null);
        $t->same(8, $packet['summary']['associatedDataCellCount'] ?? null);
        $t->same(2, $packet['summary']['rowHeaderLabeledDataRowCount'] ?? null);
        $t->same(1, $packet['summary']['rowHeaderUnlabeledDataRowCount'] ?? null);
        $t->same(false, $packet['summary']['hasRowspanRowHeaders'] ?? null);
        $t->same(['rowgroup'], $packet['summary']['rowHeaderScopes'] ?? null);

        $t->contains('<tbody id="media-body"><tr><th id="source-media-group" scope="rowgroup" style="text-align:left">Media</th><td headers="source-rg-count source-media-group" style="text-align:right">7</td><td headers="source-rg-state source-media-group" style="text-align:center">Needs alt</td></tr><tr><td headers="source-rg-scope source-media-group" style="text-align:left">Images</td><td headers="source-rg-count source-media-group" style="text-align:right">3</td><td headers="source-rg-state source-media-group" style="text-align:center">Review</td></tr></tbody>', $blocks);
        $t->contains('<tbody id="pages-body"><tr><td headers="source-rg-scope" style="text-align:left">Pages</td><td headers="source-rg-count" style="text-align:right">5</td><td headers="source-rg-state" style="text-align:center">Ready</td></tr></tbody>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'applies explicit source colgroup headers across parsed column groups' => static function (TestRunner $t) use ($buildSourceColgroupHeaderDocument): void {
        $document = $buildSourceColgroupHeaderDocument();
        $table = $document->children[0];
        $accessibility = TableGeometry::accessibilityAttributes($table, 'Source Colgroup Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Source Colgroup Grid']);
        $associations = $packet['headerAssociations'] ?? [];
        $matrix = $packet['rowMatrix'] ?? [];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('colgroup', $accessibility['head:0:0:0']['scope'] ?? null);
        $t->same([0, 1], $accessibility['head:0:0:0']['columns'] ?? null);
        $t->same([0, 1], $accessibility['head:0:0:0']['sourceColumnGroup']['columns'] ?? null);
        $t->same('source-import-columns', $accessibility['head:0:0:0']['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope'], $accessibility['body:0:0:0']['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $accessibility['body:0:1:1']['headers'] ?? null);
        $t->same(['source-state'], $accessibility['body:0:2:2']['headers'] ?? null);
        $t->same(['source-import-scope'], $accessibility['body:1:0:0']['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $accessibility['body:1:1:1']['headers'] ?? null);
        $t->same(['source-state'], $accessibility['body:1:2:2']['headers'] ?? null);

        $t->same(3, $associations['summary']['headerCellCount'] ?? null);
        $t->same(6, $associations['summary']['dataCellCount'] ?? null);
        $t->same(6, $associations['summary']['associatedDataCellCount'] ?? null);
        $t->same(8, $associations['summary']['associationCount'] ?? null);
        $t->same(['colgroup', 'col'], $associations['summary']['headerScopes'] ?? null);
        $t->same('colgroup', $associations['headerCells'][0]['scope'] ?? null);
        $t->same('colgroup', $associations['headerCells'][0]['sourceScope'] ?? null);
        $t->same([0, 1], $associations['headerCells'][0]['columns'] ?? null);
        $t->same([0, 1], $associations['headerCells'][0]['sourceColumnGroup']['columns'] ?? null);
        $t->same('source-import-columns', $associations['headerCells'][0]['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope'], $associations['dataCells'][0]['headers'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $associations['dataCells'][1]['headers'] ?? null);
        $t->same(['source-state'], $associations['dataCells'][2]['headers'] ?? null);
        $t->same([0, 1], $matrix['rows'][0]['headerCells'][0]['columns'] ?? null);
        $t->same('source-import-columns', $matrix['rows'][0]['headerCells'][0]['sourceColumnGroup']['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->same(['source-import-scope', 'source-items'], $matrix['rows'][1]['dataCells'][1]['headers'] ?? null);

        $t->same(8, $packet['summary']['headerAssociationCount'] ?? null);
        $t->same(6, $packet['summary']['associatedDataCellCount'] ?? null);
        $t->same([0, 1], $packet['columnGroups'][0]['columns'] ?? null);
        $t->same('source-import-columns', $packet['columnGroups'][0]['source']['colgroupAttributes']['htmlAttributes']['id'] ?? null);
        $t->contains('<colgroup id="source-import-columns" data-origin="legacy-doc"><col style="width:33.3333%"/><col style="width:33.3333%"/></colgroup><colgroup id="source-state-column" data-origin="legacy-doc"><col style="width:33.3333%"/></colgroup>', $blocks);
        $t->contains('<th id="source-import-scope" scope="colgroup" style="text-align:left">Import scope</th><th id="source-items" scope="col" style="text-align:right">Items</th><th id="source-state" scope="col" style="text-align:center">State</th>', $blocks);
        $t->contains('<td headers="source-import-scope" style="text-align:left">Posts</td><td headers="source-import-scope source-items" style="text-align:right">42</td><td headers="source-state" style="text-align:center">Ready</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($matrix, JSON_THROW_ON_ERROR);
    },
    'resolves explicit source header references for reviewer audits' => static function (TestRunner $t) use ($buildSourceScopedHeaderDocument): void {
        $table = $buildSourceScopedHeaderDocument()->children[0];
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Source Scope Grid']);
        $associations = $packet['headerAssociations'] ?? [];

        $t->same(2, $associations['summary']['sourceHeaderReferencingCellCount'] ?? null);
        $t->same(3, $associations['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(2, $associations['summary']['sourceHeaderResolvedReferenceCount'] ?? null);
        $t->same(1, $associations['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        $t->same(true, $associations['summary']['hasUnresolvedSourceHeaderReferences'] ?? null);
        $t->same(['legacy-count'], $associations['summary']['unresolvedSourceHeaderReferences'] ?? null);
        $t->same(3, $packet['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(1, $packet['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        $t->same(['legacy-count'], $packet['summary']['unresolvedSourceHeaderReferences'] ?? null);
        $t->same('legacy-count', $associations['dataCells'][0]['sourceHeaderReferences'][0]['id'] ?? null);
        $t->same(false, $associations['dataCells'][0]['sourceHeaderReferences'][0]['resolved'] ?? null);
        $t->same('source-posts', $associations['dataCells'][0]['sourceHeaderReferences'][1]['id'] ?? null);
        $t->same('body:0:0:0', $associations['dataCells'][0]['sourceHeaderReferences'][1]['targetKey'] ?? null);
        $t->same(2, $associations['dataCells'][0]['sourceHeaderReferences'][1]['targetRowspan'] ?? null);
        $t->same([0, 2], $associations['dataCells'][0]['sourceHeaderReferences'][1]['targetSourceRowRange'] ?? null);
        $t->same([1, 3], $associations['dataCells'][0]['sourceHeaderReferences'][1]['targetGlobalRowRange'] ?? null);
        $t->same('source-document', $associations['headerCells'][2]['sourceHeaderReferences'][0]['id'] ?? null);
        $t->same('head:0:0:0', $associations['headerCells'][2]['sourceHeaderReferences'][0]['targetKey'] ?? null);
        $t->same(1, $associations['headerCells'][2]['sourceHeaderReferences'][0]['targetRowspan'] ?? null);
        $t->same([0], $associations['headerCells'][2]['sourceHeaderReferences'][0]['targetGlobalRows'] ?? null);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes resolved source header target geometry for spanned headers' => static function (TestRunner $t) use ($buildSourceHeaderReferenceGeometryDocument): void {
        $table = $buildSourceHeaderReferenceGeometryDocument()->children[0];
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Reference Geometry Grid']);
        $associations = $packet['headerAssociations'] ?? [];
        $matrix = $packet['rowMatrix'] ?? [];

        $scopeReferences = $associations['dataCells'][0]['sourceHeaderReferences'] ?? [];
        $stateReferences = $associations['dataCells'][1]['sourceHeaderReferences'] ?? [];
        $secondRowReferences = $associations['dataCells'][2]['sourceHeaderReferences'] ?? [];

        $t->same(3, $associations['summary']['headerCellCount'] ?? null);
        $t->same(4, $associations['summary']['dataCellCount'] ?? null);
        $t->same(4, $associations['summary']['sourceHeaderReferencingCellCount'] ?? null);
        $t->same(8, $associations['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(8, $associations['summary']['sourceHeaderResolvedReferenceCount'] ?? null);
        $t->same(0, $associations['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);

        $t->same('source-scope-span', $scopeReferences[0]['id'] ?? null);
        $t->same('head:0:0:0', $scopeReferences[0]['targetKey'] ?? null);
        $t->same('colgroup', $scopeReferences[0]['targetScope'] ?? null);
        $t->same(2, $scopeReferences[0]['targetColspan'] ?? null);
        $t->same(1, $scopeReferences[0]['targetRowspan'] ?? null);
        $t->same([0, 1], $scopeReferences[0]['targetColumns'] ?? null);
        $t->same(0, $scopeReferences[0]['targetSourceRow'] ?? null);
        $t->same(1, $scopeReferences[0]['targetSourceRowEnd'] ?? null);
        $t->same([0, 1], $scopeReferences[0]['targetSourceRowRange'] ?? null);
        $t->same([0], $scopeReferences[0]['targetSourceRows'] ?? null);
        $t->same(0, $scopeReferences[0]['targetGlobalRow'] ?? null);
        $t->same(1, $scopeReferences[0]['targetGlobalRowEnd'] ?? null);
        $t->same([0, 1], $scopeReferences[0]['targetGlobalRowRange'] ?? null);
        $t->same([0], $scopeReferences[0]['targetGlobalRows'] ?? null);

        $t->same('source-posts-group', $scopeReferences[1]['id'] ?? null);
        $t->same('body:0:0:0', $scopeReferences[1]['targetKey'] ?? null);
        $t->same('rowgroup', $scopeReferences[1]['targetScope'] ?? null);
        $t->same(1, $scopeReferences[1]['targetColspan'] ?? null);
        $t->same(2, $scopeReferences[1]['targetRowspan'] ?? null);
        $t->same([0], $scopeReferences[1]['targetColumns'] ?? null);
        $t->same(0, $scopeReferences[1]['targetSourceRow'] ?? null);
        $t->same(2, $scopeReferences[1]['targetSourceRowEnd'] ?? null);
        $t->same([0, 2], $scopeReferences[1]['targetSourceRowRange'] ?? null);
        $t->same([0, 1], $scopeReferences[1]['targetSourceRows'] ?? null);
        $t->same(1, $scopeReferences[1]['targetGlobalRow'] ?? null);
        $t->same(3, $scopeReferences[1]['targetGlobalRowEnd'] ?? null);
        $t->same([1, 3], $scopeReferences[1]['targetGlobalRowRange'] ?? null);
        $t->same([1, 2], $scopeReferences[1]['targetGlobalRows'] ?? null);

        $t->same('source-state-span', $stateReferences[0]['id'] ?? null);
        $t->same('head:0:1:2', $stateReferences[0]['targetKey'] ?? null);
        $t->same(2, $stateReferences[0]['targetColumn'] ?? null);
        $t->same([2], $stateReferences[0]['targetColumns'] ?? null);
        $t->same('source-posts-group', $stateReferences[1]['id'] ?? null);
        $t->same('source-scope-span', $secondRowReferences[0]['id'] ?? null);
        $t->same('source-posts-group', $secondRowReferences[1]['id'] ?? null);
        $t->same([1, 2], $secondRowReferences[1]['targetGlobalRows'] ?? null);
        $t->same([1, 3], $matrix['rows'][1]['dataCells'][0]['sourceHeaderReferences'][1]['targetGlobalRowRange'] ?? null);
        $t->same([0, 2], $matrix['rows'][2]['dataCells'][0]['sourceHeaderReferences'][1]['targetSourceRowRange'] ?? null);

        $t->same(8, $packet['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(0, $packet['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports duplicate source header ids and ambiguous headers references' => static function (TestRunner $t) use ($buildDuplicateSourceHeaderDocument): void {
        $document = $buildDuplicateSourceHeaderDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Duplicate Header Grid',
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);
        $associations = $packet['headerAssociations'] ?? [];
        $diagnostics = TableGeometry::diagnostics($table);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, $associations['summary']['duplicateHeaderIdCount'] ?? null);
        $t->same(true, $associations['summary']['hasDuplicateHeaderIds'] ?? null);
        $t->same(['duplicate-document'], $associations['summary']['duplicateHeaderIds'] ?? null);
        $t->same(2, $associations['summary']['sourceHeaderReferencingCellCount'] ?? null);
        $t->same(3, $associations['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(2, $associations['summary']['sourceHeaderResolvedReferenceCount'] ?? null);
        $t->same(1, $associations['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        $t->same(2, $associations['summary']['sourceHeaderAmbiguousReferenceCount'] ?? null);
        $t->same(true, $associations['summary']['hasAmbiguousSourceHeaderReferences'] ?? null);
        $t->same(['duplicate-document'], $associations['summary']['ambiguousSourceHeaderReferences'] ?? null);
        $t->same(['missing-document'], $associations['summary']['unresolvedSourceHeaderReferences'] ?? null);
        $t->same(1, $packet['summary']['duplicateHeaderIdCount'] ?? null);
        $t->same(['duplicate-document'], $packet['summary']['duplicateHeaderIds'] ?? null);
        $t->same(2, $packet['summary']['sourceHeaderAmbiguousReferenceCount'] ?? null);
        $t->same(['duplicate-document'], $packet['summary']['ambiguousSourceHeaderReferences'] ?? null);
        $t->same(['table-header-id-duplicated'], $packet['summary']['diagnosticCodes'] ?? null);

        $t->same(['table-header-id-duplicated'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same('duplicate-document', $diagnostics[0]['id'] ?? null);
        $t->same(2, $diagnostics[0]['headerCellCount'] ?? null);
        $t->same(['head:0:0:0', 'head:0:1:1'], array_map(static fn (array $location): string => (string) ($location['key'] ?? ''), $diagnostics[0]['locations'] ?? []));

        $headerReference = $associations['headerCells'][2]['sourceHeaderReferences'][0] ?? [];
        $dataReferences = $associations['dataCells'][1]['sourceHeaderReferences'] ?? [];
        $t->same(true, $headerReference['resolved'] ?? null);
        $t->same(true, $headerReference['ambiguous'] ?? null);
        $t->same(2, $headerReference['targetCount'] ?? null);
        $t->same('head:0:0:0', $headerReference['targetKey'] ?? null);
        $t->same(['head:0:0:0', 'head:0:1:1'], array_map(static fn (array $target): string => (string) ($target['targetKey'] ?? ''), $headerReference['targets'] ?? []));
        $t->same('duplicate-document', $dataReferences[0]['id'] ?? null);
        $t->same(true, $dataReferences[0]['ambiguous'] ?? null);
        $t->same(2, $dataReferences[0]['targetCount'] ?? null);
        $t->same('missing-document', $dataReferences[1]['id'] ?? null);
        $t->same(false, $dataReferences[1]['resolved'] ?? null);

        $t->same(['markdown-source-headers-require-raw-html'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(2, $markdownDiagnostics[0]['ambiguousReferenceCount'] ?? null);
        $t->same(true, $markdownDiagnostics[0]['hasAmbiguousReferences'] ?? null);
        $t->same(['duplicate-document'], $markdownDiagnostics[0]['ambiguousReferences'] ?? null);
        $t->same(['missing-document'], $markdownDiagnostics[0]['unresolvedReferences'] ?? null);
        $t->same($markdownDiagnostics[0], $packet['writerDowngrades']['markdown'][0] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->contains('<th id="duplicate-document" scope="col" style="text-align:left">Document A</th><th id="duplicate-document" scope="col" style="text-align:right">Document B</th><th id="duplicate-state" scope="col" headers="duplicate-document" style="text-align:center">State</th>', $blocks);
        $t->contains('<td headers="duplicate-document missing-document" style="text-align:right">42</td>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports duplicate source headers tokens without changing resolved references' => static function (TestRunner $t) use ($buildDuplicateSourceHeaderTokenDocument): void {
        $document = $buildDuplicateSourceHeaderTokenDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Duplicate Token Grid',
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);
        $associations = $packet['headerAssociations'] ?? [];
        $diagnostics = TableGeometry::diagnostics($table);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, $associations['summary']['duplicateSourceHeaderTokenCellCount'] ?? null);
        $t->same(2, $associations['summary']['duplicateSourceHeaderTokenCount'] ?? null);
        $t->same(true, $associations['summary']['hasDuplicateSourceHeaderTokens'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $associations['summary']['duplicateSourceHeaderTokens'] ?? null);
        $t->same(3, $associations['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(3, $associations['summary']['sourceHeaderResolvedReferenceCount'] ?? null);
        $t->same(0, $associations['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        $t->same(0, $associations['summary']['sourceHeaderAmbiguousReferenceCount'] ?? null);

        $t->same(2, $packet['summary']['sourceHeaderDuplicateTokenCellCount'] ?? null);
        $t->same(2, $packet['summary']['sourceHeaderDuplicateTokenCount'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $packet['summary']['sourceHeaderDuplicateTokens'] ?? null);
        $t->same(true, $packet['summary']['hasDuplicateSourceHeaderTokens'] ?? null);
        $t->same(2, $packet['summary']['duplicateSourceHeaderTokenCount'] ?? null);
        $t->same(2, $packet['summary']['duplicateSourceHeaderTokenCellCount'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $packet['summary']['duplicateSourceHeaderTokens'] ?? null);
        $t->same(['table-source-headers-duplicate-tokens'], $packet['summary']['diagnosticCodes'] ?? null);

        $t->same(['table-source-headers-duplicate-tokens'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same(2, $diagnostics[0]['duplicateTokenCellCount'] ?? null);
        $t->same(2, $diagnostics[0]['duplicateTokenCount'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $diagnostics[0]['duplicateTokens'] ?? null);
        $t->same(['header', 'data'], array_map(static fn (array $cell): string => (string) ($cell['role'] ?? ''), $diagnostics[0]['cells'] ?? []));
        $t->same('head:0:2:2', $diagnostics[0]['cells'][0]['key'] ?? null);
        $t->same(['dup-token-document'], $diagnostics[0]['cells'][0]['duplicateSourceHeaderTokens'] ?? null);
        $t->same(2, $diagnostics[0]['cells'][0]['sourceHeaderTokenCount'] ?? null);
        $t->same(1, $diagnostics[0]['cells'][0]['sourceHeaderUniqueTokenCount'] ?? null);
        $t->same('body:0:1:1', $diagnostics[0]['cells'][1]['key'] ?? null);
        $t->same(['dup-token-count'], $diagnostics[0]['cells'][1]['duplicateSourceHeaderTokens'] ?? null);
        $t->same(3, $diagnostics[0]['cells'][1]['sourceHeaderTokenCount'] ?? null);
        $t->same(2, $diagnostics[0]['cells'][1]['sourceHeaderUniqueTokenCount'] ?? null);

        $headerReference = $associations['headerCells'][2]['sourceHeaderReferences'][0] ?? [];
        $dataReferences = $associations['dataCells'][1]['sourceHeaderReferences'] ?? [];
        $t->same('dup-token-document', $headerReference['id'] ?? null);
        $t->same('head:0:0:0', $headerReference['targetKey'] ?? null);
        $t->same(1, count($associations['headerCells'][2]['sourceHeaderReferences'] ?? []));
        $t->same(['dup-token-document'], $associations['headerCells'][2]['duplicateSourceHeaderTokens'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $associations['dataCells'][1]['sourceHeaders'] ?? null);
        $t->same(2, count($dataReferences));
        $t->same('dup-token-count', $dataReferences[1]['id'] ?? null);
        $t->same('head:0:1:1', $dataReferences[1]['targetKey'] ?? null);

        $t->same(2, $markdownDiagnostics[0]['duplicateTokenCellCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['duplicateTokenCount'] ?? null);
        $t->same(true, $markdownDiagnostics[0]['hasDuplicateTokens'] ?? null);
        $t->same(['dup-token-document', 'dup-token-count'], $markdownDiagnostics[0]['duplicateTokens'] ?? null);
        $t->same(['dup-token-count'], $markdownDiagnostics[0]['cells'][1]['duplicateSourceHeaderTokens'] ?? null);
        $t->same($markdownDiagnostics[0], $packet['writerDowngrades']['markdown'][0] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->contains('<th id="dup-token-state" scope="col" headers="dup-token-document" style="text-align:center">State</th>', $blocks);
        $t->contains('<td headers="dup-token-document dup-token-count" style="text-align:right">42</td>', $blocks);
        $t->true(!str_contains($blocks, 'headers="dup-token-document dup-token-count dup-token-count"'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports source header reference writer handoff diagnostics' => static function (TestRunner $t) use ($buildSourceScopedHeaderDocument): void {
        $table = $buildSourceScopedHeaderDocument()->children[0];
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Source Scope Grid',
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);

        $t->same([
            'markdown-row-headers-flattened',
            'markdown-source-headers-require-raw-html',
            'markdown-rowspan-flattened',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same([
            'asciidoc-row-headers-review-required',
            'asciidoc-source-headers-review-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same([
            'latex-row-headers-review-required',
            'latex-source-headers-review-required',
            'latex-multirow-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same($asciidocDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'adoc'));
        $t->same($latexDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));

        $sourceDiagnostic = $markdownDiagnostics[1];
        $t->same('markdown', $sourceDiagnostic['writer'] ?? null);
        $t->same('source-headers', $sourceDiagnostic['reason'] ?? null);
        $t->same('raw-html-table-headers', $sourceDiagnostic['requiredFeature'] ?? null);
        $t->same('html-table-headers', $sourceDiagnostic['source'] ?? null);
        $t->same('Source scoped accessibility grid', $sourceDiagnostic['caption'] ?? null);
        $t->same(2, $sourceDiagnostic['referencingCellCount'] ?? null);
        $t->same(3, $sourceDiagnostic['referenceCount'] ?? null);
        $t->same(2, $sourceDiagnostic['resolvedReferenceCount'] ?? null);
        $t->same(1, $sourceDiagnostic['unresolvedReferenceCount'] ?? null);
        $t->same(true, $sourceDiagnostic['hasUnresolvedReferences'] ?? null);
        $t->same(['legacy-count'], $sourceDiagnostic['unresolvedReferences'] ?? null);
        $t->same(1, $sourceDiagnostic['sourceHeaderOverrideCount'] ?? null);
        $t->same(true, $sourceDiagnostic['hasSourceHeaderOverrides'] ?? null);
        $t->same(2, count($sourceDiagnostic['cells'] ?? []));

        $headerCell = $sourceDiagnostic['cells'][0] ?? [];
        $dataCell = $sourceDiagnostic['cells'][1] ?? [];
        $t->same('header', $headerCell['role'] ?? null);
        $t->same('head:0:2:2', $headerCell['key'] ?? null);
        $t->same('source-state', $headerCell['id'] ?? null);
        $t->same('State', $headerCell['text'] ?? null);
        $t->same(['source-document'], $headerCell['sourceHeaders'] ?? null);
        $t->same('source-document', $headerCell['sourceHeaderReferences'][0]['id'] ?? null);
        $t->same('head:0:0:0', $headerCell['sourceHeaderReferences'][0]['targetKey'] ?? null);
        $t->same('data', $dataCell['role'] ?? null);
        $t->same('body:0:1:1', $dataCell['key'] ?? null);
        $t->same('42', $dataCell['text'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $dataCell['sourceHeaders'] ?? null);
        $t->same(false, $dataCell['sourceHeaderReferences'][0]['resolved'] ?? null);
        $t->same('legacy-count', $dataCell['sourceHeaderReferences'][0]['id'] ?? null);
        $t->same('source-posts', $dataCell['sourceHeaderReferences'][1]['id'] ?? null);
        $t->same('body:0:0:0', $dataCell['sourceHeaderReferences'][1]['targetKey'] ?? null);

        $t->same('source-header-reference-review', $asciidocDiagnostics[1]['requiredFeature'] ?? null);
        $t->same($sourceDiagnostic['cells'], $asciidocDiagnostics[1]['cells'] ?? null);
        $t->same('table-header-reference-comments', $latexDiagnostics[1]['requiredFeature'] ?? null);
        $t->same($sourceDiagnostic['cells'], $latexDiagnostics[1]['cells'] ?? null);
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->same(8, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-row-headers-flattened',
            'markdown-source-headers-require-raw-html',
            'markdown-rowspan-flattened',
            'asciidoc-row-headers-review-required',
            'asciidoc-source-headers-review-required',
            'latex-row-headers-review-required',
            'latex-source-headers-review-required',
            'latex-multirow-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'builds row header maps for importer table review packets' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument, $buildSourceScopedHeaderDocument): void {
        $table = $buildAccessibleHeaderDocument()->children[0];
        $map = TableGeometry::rowHeaderMap($table, 'Migration Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Migration Grid']);

        $t->same(2, $map['summary']['dataRowCount'] ?? null);
        $t->same(2, $map['summary']['labeledDataRowCount'] ?? null);
        $t->same(0, $map['summary']['unlabeledDataRowCount'] ?? null);
        $t->same(1, $map['summary']['rowHeaderCellCount'] ?? null);
        $t->same(2, $map['summary']['rowHeaderReferenceCount'] ?? null);
        $t->same(1, $map['summary']['maxRowHeaderCount'] ?? null);
        $t->same(['rowgroup'], $map['summary']['rowHeaderScopes'] ?? null);
        $t->same(true, $map['summary']['hasRowHeaders'] ?? null);
        $t->same(false, $map['summary']['hasUnlabeledDataRows'] ?? null);
        $t->same(true, $map['summary']['hasRowspanRowHeaders'] ?? null);
        $t->same(2, $map['summary']['rowspannedRowHeaderReferenceCount'] ?? null);

        $t->same('body', $map['rows'][0]['section'] ?? null);
        $t->same(1, $map['rows'][0]['row'] ?? null);
        $t->same('body', $map['rows'][0]['rowRole'] ?? null);
        $t->same(2, $map['rows'][0]['dataCellCount'] ?? null);
        $t->same(1, $map['rows'][0]['headerCount'] ?? null);
        $t->same(['migration-grid-body-r2c1'], $map['rows'][0]['headerIds'] ?? null);
        $t->same(['Posts'], $map['rows'][0]['headerTexts'] ?? null);
        $t->same('body:1:0:0', $map['rows'][0]['headers'][0]['key'] ?? null);
        $t->same('rowgroup', $map['rows'][0]['headers'][0]['scope'] ?? null);
        $t->same([0], $map['rows'][0]['headers'][0]['columns'] ?? null);
        $t->same(2, $map['rows'][0]['headers'][0]['rowspan'] ?? null);
        $t->same(false, $map['rows'][0]['unlabeled'] ?? null);
        $t->same(2, $map['rows'][1]['row'] ?? null);
        $t->same(['migration-grid-body-r2c1'], $map['rows'][1]['headerIds'] ?? null);
        $t->same(['Posts'], $map['rows'][1]['headerTexts'] ?? null);
        $t->same($map, $packet['rowHeaderMap'] ?? null);
        $t->same(2, $packet['summary']['rowHeaderDataRowCount'] ?? null);
        $t->same(1, $packet['summary']['rowHeaderCellCount'] ?? null);
        $t->same(2, $packet['summary']['rowHeaderReferenceCount'] ?? null);
        $t->same(true, $packet['summary']['hasRowHeaders'] ?? null);
        $t->same(false, $packet['summary']['hasUnlabeledDataRows'] ?? null);
        $t->same(['rowgroup'], $packet['summary']['rowHeaderScopes'] ?? null);

        $sourcePacket = TableGeometry::reviewPacket($buildSourceScopedHeaderDocument()->children[0], ['idPrefix' => 'Source Scope Grid']);
        $sourceMap = $sourcePacket['rowHeaderMap'] ?? [];
        $t->same(2, $sourceMap['summary']['dataRowCount'] ?? null);
        $t->same(1, $sourceMap['summary']['labeledDataRowCount'] ?? null);
        $t->same(1, $sourceMap['summary']['unlabeledDataRowCount'] ?? null);
        $t->same(['row'], $sourceMap['summary']['rowHeaderScopes'] ?? null);
        $t->same(['source-posts'], $sourceMap['rows'][0]['headerIds'] ?? null);
        $t->same(['Posts'], $sourceMap['rows'][0]['headerTexts'] ?? null);
        $t->same([], $sourceMap['rows'][1]['headers'] ?? null);
        $t->same(true, $sourceMap['rows'][1]['unlabeled'] ?? null);
        $t->same(1, $sourcePacket['summary']['rowHeaderUnlabeledDataRowCount'] ?? null);
        $t->same(true, $sourcePacket['summary']['hasUnlabeledDataRows'] ?? null);

        $compactPacket = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $t->same([], $compactPacket['rowHeaderMap']['rows'] ?? null);
        $t->same(0, $compactPacket['summary']['rowHeaderDataRowCount'] ?? null);
        json_encode($map, JSON_THROW_ON_ERROR);
        json_encode($sourceMap, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'builds row-oriented visual matrices with header association handoff metadata' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument, $buildSourceScopedHeaderDocument): void {
        $table = $buildAccessibleHeaderDocument()->children[0];
        $matrix = TableGeometry::rowMatrix($table, 'Migration Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Migration Grid']);

        $t->same(4, $matrix['summary']['rowCount'] ?? null);
        $t->same(2, $matrix['summary']['headerRowCount'] ?? null);
        $t->same(2, $matrix['summary']['dataRowCount'] ?? null);
        $t->same(6, $matrix['summary']['headerCellCount'] ?? null);
        $t->same(4, $matrix['summary']['dataCellCount'] ?? null);
        $t->same(4, $matrix['summary']['associatedDataCellCount'] ?? null);
        $t->same(0, $matrix['summary']['unassociatedDataCellCount'] ?? null);
        $t->same(2, $matrix['summary']['coveredRowCount'] ?? null);
        $t->same(0, $matrix['summary']['missingRowCount'] ?? null);
        $t->same(3, $matrix['summary']['maxCellCountPerRow'] ?? null);
        $t->same(3, $matrix['summary']['maxHeaderCellsPerRow'] ?? null);
        $t->same(2, $matrix['summary']['maxDataCellsPerRow'] ?? null);
        $t->same(['head' => 1, 'body-head' => 1, 'body' => 2], $matrix['summary']['rowRoleCounts'] ?? null);

        $t->same('head', $matrix['rows'][0]['section'] ?? null);
        $t->same('head', $matrix['rows'][0]['rowRole'] ?? null);
        $t->same(true, $matrix['rows'][0]['header'] ?? null);
        $t->same(2, $matrix['rows'][0]['headerCellCount'] ?? null);
        $t->same(0, $matrix['rows'][0]['dataCellCount'] ?? null);
        $t->same('Document', $matrix['rows'][0]['headerCells'][0]['text'] ?? null);
        $t->same('migration-grid-head-r1c1', $matrix['rows'][0]['headerCells'][0]['id'] ?? null);
        $t->same('colgroup', $matrix['rows'][0]['headerCells'][0]['scope'] ?? null);
        $t->same([0, 1], $matrix['rows'][0]['headerCells'][0]['columns'] ?? null);

        $t->same('body-head', $matrix['rows'][1]['rowRole'] ?? null);
        $t->same(3, $matrix['rows'][1]['headerCellCount'] ?? null);
        $t->same(['Batch', 'Queue', 'Decision'], array_map(static fn (array $cell): string => $cell['text'], $matrix['rows'][1]['headerCells'] ?? []));

        $postsRow = $matrix['rows'][2] ?? [];
        $t->same('body', $postsRow['section'] ?? null);
        $t->same(1, $postsRow['row'] ?? null);
        $t->same(2, $postsRow['globalRow'] ?? null);
        $t->same(false, $postsRow['header'] ?? null);
        $t->same(1, $postsRow['headerCellCount'] ?? null);
        $t->same(2, $postsRow['dataCellCount'] ?? null);
        $t->same('Posts', $postsRow['headerCells'][0]['text'] ?? null);
        $t->same('migration-grid-body-r2c1', $postsRow['headerCells'][0]['id'] ?? null);
        $t->same('rowgroup', $postsRow['headerCells'][0]['scope'] ?? null);
        $t->same(2, $postsRow['headerCells'][0]['rowspan'] ?? null);
        $t->same('42', $postsRow['dataCells'][0]['text'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $postsRow['dataCells'][0]['headers'] ?? null);
        $t->same(3, $postsRow['dataCells'][0]['headerCount'] ?? null);
        $t->same('Review', $postsRow['dataCells'][1]['text'] ?? null);
        $t->same(['migration-grid-head-r1c3', 'migration-grid-body-r1c3', 'migration-grid-body-r2c1'], $postsRow['dataCells'][1]['headers'] ?? null);

        $coveredRow = $matrix['rows'][3] ?? [];
        $t->same(1, $coveredRow['coveredSlotCount'] ?? null);
        $t->same(0, $coveredRow['missingSlotCount'] ?? null);
        $t->same('rowspan', $coveredRow['coveredSlots'][0]['covering'] ?? null);
        $t->same('body:1:0:0', $coveredRow['coveredSlots'][0]['anchorKey'] ?? null);
        $t->same('7', $coveredRow['dataCells'][0]['text'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $coveredRow['dataCells'][0]['headers'] ?? null);
        $t->same('Import', $coveredRow['dataCells'][1]['text'] ?? null);
        $t->same(['migration-grid-head-r1c3', 'migration-grid-body-r1c3', 'migration-grid-body-r2c1'], $coveredRow['dataCells'][1]['headers'] ?? null);

        $t->same($matrix, $packet['rowMatrix'] ?? null);
        $t->same(4, $packet['summary']['rowMatrixRowCount'] ?? null);
        $t->same(4, $packet['summary']['rowMatrixAssociatedDataCellCount'] ?? null);
        $t->same(true, $packet['summary']['hasRowMatrixHeaderAssociations'] ?? null);

        $sourceMatrix = TableGeometry::rowMatrix($buildSourceScopedHeaderDocument()->children[0], 'Source Scope Grid');
        $t->same(2, $sourceMatrix['summary']['dataRowCount'] ?? null);
        $t->same(4, $sourceMatrix['summary']['associatedDataCellCount'] ?? null);
        $t->same(0, $sourceMatrix['summary']['unassociatedDataCellCount'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $sourceMatrix['rows'][1]['dataCells'][0]['headers'] ?? null);
        $t->same(['source-count'], $sourceMatrix['rows'][2]['dataCells'][0]['headers'] ?? null);
        $t->same(['source-state'], $sourceMatrix['rows'][2]['dataCells'][1]['headers'] ?? null);

        $compactPacket = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $t->same([], $compactPacket['rowMatrix']['rows'][2]['dataCells'][0]['headers'] ?? null);
        $t->same(0, $compactPacket['summary']['rowMatrixAssociatedDataCellCount'] ?? null);
        json_encode($matrix, JSON_THROW_ON_ERROR);
        json_encode($sourceMatrix, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'builds flattened visual grids for fallback writer handoffs' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument): void {
        $table = $buildAccessibleHeaderDocument()->children[0];
        $flatGrid = TableGeometry::flatGrid($table);
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Migration Grid']);

        $t->same(3, $flatGrid['columnCount'] ?? null);
        $t->same(4, $flatGrid['summary']['rowCount'] ?? null);
        $t->same(12, $flatGrid['summary']['slotCount'] ?? null);
        $t->same(10, $flatGrid['summary']['anchorSlotCount'] ?? null);
        $t->same(2, $flatGrid['summary']['coveredSlotCount'] ?? null);
        $t->same(0, $flatGrid['summary']['missingSlotCount'] ?? null);
        $t->same(2, $flatGrid['summary']['spanAnchorCount'] ?? null);
        $t->same(1, $flatGrid['summary']['colspanAnchorCount'] ?? null);
        $t->same(1, $flatGrid['summary']['rowspanAnchorCount'] ?? null);
        $t->same(true, $flatGrid['summary']['hasCoveredSlots'] ?? null);
        $t->same(false, $flatGrid['summary']['hasMissingSlots'] ?? null);
        $t->same(['head', 'body'], $flatGrid['summary']['sections'] ?? null);

        $headRow = $flatGrid['rows'][0] ?? [];
        $t->same('head', $headRow['section'] ?? null);
        $t->same(true, $headRow['header'] ?? null);
        $t->same(3, $headRow['slotCount'] ?? null);
        $t->same(2, $headRow['anchorSlotCount'] ?? null);
        $t->same(1, $headRow['coveredSlotCount'] ?? null);
        $t->same('cell', $headRow['cells'][0]['kind'] ?? null);
        $t->same('Document', $headRow['cells'][0]['text'] ?? null);
        $t->same('head:0:0:0', $headRow['cells'][0]['anchorKey'] ?? null);
        $t->same([0, 1], $headRow['cells'][0]['spanColumns'] ?? null);
        $t->same('covered', $headRow['cells'][1]['kind'] ?? null);
        $t->same('', $headRow['cells'][1]['text'] ?? null);
        $t->same('Document', $headRow['cells'][1]['anchorText'] ?? null);
        $t->same('colspan', $headRow['cells'][1]['covering'] ?? null);
        $t->same('head:0:0:0', $headRow['cells'][1]['anchorKey'] ?? null);
        $t->same(0, $headRow['cells'][1]['anchorColumn'] ?? null);

        $coveredBodyRow = $flatGrid['rows'][3] ?? [];
        $t->same('body', $coveredBodyRow['section'] ?? null);
        $t->same(2, $coveredBodyRow['row'] ?? null);
        $t->same(3, $coveredBodyRow['globalRow'] ?? null);
        $t->same(1, $coveredBodyRow['coveredSlotCount'] ?? null);
        $t->same('covered', $coveredBodyRow['cells'][0]['kind'] ?? null);
        $t->same('rowspan', $coveredBodyRow['cells'][0]['covering'] ?? null);
        $t->same('', $coveredBodyRow['cells'][0]['text'] ?? null);
        $t->same('Posts', $coveredBodyRow['cells'][0]['anchorText'] ?? null);
        $t->same('body:1:0:0', $coveredBodyRow['cells'][0]['anchorKey'] ?? null);
        $t->same(1, $coveredBodyRow['cells'][0]['anchorRow'] ?? null);
        $t->same(2, $coveredBodyRow['cells'][0]['anchorGlobalRow'] ?? null);
        $t->same([1, 3], $coveredBodyRow['cells'][0]['sourceRowRange'] ?? null);
        $t->same('cell', $coveredBodyRow['cells'][1]['kind'] ?? null);
        $t->same('7', $coveredBodyRow['cells'][1]['text'] ?? null);
        $t->same('cell', $coveredBodyRow['cells'][2]['kind'] ?? null);
        $t->same('Import', $coveredBodyRow['cells'][2]['text'] ?? null);

        $t->same($flatGrid, $packet['flatGrid'] ?? null);
        $t->same(4, $packet['summary']['flatGridRowCount'] ?? null);
        $t->same(12, $packet['summary']['flatGridSlotCount'] ?? null);
        $t->same(2, $packet['summary']['flatGridCoveredSlotCount'] ?? null);
        $t->same(0, $packet['summary']['flatGridMissingSlotCount'] ?? null);
        json_encode($flatGrid, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports row header writer requirements for plain table handoff' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Row header review',
            'alignments' => ['left', 'right'],
            'accessibilityHeaders' => true,
            'accessibilityIdPrefix' => 'Row Header Grid',
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Items'], [new AstNode('text', ['text' => 'Items'])]),
                ]),
            ]),
            new AstNode('table_body', ['rowHeadColumns' => 1], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                ]),
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
                    new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                ]),
            ]),
        ]);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'idPrefix' => 'Row Header Grid',
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex', 'wordpress'],
        ]);

        $t->same(['markdown-row-headers-flattened'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('row-headers', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('pipe-table-row-header-semantics', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('pandoc-row-head-columns', $markdownDiagnostics[0]['source'] ?? null);
        $t->same('Row header review', $markdownDiagnostics[0]['caption'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['dataRowCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['labeledDataRowCount'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['unlabeledDataRowCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['rowHeaderCellCount'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['rowHeaderReferenceCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['maxRowHeaderCount'] ?? null);
        $t->same(['row'], $markdownDiagnostics[0]['rowHeaderScopes'] ?? null);
        $t->same(false, $markdownDiagnostics[0]['hasUnlabeledDataRows'] ?? null);
        $t->same(false, $markdownDiagnostics[0]['hasRowspanRowHeaders'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['rowspannedRowHeaderReferenceCount'] ?? null);
        $t->same(['row-header-grid-body-r1c1'], $markdownDiagnostics[0]['rows'][0]['headerIds'] ?? null);
        $t->same(['Posts'], $markdownDiagnostics[0]['rows'][0]['headerTexts'] ?? null);
        $t->same('row', $markdownDiagnostics[0]['rows'][0]['headers'][0]['scope'] ?? null);
        $t->same([0], $markdownDiagnostics[0]['rows'][0]['headers'][0]['columns'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['rows'][0]['headers'][0]['rowspan'] ?? null);
        $t->same(['row-header-grid-body-r2c1'], $markdownDiagnostics[0]['rows'][1]['headerIds'] ?? null);

        $t->same(['asciidoc-row-headers-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same('asciidoc', $asciidocDiagnostics[0]['writer'] ?? null);
        $t->same('row-header-review', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same($markdownDiagnostics[0]['rows'], $asciidocDiagnostics[0]['rows'] ?? null);

        $t->same(['latex-row-headers-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same('latex', $latexDiagnostics[0]['writer'] ?? null);
        $t->same('row-header-review-comments', $latexDiagnostics[0]['requiredFeature'] ?? null);
        $t->same($markdownDiagnostics[0]['rows'], $latexDiagnostics[0]['rows'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));

        $t->same(['markdown', 'asciidoc', 'latex', 'wordpress'], array_keys($packet['writerDowngrades']));
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->same(3, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-row-headers-flattened',
            'asciidoc-row-headers-review-required',
            'latex-row-headers-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(true, $packet['summary']['hasRowHeaders'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes table header associations for reviewer accessibility audits' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument, $buildSourceScopedHeaderDocument): void {
        $table = $buildAccessibleHeaderDocument()->children[0];
        $associations = TableGeometry::headerAssociations($table, 'Migration Grid');
        $packet = TableGeometry::reviewPacket($table, ['idPrefix' => 'Migration Grid']);

        $t->same(6, $associations['summary']['headerCellCount'] ?? null);
        $t->same(4, $associations['summary']['dataCellCount'] ?? null);
        $t->same(4, $associations['summary']['associatedDataCellCount'] ?? null);
        $t->same(12, $associations['summary']['associationCount'] ?? null);
        $t->same(['colgroup', 'col', 'rowgroup'], $associations['summary']['headerScopes'] ?? null);
        $t->same(false, $associations['summary']['hasSourceHeaderOverrides'] ?? null);

        $t->same('head:0:0:0', $associations['headerCells'][0]['key'] ?? null);
        $t->same('migration-grid-head-r1c1', $associations['headerCells'][0]['id'] ?? null);
        $t->same('colgroup', $associations['headerCells'][0]['scope'] ?? null);
        $t->same([0, 1], $associations['headerCells'][0]['columns'] ?? null);
        $t->same('Document', $associations['headerCells'][0]['text'] ?? null);
        $t->same(2, $associations['headerCells'][0]['colspan'] ?? null);

        $t->same('body:1:1:1', $associations['dataCells'][0]['key'] ?? null);
        $t->same('42', $associations['dataCells'][0]['text'] ?? null);
        $t->same([1], $associations['dataCells'][0]['columns'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $associations['dataCells'][0]['headers'] ?? null);
        $t->same('body:2:0:0', $associations['dataCells'][2]['key'] ?? null);
        $t->same(1, $associations['dataCells'][2]['column'] ?? null);
        $t->same(0, $associations['dataCells'][2]['sourceColumn'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $associations['dataCells'][2]['headers'] ?? null);

        $t->same($associations, $packet['headerAssociations'] ?? null);
        $t->same(12, $packet['summary']['headerAssociationCount'] ?? null);
        $t->same(4, $packet['summary']['associatedDataCellCount'] ?? null);

        $sourceTable = $buildSourceScopedHeaderDocument()->children[0];
        $sourceAssociations = TableGeometry::headerAssociations($sourceTable, 'Source Scope Grid');
        $t->same(true, $sourceAssociations['summary']['hasSourceHeaderOverrides'] ?? null);
        $t->same(1, $sourceAssociations['summary']['sourceHeaderOverrideCount'] ?? null);
        $t->same(2, $sourceAssociations['summary']['sourceHeaderReferencingCellCount'] ?? null);
        $t->same(3, $sourceAssociations['summary']['sourceHeaderReferenceCount'] ?? null);
        $t->same(2, $sourceAssociations['summary']['sourceHeaderResolvedReferenceCount'] ?? null);
        $t->same(1, $sourceAssociations['summary']['sourceHeaderUnresolvedReferenceCount'] ?? null);
        $t->same(true, $sourceAssociations['summary']['hasUnresolvedSourceHeaderReferences'] ?? null);
        $t->same(['legacy-count'], $sourceAssociations['summary']['unresolvedSourceHeaderReferences'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $sourceAssociations['dataCells'][0]['headers'] ?? null);
        $t->same(['legacy-count', 'source-posts'], $sourceAssociations['dataCells'][0]['sourceHeaders'] ?? null);
        $t->same([
            [
                'id' => 'legacy-count',
                'resolved' => false,
            ],
            [
                'id' => 'source-posts',
                'resolved' => true,
                'targetKey' => 'body:0:0:0',
                'targetSection' => 'body',
                'targetRow' => 0,
                'targetColumn' => 0,
                'targetRowHeadColumns' => 1,
                'targetSourceCell' => 0,
                'targetSourceColumn' => 0,
                'targetColspan' => 1,
                'targetRowspan' => 2,
                'targetSourceRow' => 0,
                'targetSourceRowEnd' => 2,
                'targetSourceRowspan' => 2,
                'targetGlobalRow' => 1,
                'targetGlobalRowEnd' => 3,
                'targetRowRole' => 'body',
                'targetScope' => 'row',
                'targetText' => 'Posts',
                'targetColumns' => [0],
                'targetSourceRows' => [0, 1],
                'targetSourceRowRange' => [0, 2],
                'targetGlobalRows' => [1, 2],
                'targetGlobalRowRange' => [1, 3],
            ],
        ], $sourceAssociations['dataCells'][0]['sourceHeaderReferences'] ?? null);
        $t->same(['source-document'], $sourceAssociations['headerCells'][2]['headers'] ?? null);
        $t->same(['source-document'], $sourceAssociations['headerCells'][2]['sourceHeaders'] ?? null);
        $t->same([
            [
                'id' => 'source-document',
                'resolved' => true,
                'targetKey' => 'head:0:0:0',
                'targetSection' => 'head',
                'targetRow' => 0,
                'targetColumn' => 0,
                'targetRowHeadColumns' => 0,
                'targetSourceCell' => 0,
                'targetSourceColumn' => 0,
                'targetColspan' => 1,
                'targetRowspan' => 1,
                'targetSourceRow' => 0,
                'targetSourceRowEnd' => 1,
                'targetSourceRowspan' => 1,
                'targetGlobalRow' => 0,
                'targetGlobalRowEnd' => 1,
                'targetRowRole' => 'head',
                'targetScope' => 'col',
                'targetText' => 'Document',
                'targetColumns' => [0],
                'targetSourceRows' => [0],
                'targetSourceRowRange' => [0, 1],
                'targetGlobalRows' => [0],
                'targetGlobalRowRange' => [0, 1],
            ],
        ], $sourceAssociations['headerCells'][2]['sourceHeaderReferences'] ?? null);
        json_encode($associations, JSON_THROW_ON_ERROR);
        json_encode($sourceAssociations, JSON_THROW_ON_ERROR);
    },
    'attaches serializable review packets to table ast nodes for reader handoff' => static function (TestRunner $t) use ($buildAccessibleHeaderDocument): void {
        $table = $buildAccessibleHeaderDocument()->children[0];
        $withPacket = TableGeometry::withReviewPacket($table, ['idPrefix' => 'Migration Grid']);
        $packet = $withPacket->attr('tableGeometry');

        $t->same('table', $withPacket->type);
        $t->same($table->children, $withPacket->children);
        $t->same('Accessible review grid', $withPacket->attr('caption'));
        $t->same(null, $table->attr('tableGeometry'));
        $t->same(true, is_array($packet));
        $packet = is_array($packet) ? $packet : [];
        $t->same('Accessible review grid', $packet['caption'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(10, $packet['summary']['cellCount'] ?? null);
        $t->same('migration-grid-head-r1c1', $packet['accessibility']['head:0:0:0']['id'] ?? null);
        $t->same(['migration-grid-head-r1c1', 'migration-grid-body-r1c2', 'migration-grid-body-r2c1'], $packet['accessibility']['body:1:1:1']['headers'] ?? null);
        $paragraph = new AstNode('paragraph', [], []);
        $t->same($paragraph, TableGeometry::withReviewPacket($paragraph));
        json_encode($packet, JSON_THROW_ON_ERROR);
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
    'rolls up nested table geometry in serializable coverage packets' => static function (TestRunner $t): void {
        $deepTable = new AstNode('table', [
            'caption' => 'Deep audit',
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
        ]);
        $middleTable = new AstNode('table', [
            'caption' => 'Nested queue audit',
            'alignments' => ['left', 'right'],
            'widths' => [0.5, 0.5],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Nested scope', 'header' => true], [new AstNode('text', ['text' => 'Nested scope'])]),
                    new AstNode('table_cell', ['text' => 'Nested state', 'header' => true], [new AstNode('text', ['text' => 'Nested state'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Media'], [new AstNode('text', ['text' => 'Media'])]),
                    new AstNode('table_cell', ['text' => 'Nested detail'], [$deepTable]),
                ]),
            ]),
        ]);
        $outerTable = new AstNode('table', [
            'caption' => 'Nested importer audit',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope', 'header' => true], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'State', 'header' => true], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Review packet'], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Review packet'])]),
                        $middleTable,
                    ]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $packet = TableGeometry::reviewPacket($outerTable, ['idPrefix' => 'Nested Import']);
        $outerCell = $packet['coverage'][2];
        $readyCell = $packet['coverage'][3];

        $t->same(2, $packet['summary']['nestedTableCount']);
        $t->same(1, $packet['summary']['nestedTableCellCount']);
        $t->same(0, $packet['sections'][0]['summary']['nestedTableCount'] ?? null);
        $t->same(0, $packet['sections'][0]['summary']['nestedTableCellCount'] ?? null);
        $t->same(false, $packet['sections'][0]['summary']['hasNestedTables'] ?? null);
        $t->same(2, $packet['sections'][1]['summary']['nestedTableCount'] ?? null);
        $t->same(1, $packet['sections'][1]['summary']['nestedTableCellCount'] ?? null);
        $t->same(true, $packet['sections'][1]['summary']['hasNestedTables'] ?? null);
        $t->same(['Deep audit', 'Nested queue audit'], $packet['sections'][1]['summary']['nestedTableCaptions'] ?? null);
        $t->same(['Deep audit'], $packet['sections'][1]['summary']['nestedTableDescendantCaptions'] ?? null);
        $t->same('Review packetNested scopeNested stateMediaInner posts42', $outerCell['text']);
        $t->same(true, isset($outerCell['nestedTables']));
        $t->same(2, count($outerCell['nestedTables'] ?? []));
        $t->same(false, array_key_exists('nestedTables', $readyCell));

        $nested = $outerCell['nestedTables'][0] ?? [];
        $deep = $outerCell['nestedTables'][1] ?? [];
        $t->same([1], $nested['path'] ?? null);
        $t->same('Nested queue audit', $nested['caption'] ?? null);
        $t->same(2, $nested['columnCount'] ?? null);
        $t->same(2, $nested['rowCount'] ?? null);
        $t->same(4, $nested['cellCount'] ?? null);
        $t->same(2, $nested['headerCellCount'] ?? null);
        $t->same(1, $nested['nestedTableCount'] ?? null);
        $t->same(true, $nested['hasNestedTables'] ?? null);
        $t->same('Deep audit', $deep['caption'] ?? null);
        $t->same(2, $deep['cellCount'] ?? null);
        $t->same(0, $deep['nestedTableCount'] ?? null);
        $t->same(false, $deep['hasNestedTables'] ?? null);
        $t->same(false, array_key_exists('node', $outerCell));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes source attributes for table sections rows and cell coverage audits' => static function (TestRunner $t) use ($buildSourceAttributedReviewPacketDocument): void {
        $table = $buildSourceAttributedReviewPacketDocument()->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('source-audit', $packet['sourceAttributes']['id'] ?? null);
        $t->same(['wp-import', 'needs-review'], $packet['sourceAttributes']['classes'] ?? null);
        $t->same(['batch' => '42', 'origin' => 'html-reader'], $packet['sourceAttributes']['attributes'] ?? null);
        $t->same('Source audit table', $packet['sourceAttributes']['htmlAttributes']['aria-label'] ?? null);
        $t->same('html-reader', $packet['sourceAttributes']['htmlAttributes']['data-origin'] ?? null);

        $t->same('source-head', $packet['sections'][0]['sourceAttributes']['id'] ?? null);
        $t->same('thead', $packet['sections'][0]['sourceAttributes']['htmlAttributes']['data-section'] ?? null);
        $t->same('source-body', $packet['sections'][1]['sourceAttributes']['id'] ?? null);
        $t->same('tbody', $packet['sections'][1]['sourceAttributes']['htmlAttributes']['data-section'] ?? null);
        $t->same('head-1', $packet['sections'][0]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null);
        $t->same('body-1', $packet['sections'][1]['rows'][0]['sourceAttributes']['htmlAttributes']['data-row'] ?? null);

        $headScope = $packet['sections'][0]['rows'][0]['slots'][0]['sourceAttributes'] ?? [];
        $stateCoverage = $packet['coverage'][1]['sourceAttributes'] ?? [];
        $postsCoverage = $packet['coverage'][2]['sourceAttributes'] ?? [];
        $readyCoverage = $packet['coverage'][3] ?? [];

        $t->same('source-scope', $headScope['id'] ?? null);
        $t->same(['review-header'], $headScope['classes'] ?? null);
        $t->same('docx', $headScope['htmlAttributes']['data-origin'] ?? null);
        $t->same(['source' => 'manual'], $stateCoverage['attributes'] ?? null);
        $t->same('manual', $stateCoverage['htmlAttributes']['data-origin'] ?? null);
        $t->same('Imported posts', $postsCoverage['htmlAttributes']['title'] ?? null);
        $t->true(!array_key_exists('sourceAttributes', $readyCoverage), 'Cells without source attributes should not add empty packet noise');
        $t->true(!array_key_exists('node', $packet['coverage'][0]), 'Source attribute packets remain serializable and do not leak AstNode references');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'renders pandoc ast table key value attributes on wordpress table sections' => static function (TestRunner $t) use ($buildAstAttributeHandoffDocument): void {
        $document = $buildAstAttributeHandoffDocument();
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('native-ast', $packet['sourceAttributes']['attributes']['data-pandoc-source'] ?? null);
        $t->same('head', $packet['sections'][0]['sourceAttributes']['attributes']['data-section-role'] ?? null);
        $t->same('body', $packet['sections'][1]['sourceAttributes']['attributes']['data-section-role'] ?? null);
        $t->same('foot', $packet['sections'][2]['sourceAttributes']['attributes']['data-section-role'] ?? null);
        $t->same('head', $packet['sections'][0]['rows'][0]['sourceAttributes']['attributes']['data-row-role'] ?? null);
        $t->same('body', $packet['sections'][1]['rows'][0]['sourceAttributes']['attributes']['data-row-role'] ?? null);
        $t->same('scope', $packet['coverage'][0]['sourceAttributes']['attributes']['data-field'] ?? null);
        $t->same('posts', $packet['coverage'][2]['sourceAttributes']['attributes']['data-field'] ?? null);

        $t->contains('<table id="native-attr-table" class="source-table needs-review"', $blocks);
        $t->contains('data-pandoc-source="native-ast"', $blocks);
        $t->contains('aria-label="Pandoc section attribute audit"', $blocks);
        $t->contains('data-html-overlap="from-html"', $blocks);
        $t->true(!str_contains($blocks, 'data-html-overlap="from-attr"'), 'Parsed HTML attributes should win over Pandoc AST attributes');
        $t->contains('<thead id="native-head" class="table-source-head" data-section-role="head" aria-label="Head rows">', $blocks);
        $t->contains('<tr id="native-head-row" class="source-row" data-row-role="head">', $blocks);
        $t->contains('<th id="native-scope" class="source-cell" data-field="scope" aria-sort="ascending" style="text-align:left">Scope</th>', $blocks);
        $t->contains('<tbody id="native-body" class="table-source-body" data-section-role="body">', $blocks);
        $t->contains('<tr id="native-body-row" data-row-role="body"><th data-field="posts" style="text-align:left">Posts</th><td data-field="ready" aria-label="Ready state" style="text-align:right">Ready</td></tr>', $blocks);
        $t->contains('<tfoot id="native-foot" data-section-role="foot"><tr data-row-role="foot"><td data-field="total" style="text-align:left">Total</td><td data-field="foot-state" style="text-align:right">Ready</td></tr></tfoot>', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe event handler attributes must not render');
        $t->true(!str_contains($blocks, 'onmouseover='), 'Unsafe section event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'flags pandoc ast table key value attributes for non-html table writers' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Native writer attribute audit',
            'alignments' => ['left', 'right'],
            'attributes' => [
                'data-pandoc-source' => 'native-ast',
                'aria-label' => 'Native writer attributes',
            ],
        ], [
            new AstNode('table_head', [
                'attributes' => [
                    'data-section-role' => 'head',
                ],
            ], [
                new AstNode('table_row', [
                    'attributes' => [
                        'data-row-role' => 'head',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Scope',
                        'attributes' => [
                            'data-field' => 'scope',
                            'aria-sort' => 'ascending',
                        ],
                    ], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', [
                        'text' => 'State',
                        'attributes' => [
                            'data-field' => 'state',
                        ],
                    ], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [
                'attributes' => [
                    'data-section-role' => 'body',
                ],
            ], [
                new AstNode('table_row', [
                    'attributes' => [
                        'data-row-role' => 'body',
                    ],
                ], [
                    new AstNode('table_cell', [
                        'text' => 'Posts',
                        'attributes' => [
                            'data-field' => 'posts',
                        ],
                    ], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', [
                        'text' => 'Ready',
                        'attributes' => [
                            'data-field' => 'ready',
                            'aria-label' => 'Ready state',
                        ],
                    ], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['pipe-table', 'asciidoctor', 'xelatex'],
        ]);

        $t->same(['markdown-table-source-attributes-require-raw-html'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(['asciidoc-table-source-attributes-require-raw-html'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same(['latex-table-source-attributes-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same($asciidocDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'adoc'));
        $t->same($latexDiagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));

        $t->same('source-attributes', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-table-attributes', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(9, $markdownDiagnostics[0]['attributeScopeCount'] ?? null);
        $t->same(12, $markdownDiagnostics[0]['attributeCount'] ?? null);
        $t->same(['table', 'section', 'row', 'cell'], $markdownDiagnostics[0]['scopes'] ?? null);
        $t->same('native-ast', $markdownDiagnostics[0]['locations'][0]['attributes']['data-pandoc-source'] ?? null);
        $t->same('head', $markdownDiagnostics[0]['locations'][1]['section'] ?? null);
        $t->same('scope', $markdownDiagnostics[0]['locations'][5]['attributes']['data-field'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['locations'][5]['row'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['locations'][5]['column'] ?? null);
        $t->same('posts', $markdownDiagnostics[0]['locations'][7]['attributes']['data-field'] ?? null);
        $t->same('ready', $markdownDiagnostics[0]['locations'][8]['attributes']['data-field'] ?? null);

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(3, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-table-source-attributes-require-raw-html',
            'asciidoc-table-source-attributes-require-raw-html',
            'latex-table-source-attributes-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes long and short table caption metadata for importer review packets' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
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
        ]);

        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('Long caption for reviewer', $packet['caption']);
        $t->same('Long caption for reviewer', $packet['captions']['long']['text'] ?? null);
        $t->same('captionInlines', $packet['captions']['long']['source'] ?? null);
        $t->same(['text', 'emph', 'link'], $packet['captions']['long']['inlineTypes'] ?? null);
        $t->same(4, $packet['captions']['long']['inlineCount'] ?? null);
        $t->same(true, $packet['captions']['long']['hasInlineFormatting'] ?? null);
        $t->same('emph', $packet['captions']['long']['inlines'][1]['type'] ?? null);
        $t->same('caption', $packet['captions']['long']['inlines'][1]['children'][0]['text'] ?? null);
        $t->same('https://example.test/review', $packet['captions']['long']['inlines'][3]['url'] ?? null);
        $t->same('Review', $packet['captions']['long']['inlines'][3]['title'] ?? null);

        $t->same('Queue short', $packet['captions']['short']['text'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same(['text', 'strong'], $packet['captions']['short']['inlineTypes'] ?? null);
        $t->same(2, $packet['captions']['short']['inlineCount'] ?? null);
        $t->same(true, $packet['captions']['short']['hasInlineFormatting'] ?? null);
        $t->same('strong', $packet['captions']['short']['inlines'][1]['type'] ?? null);
        $t->same('short', $packet['captions']['short']['inlines'][1]['children'][0]['text'] ?? null);

        $t->same(true, $packet['summary']['hasCaption'] ?? null);
        $t->same(true, $packet['summary']['hasShortCaption'] ?? null);
        $t->same(['text', 'emph', 'link'], $packet['summary']['captionInlineTypes'] ?? null);
        $t->same(['text', 'strong'], $packet['summary']['shortCaptionInlineTypes'] ?? null);
        $t->same(false, array_key_exists('node', $packet['captions']['long']['inlines'][1] ?? []));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes caption source attributes and writer diagnostics for importer review packets' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Caption source audit',
            'captionInlines' => [
                new AstNode('text', ['text' => 'Caption ']),
                new AstNode('emph', [], [new AstNode('text', ['text' => 'source'])]),
                new AstNode('text', ['text' => ' audit']),
            ],
            'captionSource' => [
                'element' => 'caption',
                'position' => 'before-table-sections',
                'childIndex' => 0,
                'captionSide' => 'bottom',
                'sourceAttributes' => [
                    'id' => 'native-caption',
                    'classes' => ['review-caption'],
                    'attributes' => [
                        'data-pandoc-source' => 'native-ast',
                        'aria-label' => 'Caption source',
                        'onclick' => 'blocked',
                    ],
                    'htmlAttributes' => [
                        'id' => 'native-caption',
                        'class' => 'review-caption',
                        'data-pandoc-source' => 'native-ast',
                        'aria-label' => 'Caption source',
                        'onclick' => 'blocked',
                    ],
                ],
            ],
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same('Caption source audit', $packet['captions']['long']['text'] ?? null);
        $t->same('captionInlines', $packet['captions']['long']['source'] ?? null);
        $t->same('caption', $packet['captions']['long']['sourceElement'] ?? null);
        $t->same('before-table-sections', $packet['captions']['long']['sourcePosition'] ?? null);
        $t->same(0, $packet['captions']['long']['sourceChildIndex'] ?? null);
        $t->same('bottom', $packet['captions']['long']['captionSide'] ?? null);
        $t->same('native-caption', $packet['captions']['long']['sourceAttributes']['id'] ?? null);
        $t->same(['review-caption'], $packet['captions']['long']['sourceAttributes']['classes'] ?? null);
        $t->same('native-ast', $packet['captions']['long']['sourceAttributes']['attributes']['data-pandoc-source'] ?? null);
        $t->same(true, $packet['summary']['hasCaptionSourceAttributes'] ?? null);
        $t->same('caption', $packet['summary']['captionSourceElement'] ?? null);
        $t->same('before-table-sections', $packet['summary']['captionSourcePosition'] ?? null);
        $t->same(0, $packet['summary']['captionSourceChildIndex'] ?? null);
        $t->same('bottom', $packet['summary']['captionSide'] ?? null);

        $t->same(['markdown-caption-source-attributes-require-raw-html'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(['asciidoc-caption-source-attributes-require-raw-html'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same(['latex-caption-source-attributes-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same('caption-source-attributes', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-caption-attributes', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(5, $markdownDiagnostics[0]['attributeCount'] ?? null);
        $t->same('native-caption', $markdownDiagnostics[0]['attributes']['id'] ?? null);
        $t->same('review-caption', $markdownDiagnostics[0]['attributes']['class'] ?? null);
        $t->same('native-ast', $markdownDiagnostics[0]['attributes']['data-pandoc-source'] ?? null);
        $t->same('bottom', $markdownDiagnostics[0]['captionSide'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['sourceChildIndex'] ?? null);

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(3, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-caption-source-attributes-require-raw-html',
            'asciidoc-caption-source-attributes-require-raw-html',
            'latex-caption-source-attributes-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));
        $t->contains('<figcaption id="native-caption" class="wp-element-caption review-caption" data-pandoc-source="native-ast" aria-label="Caption source">Caption <em>source</em> audit</figcaption>', $blocks);
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe caption event attributes must not render');
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
    },
    'serializes top caption placement for table geometry and writer handoff' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Top caption audit',
            'captionSource' => [
                'element' => 'caption',
                'position' => 'before-table-sections',
                'childIndex' => 0,
                'captionSide' => 'top',
            ],
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'latex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same('top', $packet['captions']['long']['captionSide'] ?? null);
        $t->same('before-table', $packet['captions']['long']['captionPlacement'] ?? null);
        $t->same(true, $packet['captions']['long']['captionBeforeTable'] ?? null);
        $t->same(false, $packet['captions']['long']['captionAfterTable'] ?? null);
        $t->same('before-table', $packet['summary']['captionPlacement'] ?? null);
        $t->same(true, $packet['summary']['captionBeforeTable'] ?? null);
        $t->same(false, $packet['summary']['captionAfterTable'] ?? null);
        $t->same([], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same(['asciidoc-caption-side-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same(['latex-caption-side-review-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same(null, $markdownDiagnostics[0]['reason'] ?? null);
        $t->same(null, $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(null, $markdownDiagnostics[0]['captionPlacement'] ?? null);
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same(2, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'asciidoc-caption-side-review-required',
            'latex-caption-side-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($table, 'wordpress'));
        $t->contains('<figcaption class="wp-element-caption">Top caption audit</figcaption><table>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
    },
    'reports non top bottom caption side values as geometry review requirements' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Side caption audit',
            'captionSource' => [
                'element' => 'caption',
                'position' => 'before-table-sections',
                'childIndex' => 0,
                'captionSide' => 'left',
                'sourceAttributes' => [
                    'htmlAttributes' => [
                        'style' => 'caption-side: left; color: green',
                        'data-origin' => 'html-reader',
                    ],
                ],
            ],
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Scope'], [new AstNode('text', ['text' => 'Scope'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoc');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'latex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same('left', $packet['captions']['long']['captionSide'] ?? null);
        $t->same(false, $packet['captions']['long']['captionSideSupported'] ?? null);
        $t->same(true, $packet['captions']['long']['captionSideReviewRequired'] ?? null);
        $t->same('after-table', $packet['captions']['long']['captionPlacement'] ?? null);
        $t->same('after-table', $packet['captions']['long']['captionPlacementFallback'] ?? null);
        $t->same(false, $packet['captions']['long']['captionBeforeTable'] ?? null);
        $t->same(true, $packet['captions']['long']['captionAfterTable'] ?? null);
        $t->same('left', $packet['summary']['captionSide'] ?? null);
        $t->same(false, $packet['summary']['captionSideSupported'] ?? null);
        $t->same(true, $packet['summary']['captionSideReviewRequired'] ?? null);
        $t->same('after-table', $packet['summary']['captionPlacement'] ?? null);
        $t->same('after-table', $packet['summary']['captionPlacementFallback'] ?? null);

        $t->same([
            'markdown-caption-side-review-required',
            'markdown-caption-source-attributes-require-raw-html',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics));
        $t->same([
            'asciidoc-caption-side-review-required',
            'asciidoc-caption-source-attributes-require-raw-html',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics));
        $t->same([
            'latex-caption-side-review-required',
            'latex-caption-source-attributes-review-required',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics));
        $t->same('caption-side', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-caption-side', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('left', $markdownDiagnostics[0]['captionSide'] ?? null);
        $t->same(false, $markdownDiagnostics[0]['captionSideSupported'] ?? null);
        $t->same(true, $markdownDiagnostics[0]['captionSideReviewRequired'] ?? null);
        $t->same('after-table', $markdownDiagnostics[0]['captionPlacement'] ?? null);
        $t->same('after-table', $markdownDiagnostics[0]['captionPlacementFallback'] ?? null);

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same($latexDiagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(6, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-caption-side-review-required',
            'markdown-caption-source-attributes-require-raw-html',
            'asciidoc-caption-side-review-required',
            'asciidoc-caption-source-attributes-require-raw-html',
            'latex-caption-side-review-required',
            'latex-caption-source-attributes-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->contains('<table>', $blocks);
        $t->contains('<figcaption class="wp-element-caption" style="caption-side: left; color: green" data-origin="html-reader">Side caption audit</figcaption>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
    },
    'serializes block-level table caption provenance for importer review packets' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Fallback block caption text',
            'captionInlines' => [
                new AstNode('text', ['text' => 'Fallback inline caption']),
            ],
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

        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same('Block caption for reviewer' . "\n" . 'Queue note', $packet['captions']['long']['text'] ?? null);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('Fallback block caption text', $packet['captions']['long']['rawText'] ?? null);
        $t->same(2, $packet['captions']['long']['blockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $packet['captions']['long']['blockTypes'] ?? null);
        $t->same(true, $packet['captions']['long']['hasBlockContent'] ?? null);
        $t->same('paragraph', $packet['captions']['long']['blocks'][0]['type'] ?? null);
        $t->same('strong', $packet['captions']['long']['blocks'][0]['inlines'][1]['type'] ?? null);
        $t->same('caption', $packet['captions']['long']['blocks'][0]['inlines'][1]['children'][0]['text'] ?? null);
        $t->same('bullet_list', $packet['captions']['long']['blocks'][1]['type'] ?? null);
        $t->same('list_item', $packet['captions']['long']['blocks'][1]['children'][0]['type'] ?? null);
        $t->same(true, $packet['summary']['hasCaption'] ?? null);
        $t->same(true, $packet['summary']['hasCaptionBlocks'] ?? null);
        $t->same(2, $packet['summary']['captionBlockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $packet['summary']['captionBlockTypes'] ?? null);
        $t->contains('<figcaption class="wp-element-caption"><p>Block <strong>caption</strong> for reviewer</p><ul><li>Queue note</li></ul></figcaption>', $blocks);
        $t->same(false, array_key_exists('node', $packet['captions']['long']['blocks'][0] ?? []));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes block-level table cell content for importer review packets and writer handoff' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
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

        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc'],
        ]);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'pipe-table');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $reviewCell = $packet['coverage'][2];
        $content = $reviewCell['content'] ?? [];
        $sectionCellContent = $packet['sections'][1]['rows'][0]['slots'][0]['content'] ?? [];

        $t->same('Review sourceImage alt textResolve captions', $reviewCell['text'] ?? null);
        $t->same(true, $content['hasBlockContent'] ?? null);
        $t->same(false, $content['hasMixedInlineAndBlockContent'] ?? null);
        $t->same(2, $content['blockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $content['blockTypes'] ?? null);
        $t->same('Review source' . "\n" . 'Image alt textResolve captions', $content['text'] ?? null);
        $t->same('paragraph', $content['blocks'][0]['type'] ?? null);
        $t->same(['text', 'emph'], array_map(static fn (array $inline): string => (string) ($inline['type'] ?? ''), $content['blocks'][0]['inlines'] ?? []));
        $t->same('source', $content['blocks'][0]['inlines'][1]['children'][0]['text'] ?? null);
        $t->same('bullet_list', $content['blocks'][1]['type'] ?? null);
        $t->same('list_item', $content['blocks'][1]['children'][0]['type'] ?? null);
        $t->same('Image alt text', $content['blocks'][1]['children'][0]['children'][0]['text'] ?? null);
        $t->same('strong', $content['blocks'][1]['children'][1]['children'][0]['inlines'][0]['type'] ?? null);
        $t->same($content, $sectionCellContent);

        $t->same(true, $packet['summary']['hasBlockContentCells'] ?? null);
        $t->same(1, $packet['summary']['blockContentCellCount'] ?? null);
        $t->same(1, $packet['summary']['multiBlockCellCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $packet['summary']['cellBlockTypes'] ?? null);
        $t->same(1, $packet['sections'][1]['summary']['blockContentCellCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $packet['sections'][1]['summary']['cellBlockTypes'] ?? null);

        $t->same(['markdown-cell-blocks-flattened'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $markdownDiagnostics));
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('body', $markdownDiagnostics[0]['section'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['row'] ?? null);
        $t->same(0, $markdownDiagnostics[0]['column'] ?? null);
        $t->same('block-content', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('multiline-or-grid-table-cell', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['blockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $markdownDiagnostics[0]['blockTypes'] ?? null);
        $t->same(['asciidoc-block-cell-required'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $asciidocDiagnostics));
        $t->same('asciidoc-block-cell', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same(2, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['markdown-cell-blocks-flattened', 'asciidoc-block-cell-required'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);

        $t->contains('<td style="text-align:left"><p>Review <em>source</em></p><ul><li>Image alt text</li><li><strong>Resolve captions</strong></li></ul></td><td style="text-align:right">Ready</td>', $blocks);
        $t->same(false, array_key_exists('node', $content['blocks'][0] ?? []));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports latex table writer requirements for spans block cells and nested tables' => static function (TestRunner $t): void {
        $nestedTable = new AstNode('table', [
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
        $table = new AstNode('table', [
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
                        $nestedTable,
                    ]),
                ]),
            ]),
        ]);

        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['latex'],
        ]);

        $t->same([
            'latex-multicolumn-required',
            'latex-multirow-required',
            'latex-multicolumn-required',
            'latex-cell-block-required',
            'latex-nested-table-required',
        ], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same(['latex'], array_values(array_unique(array_map(static fn (array $diagnostic): string => (string) $diagnostic['writer'], $diagnostics))));
        $t->same('head', $diagnostics[0]['section'] ?? null);
        $t->same(0, $diagnostics[0]['row'] ?? null);
        $t->same([0, 1], $diagnostics[0]['columns'] ?? null);
        $t->same('colspan', $diagnostics[0]['reason'] ?? null);
        $t->same('multicolumn', $diagnostics[0]['requiredFeature'] ?? null);
        $t->same([['row' => 0, 'column' => 1, 'covering' => 'colspan']], $diagnostics[0]['requiredSlots'] ?? null);
        $t->same('body', $diagnostics[1]['section'] ?? null);
        $t->same(0, $diagnostics[1]['row'] ?? null);
        $t->same(0, $diagnostics[1]['column'] ?? null);
        $t->same('rowspan', $diagnostics[1]['reason'] ?? null);
        $t->same('multirow', $diagnostics[1]['requiredFeature'] ?? null);
        $t->same([['row' => 1, 'column' => 0, 'covering' => 'rowspan']], $diagnostics[1]['requiredSlots'] ?? null);
        $t->same([1, 2], $diagnostics[2]['columns'] ?? null);
        $t->same('multicolumn', $diagnostics[2]['requiredFeature'] ?? null);
        $t->same('block-content', $diagnostics[3]['reason'] ?? null);
        $t->same('parbox-or-minipage-cell', $diagnostics[3]['requiredFeature'] ?? null);
        $t->same(2, $diagnostics[3]['blockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $diagnostics[3]['blockTypes'] ?? null);
        $t->same(1, $diagnostics[4]['nestedTableCount'] ?? null);
        $t->same('nested-tabular-minipage', $diagnostics[4]['requiredFeature'] ?? null);
        $t->same(['Nested LaTeX audit'], $diagnostics[4]['nestedTableCaptions'] ?? null);
        $t->same([1], $diagnostics[4]['nestedTables'][0]['path'] ?? null);
        $t->same(2, $diagnostics[4]['nestedTables'][0]['cellCount'] ?? null);
        $t->same(false, array_key_exists('node', $diagnostics[4]['nestedTables'][0] ?? []));

        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));
        $t->same($diagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(5, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'latex-multicolumn-required',
            'latex-multirow-required',
            'latex-cell-block-required',
            'latex-nested-table-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['latex'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(true, $packet['summary']['hasSpans'] ?? null);
        $t->same(true, $packet['summary']['hasBlockContentCells'] ?? null);
        $t->same(1, $packet['summary']['nestedTableCount'] ?? null);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'treats latex table foot sections as supported longtable handoff' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
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

        $diagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'lualatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['latex'],
        ]);

        $t->same([], $diagnostics);
        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($table, 'tex'));
        $t->same($diagnostics, $packet['writerDowngrades']['latex'] ?? null);
        $t->same(0, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same([], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(true, $packet['summary']['hasTableFoot'] ?? null);
        $t->same(1, $packet['summary']['tableFootRowCount'] ?? null);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports markdown and asciidoc table foot section writer handoff diagnostics' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Footer section audit',
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
        $plainTable = new AstNode('table', [
            'caption' => 'No footer section audit',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(['markdown-table-foot-flattened'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $markdownDiagnostics));
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('table-foot', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('body-row-flattening', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('Footer section audit', $markdownDiagnostics[0]['caption'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['columnCount'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['sectionCount'] ?? null);
        $t->same(3, $markdownDiagnostics[0]['rowCount'] ?? null);
        $t->same(1, $markdownDiagnostics[0]['footRowCount'] ?? null);
        $t->same(['head', 'body', 'foot'], array_map(static fn (array $section): string => (string) ($section['section'] ?? ''), $markdownDiagnostics[0]['sections'] ?? []));
        $t->same([1, 1, 1], array_map(static fn (array $section): int => (int) ($section['rowCount'] ?? 0), $markdownDiagnostics[0]['sections'] ?? []));
        $t->same([
            ['section' => 'head', 'rowRange' => [0, 1], 'rowCount' => 1, 'rowRole' => 'head'],
            ['section' => 'body', 'rowRange' => [1, 2], 'rowCount' => 1, 'rowRole' => 'body'],
            ['section' => 'foot', 'rowRange' => [2, 3], 'rowCount' => 1, 'rowRole' => 'foot'],
        ], $markdownDiagnostics[0]['sectionRanges'] ?? null);
        $t->same([
            ['section' => 'foot', 'rowRange' => [2, 3], 'rowCount' => 1, 'rowRole' => 'foot'],
        ], $markdownDiagnostics[0]['footSectionRanges'] ?? null);

        $t->same(['asciidoc-table-foot-required'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $asciidocDiagnostics));
        $t->same('asciidoc', $asciidocDiagnostics[0]['writer'] ?? null);
        $t->same('table-foot', $asciidocDiagnostics[0]['reason'] ?? null);
        $t->same('table-footer', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(1, $asciidocDiagnostics[0]['bodyCount'] ?? null);
        $t->same(1, $asciidocDiagnostics[0]['footRowCount'] ?? null);
        $t->same([], TableGeometry::writerDowngradeDiagnostics($plainTable, 'markdown'));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($plainTable, 'asciidoc'));

        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same($asciidocDiagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same(2, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['markdown-table-foot-flattened', 'asciidoc-table-foot-required'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->contains('<tfoot><tr><td style="text-align:left">Total</td><td style="text-align:right">Ready</td></tr></tfoot>', $blocks);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($asciidocDiagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports asciidoc nested table passthrough requirements for writer handoff' => static function (TestRunner $t): void {
        $innerTable = new AstNode('table', [
            'caption' => 'Nested source audit',
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
        ]);
        $outerTable = new AstNode('table', [
            'caption' => 'AsciiDoc nested table audit',
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
                        $innerTable,
                    ]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);
        $plainTable = new AstNode('table', [
            'caption' => 'Plain AsciiDoc table audit',
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $diagnostics = TableGeometry::writerDowngradeDiagnostics($outerTable, 'asciidoctor');
        $packet = TableGeometry::reviewPacket($outerTable, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc'],
        ]);

        $t->same(['asciidoc-nested-table-raw-html-required'], array_map(static fn (array $diagnostic): string => $diagnostic['code'], $diagnostics));
        $t->same('asciidoc', $diagnostics[0]['writer'] ?? null);
        $t->same('body', $diagnostics[0]['section'] ?? null);
        $t->same(0, $diagnostics[0]['row'] ?? null);
        $t->same(0, $diagnostics[0]['column'] ?? null);
        $t->same([0], $diagnostics[0]['columns'] ?? null);
        $t->same('nested-table', $diagnostics[0]['reason'] ?? null);
        $t->same('raw-html-table-passthrough', $diagnostics[0]['requiredFeature'] ?? null);
        $t->same(1, $diagnostics[0]['nestedTableCount'] ?? null);
        $t->same(['Nested source audit'], $diagnostics[0]['nestedTableCaptions'] ?? null);
        $t->same([], $diagnostics[0]['nestedTableDiagnosticCodes'] ?? null);
        $t->same([1], $diagnostics[0]['nestedTables'][0]['path'] ?? null);
        $t->same('Nested source audit', $diagnostics[0]['nestedTables'][0]['caption'] ?? null);
        $t->same(2, $diagnostics[0]['nestedTables'][0]['cellCount'] ?? null);
        $t->same(false, array_key_exists('node', $diagnostics[0]['nestedTables'][0] ?? []));

        $t->same($diagnostics, TableGeometry::writerDowngradeDiagnostics($outerTable, 'adoc'));
        $t->same([], TableGeometry::writerDowngradeDiagnostics($plainTable, 'asciidoc'));
        $t->same($diagnostics, $packet['writerDowngrades']['asciidoc'] ?? null);
        $t->same(1, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same(['asciidoc-nested-table-raw-html-required'], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same(1, $packet['summary']['nestedTableCount'] ?? null);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports empty table review and writer handoff diagnostics' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Empty import table audit',
        ], [
            new AstNode('table_head'),
            new AstNode('table_body', [
                'htmlAttributes' => [
                    'id' => 'empty-body',
                ],
            ]),
        ]);

        $diagnostics = TableGeometry::diagnostics($table);
        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex', 'wordpress'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(0, TableGeometry::columnCount($table));
        $t->same([], TableGeometry::cellCoverage($table));
        $t->same(['table-has-no-cells'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $diagnostics));
        $t->same('pandoc-table-geometry', $diagnostics[0]['source'] ?? null);
        $t->same(true, $diagnostics[0]['hasCaption'] ?? null);
        $t->same(0, $diagnostics[0]['columnCount'] ?? null);
        $t->same(0, $diagnostics[0]['declaredColumnCount'] ?? null);
        $t->same(2, $diagnostics[0]['sectionCount'] ?? null);
        $t->same(0, $diagnostics[0]['rowCount'] ?? null);
        $t->same(1, $diagnostics[0]['bodyCount'] ?? null);
        $t->same(['head', 'body'], array_map(static fn (array $section): string => (string) ($section['section'] ?? ''), $diagnostics[0]['sections'] ?? []));
        $t->same([0, 0], array_map(static fn (array $section): int => (int) ($section['rowCount'] ?? -1), $diagnostics[0]['sections'] ?? []));

        $t->same(['markdown-empty-table-omitted'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $markdownDiagnostics));
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('empty-table', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('raw-html-empty-table-or-placeholder', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('pandoc-empty-table', $markdownDiagnostics[0]['source'] ?? null);
        $t->same(2, $markdownDiagnostics[0]['sectionCount'] ?? null);
        $t->same(['asciidoc-empty-table-review-required'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $asciidocDiagnostics));
        $t->same('empty-table-placeholder', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same(['latex-empty-table-review-required'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['code'], $latexDiagnostics));
        $t->same('empty-tabular-placeholder', $latexDiagnostics[0]['requiredFeature'] ?? null);

        $t->same(['table-has-no-cells'], $packet['summary']['diagnosticCodes'] ?? null);
        $t->same(true, $packet['summary']['hasEmptyTable'] ?? null);
        $t->same(2, $packet['summary']['emptyTableSectionCount'] ?? null);
        $t->same(0, $packet['summary']['emptyTableRowCount'] ?? null);
        $t->same(0, $packet['summary']['cellCount'] ?? null);
        $t->same(0, $packet['summary']['rowCount'] ?? null);
        $t->same(3, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-empty-table-omitted',
            'asciidoc-empty-table-review-required',
            'latex-empty-table-review-required',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->same([], $packet['writerDowngrades']['wordpress'] ?? null);
        $t->contains('<table><tbody id="empty-body"></tbody></table><figcaption class="wp-element-caption">Empty import table audit</figcaption>', $blocks);
        json_encode($diagnostics, JSON_THROW_ON_ERROR);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'reports caption writer requirements for short and block captions' => static function (TestRunner $t): void {
        $table = new AstNode('table', [
            'caption' => 'Fallback caption text',
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
            'shortCaption' => 'Queue short',
            'shortCaptionInlines' => [
                new AstNode('text', ['text' => 'Queue ']),
                new AstNode('strong', [], [new AstNode('text', ['text' => 'short'])]),
            ],
            'alignments' => ['left', 'right'],
        ], [
            new AstNode('table_head', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Metric', 'header' => true], [new AstNode('text', ['text' => 'Metric'])]),
                    new AstNode('table_cell', ['text' => 'State', 'header' => true], [new AstNode('text', ['text' => 'State'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', ['text' => 'Caption handoff'], [new AstNode('text', ['text' => 'Caption handoff'])]),
                    new AstNode('table_cell', ['text' => 'Ready'], [new AstNode('text', ['text' => 'Ready'])]),
                ]),
            ]),
        ]);

        $markdownDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'markdown');
        $asciidocDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'asciidoctor');
        $latexDiagnostics = TableGeometry::writerDowngradeDiagnostics($table, 'xelatex');
        $packet = TableGeometry::reviewPacket($table, [
            'accessibility' => false,
            'writers' => ['markdown', 'asciidoc', 'latex'],
        ]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same(
            ['markdown-short-caption-prefix-required', 'markdown-caption-blocks-flattened'],
            array_map(static fn (array $diagnostic): string => $diagnostic['code'], $markdownDiagnostics)
        );
        $t->same('markdown', $markdownDiagnostics[0]['writer'] ?? null);
        $t->same('short-caption', $markdownDiagnostics[0]['reason'] ?? null);
        $t->same('pandoc-short-caption-prefix', $markdownDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('Queue short', $markdownDiagnostics[0]['shortCaption'] ?? null);
        $t->same('shortCaptionInlines', $markdownDiagnostics[0]['shortCaptionSource'] ?? null);
        $t->same(['text', 'strong'], $markdownDiagnostics[0]['shortCaptionInlineTypes'] ?? null);
        $t->same(true, $markdownDiagnostics[0]['hasShortCaptionFormatting'] ?? null);
        $t->same('Block caption for reviewer' . "\n" . 'Queue note', $markdownDiagnostics[1]['captionText'] ?? null);
        $t->same('captionBlocks', $markdownDiagnostics[1]['captionSource'] ?? null);
        $t->same(2, $markdownDiagnostics[1]['blockCount'] ?? null);
        $t->same(['paragraph', 'bullet_list'], $markdownDiagnostics[1]['blockTypes'] ?? null);
        $t->same('Fallback caption text', $markdownDiagnostics[1]['rawCaption'] ?? null);

        $t->same(
            ['asciidoc-short-caption-review-required', 'asciidoc-caption-blocks-flattened'],
            array_map(static fn (array $diagnostic): string => $diagnostic['code'], $asciidocDiagnostics)
        );
        $t->same('table-short-title-review', $asciidocDiagnostics[0]['requiredFeature'] ?? null);
        $t->same('plain-caption-text', $asciidocDiagnostics[1]['requiredFeature'] ?? null);

        $t->same(
            ['latex-caption-blocks-flattened'],
            array_map(static fn (array $diagnostic): string => $diagnostic['code'], $latexDiagnostics)
        );
        $t->same('caption-text', $latexDiagnostics[0]['requiredFeature'] ?? null);

        $t->same(5, $packet['summary']['writerDowngradeCount'] ?? null);
        $t->same([
            'markdown-short-caption-prefix-required',
            'markdown-caption-blocks-flattened',
            'asciidoc-short-caption-review-required',
            'asciidoc-caption-blocks-flattened',
            'latex-caption-blocks-flattened',
        ], $packet['summary']['writerDowngradeCodes'] ?? null);
        $t->same(['asciidoc', 'latex', 'markdown'], $packet['summary']['writerDowngradeWriters'] ?? null);
        $t->same($markdownDiagnostics, $packet['writerDowngrades']['markdown'] ?? null);
        $t->contains('data-pandoc-short-caption="Queue short"', $blocks);
        $t->contains('<strong>caption</strong>', $blocks);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($markdownDiagnostics, JSON_THROW_ON_ERROR);
    },
];
