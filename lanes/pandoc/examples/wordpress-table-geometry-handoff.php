<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

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

    echo "table geometry handoff self-test ok\n";
    return;
}

echo $blocks . "\n";
