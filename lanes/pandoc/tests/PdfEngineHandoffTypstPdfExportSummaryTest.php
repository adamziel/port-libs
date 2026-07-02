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
    'records mapped typst pdf export summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstPdfExportSummaryCases'] ?? null);
        $t->same(28, $manifest['typstPdfExportSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstPdfExportSummaryCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['breakdown']['typstPdfExportSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstPdfExportSummaryCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['inventory']['typstPdfExportSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstPdfExportSummaryCases'] ?? null);
        $t->same(28, $manifest['inventory']['typstPdfExportSummaryAssertions'] ?? null);
    },

    'summarizes typst pdf export controls for reviewer handoff without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/pdf-export-summary.pdf',
            'source' => '= Typst PDF Export Summary',
            'engineOptions' => [
                '--pages=1-3',
                '--pages=0,5-2,',
                '--ppi=0',
                '--ppi=300',
                '--pdf-standard=1.7,',
                '--pdf-standard=2.0',
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
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }

        $t->same('review', $summary['reviewStatus']);
        $t->same(5, $summary['pdfExportControlCount']);
        $t->same(['pages', 'ppi', 'pdf-standard', 'tags', 'pretty'], $summary['pdfExportControlNames']);
        $t->same([
            'pages' => 1,
            'ppi' => 1,
            'pdf-standard' => 1,
            'tags' => 1,
            'pretty' => 1,
        ], $summary['pdfExportControlCounts']);
        $t->same(2, $summary['pdfExportFlagCount']);
        $t->same(6, $summary['pdfExportHistoryEntryCount']);
        $t->same(3, $summary['pdfExportOverrideCount']);
        $t->same(8, $summary['pdfExportIssueCount']);
        $t->same([
            'pages' => 4,
            'ppi' => 2,
            'pdf-standard' => 2,
            'tags' => 0,
            'pretty' => 0,
        ], $summary['pdfExportIssueCounts']);
        $t->contains('typst-boundary-summary-pdf-export-controls:5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-export-issues:8', implode(',', $plan['diagnostics']));
        $t->same('review', $plan['typstBoundaryMatrix']['reviewStatus']);
        $t->same(['boundary-overrides', 'output-format', 'pdf-export-controls'], array_column($plan['typstBoundaryMatrix']['cases'], 'case'));
        $t->same(5, $cases['pdf-export-controls']['observed']);
        $t->same(3, $cases['pdf-export-controls']['details']['overrideCount']);
        $t->contains('pdf-export-controls:pdf-standard-boundary-overridden', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
