<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF export summary packet keeps page, ppi, standard, tag, and pretty controls reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst PDF Export Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst pdf export summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstPdfExportSummaryProvenanceCases'] ?? null);
        $t->same(68, $manifest['typstPdfExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstPdfExportSummaryProvenanceCases'] ?? null);
        $t->same(68, $manifest['benchmarkDenominator']['breakdown']['typstPdfExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstPdfExportSummaryProvenanceCases'] ?? null);
        $t->same(68, $manifest['benchmarkDenominator']['inventory']['typstPdfExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstPdfExportSummaryProvenanceCases'] ?? null);
        $t->same(68, $manifest['inventory']['typstPdfExportSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst pdf export controls without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/pdf-export-summary.pdf',
            'source' => '= Typst PDF Export Summary',
            'engineOptions' => [
                '--pages=1-3',
                '--pages=1-3,2,8-,0',
                '--ppi=0',
                '--ppi=144.5',
                '--pdf-standard=1.7,',
                '--pdf-standard=a-2b,ua-1',
                '--no-pdf-tags',
                '--pretty',
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
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $details = $cases['pdf-export-controls']['details'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(5, $summary['pdfExportControlCount']);
        $t->same(true, $summary['pdfExportPageSelectionPresent']);
        $t->same('1-3,2,8-,0', $summary['pdfExportPageSelectionValue']);
        $t->same(4, $summary['pdfExportPageSelectionSegmentCount']);
        $t->same(1, $summary['pdfExportPageSelectionPageSegmentCount']);
        $t->same(1, $summary['pdfExportPageSelectionRangeSegmentCount']);
        $t->same(1, $summary['pdfExportPageSelectionRangeFromSegmentCount']);
        $t->same(0, $summary['pdfExportPageSelectionRangeToSegmentCount']);
        $t->same(1, $summary['pdfExportPageSelectionInvalidSegmentCount']);
        $t->same(1, $summary['pdfExportPageSelectionOverlapCount']);
        $t->same(2, $summary['pdfExportPageSelectionHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPageSelectionCount']);
        $t->same(3, $summary['pdfExportPageSelectionIssueCount']);
        $t->same(true, $summary['pdfExportPpiPresent']);
        $t->same('144.5', $summary['pdfExportPpiValue']);
        $t->same(144.5, $summary['pdfExportPpi']);
        $t->same(2, $summary['pdfExportPpiHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPpiCount']);
        $t->same(2, $summary['pdfExportPpiIssueCount']);
        $t->same(2, $summary['pdfExportPdfStandardCount']);
        $t->same(0, $summary['pdfExportPdfVersionCount']);
        $t->same(1, $summary['pdfExportPdfaCount']);
        $t->same(1, $summary['pdfExportPdfuaCount']);
        $t->same(['a-2b', 'ua-1'], $summary['pdfExportPdfStandards']);
        $t->same(2, $summary['pdfExportPdfStandardHistoryCount']);
        $t->same(1, $summary['pdfExportInvalidPdfStandardCount']);
        $t->same(3, $summary['pdfExportPdfStandardIssueCount']);
        $t->same(true, $summary['pdfExportTagsDisabled']);
        $t->same(1, $summary['pdfExportTagsFlagCount']);
        $t->same(true, $summary['pdfExportPrettyEnabled']);
        $t->same(2, $summary['pdfExportPrettyFlagCount']);
        $t->same(3, $summary['pdfExportOverrideCount']);
        $t->same(9, $summary['pdfExportIssueCount']);
        $t->contains('pages-overlapping-selection-boundary', implode(',', $summary['pdfExportIssues']));
        $t->contains('pdf-standard-empty-token-boundary', implode(',', $summary['pdfExportIssues']));
        $t->contains('pdf-tags-disabled-for-pdfua', implode(',', $summary['pdfExportIssues']));
        $t->contains('typst-boundary-summary-pdf-export:5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-pages:4', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-page-overlaps:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-ppi:144.5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-standards:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-export-issues:9', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same(5, $cases['pdf-export-controls']['observed']);
        $t->same($summary['pdfExportPageSelectionValue'], $details['pageSelectionValue']);
        $t->same($summary['pdfExportPageSelectionSegmentCount'], $details['pageSelectionSegmentCount']);
        $t->same($summary['pdfExportPpi'], $details['ppi']);
        $t->same($summary['pdfExportPdfStandards'], $details['pdfStandards']);
        $t->same($summary['pdfExportTagsDisabled'], $details['tagsDisabled']);
        $t->same($summary['pdfExportPrettyFlagCount'], $details['prettyFlagCount']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-pdf-standard-policy:review', implode(',', $plan['diagnostics']));
        $t->contains('typst-pdf-tags:disabled', implode(',', $plan['diagnostics']));
        $t->contains('pdf-export-controls:pdf-standard-pdfa-pdfua-conflict-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
