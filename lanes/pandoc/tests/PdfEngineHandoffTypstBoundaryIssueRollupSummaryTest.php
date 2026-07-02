<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst boundary issue rollup packet keeps repeated review codes visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Issue Rollup Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary issue rollup summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundaryIssueRollupSummaryCases'] ?? null);
        $t->same(32, $manifest['typstBoundaryIssueRollupSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundaryIssueRollupSummaryCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['breakdown']['typstBoundaryIssueRollupSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundaryIssueRollupSummaryCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['inventory']['typstBoundaryIssueRollupSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundaryIssueRollupSummaryCases'] ?? null);
        $t->same(32, $manifest['inventory']['typstBoundaryIssueRollupSummaryAssertions'] ?? null);
    },

    'summarizes typst boundary issue occurrence rollups without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/issue-rollup-summary.pdf',
            'source' => '= Typst Boundary Issue Rollup',
            'engineOptions' => [
                '--font-path=/srv/fonts',
                '--font-path=https://fonts.example.invalid',
                '--pages=1-3,2',
                '--pages=bad',
                '--ppi=0',
                '--ppi=300',
                '--cert',
                '--cert=https://ca.example.invalid/root.pem',
            ],
            'engineEnvironment' => [
                'TYPST_CERT' => 'https://env-ca.example.invalid/root.pem',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst boundary issue rollup summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/issue-rollup-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/issue-rollup-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(8, $summary['issueCount']);
        $t->same(21, $summary['issueOccurrenceCount']);
        $t->same([
            'certificate-empty' => 2,
            'certificate-environment-shadowed' => 2,
            'certificate-external-boundary' => 3,
            'font-path-external-boundary' => 3,
            'pages-boundary-overridden' => 2,
            'pages-invalid-segment-boundary:bad' => 5,
            'ppi-boundary-overridden' => 2,
            'ppi-nonpositive-boundary' => 2,
        ], $summary['issueCounts']);
        $t->same(5, $summary['pathEntryCount']);
        $t->same(5, $summary['unsafePathEntryCount']);
        $t->same(3, $summary['certificateBoundaryEntryCount']);
        $t->same(4, $summary['pdfExportIssueCount']);
        $t->same(2, $summary['pdfExportOverrideCount']);
        $t->same([
            'boundary-overrides' => 2,
            'certificate-paths' => 3,
            'environment-shadows' => 2,
            'font-path-policy' => 1,
            'output-format' => 0,
            'pdf-export-controls' => 4,
        ], $plan['typstBoundaryMatrix']['caseIssueCounts']);
        $t->contains('typst-boundary-summary-issues:8', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issue-occurrences:21', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issue:certificate-empty:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issue:font-path-external-boundary:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issue:pages-invalid-segment-boundary:bad:5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issue:ppi-nonpositive-boundary:2', implode(',', $plan['diagnostics']));
        $t->contains('certificate-paths:certificate-empty', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
