<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF export summary packet keeps boundary controls visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst PDF Export Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'summarizes typst pdf export boundary controls without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/pdf-export-summary.pdf',
            'source' => '= Typst PDF Export Summary Packet',
            'engineOptions' => [
                '--pages=1-3',
                '--pages=0,5-2,',
                '--ppi=0',
                '--ppi=300',
                '--pdf-standard=1.7,',
                '--pdf-standard=a-2b,ua-1',
                '--no-pdf-tags',
                '--pretty',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst PDF export summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/pdf-export-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/pdf-export-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(5, $summary['pdfExportControlCount']);
        $t->same(true, $summary['pdfExportPageSelectionPresent']);
        $t->same('0,5-2,', $summary['pdfExportPageSelectionValue']);
        $t->same(3, $summary['pdfExportPageSelectionSegmentCount']);
        $t->same(2, $summary['pdfExportPageSelectionInvalidSegmentCount']);
        $t->same(2, $summary['pdfExportPageSelectionHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPageSelectionCount']);
        $t->same(true, $summary['pdfExportPpiPresent']);
        $t->same('300', $summary['pdfExportPpiValue']);
        $t->same(300, $summary['pdfExportPpi']);
        $t->same(2, $summary['pdfExportPpiHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPpiCount']);
        $t->same(2, $summary['pdfExportPdfStandardCount']);
        $t->same(0, $summary['pdfExportPdfVersionCount']);
        $t->same(1, $summary['pdfExportPdfaCount']);
        $t->same(1, $summary['pdfExportPdfuaCount']);
        $t->same(['a-2b', 'ua-1'], $summary['pdfExportPdfStandards']);
        $t->same(2, $summary['pdfExportPdfStandardHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPdfStandardCount']);
        $t->same(true, $summary['pdfExportTagsDisabled']);
        $t->same(1, $summary['pdfExportTagsFlagCount']);
        $t->same(true, $summary['pdfExportPrettyEnabled']);
        $t->same(1, $summary['pdfExportPrettyFlagCount']);
        $t->same(3, $summary['pdfExportOverrideCount']);
        $t->same(3, $summary['pdfExportInvalidPageSelectionCount'] + $summary['pdfExportInvalidPpiCount'] + $summary['pdfExportInvalidPdfStandardCount']);
        $t->same(10, $summary['pdfExportIssueCount']);
        $t->contains('typst-boundary-summary-pdf-export-controls:5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-pdf-export-controls:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-export-issues:10', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
