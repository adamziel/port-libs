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
    ...$readerHandoffTables,
]);

$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
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
    if (($cellCoverage[0]['section'] ?? null) !== 'head' || ($cellCoverage[0]['columns'] ?? null) !== [0, 1]) {
        throw new RuntimeException('Table geometry self-test missing head cell visual coverage report');
    }
    if (($cellCoverage[0]['columnAlignments'] ?? null) !== ['left', 'right'] || ($cellCoverage[0]['widths'] ?? null) !== [0.25, 0.25]) {
        throw new RuntimeException('Table geometry self-test missing covered column specs');
    }
    if (($cellCoverage[2]['section'] ?? null) !== 'body' || ($cellCoverage[2]['rowspan'] ?? null) !== 2 || ($cellCoverage[2]['columns'] ?? null) !== [0]) {
        throw new RuntimeException('Table geometry self-test missing rowspanned body cell coverage report');
    }
    if (($cellCoverage[5]['sourceCell'] ?? null) !== 0 || ($cellCoverage[5]['sourceColumn'] ?? null) !== 0 || ($cellCoverage[5]['column'] ?? null) !== 1) {
        throw new RuntimeException('Table geometry self-test missing source-to-visual coverage coordinates');
    }

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
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">Nested table packet review</figcaption>')) {
        throw new RuntimeException('Table geometry self-test missing nested table packet WordPress output');
    }

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

    echo "table geometry handoff self-test ok\n";
    return;
}

echo $blocks . "\n";
