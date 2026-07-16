<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst matrix summary packet keeps boundary provenance visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Matrix Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary matrix summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundaryMatrixSummaryCases'] ?? null);
        $t->same(25, $manifest['typstBoundaryMatrixSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundaryMatrixSummaryCases'] ?? null);
        $t->same(25, $manifest['benchmarkDenominator']['breakdown']['typstBoundaryMatrixSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundaryMatrixSummaryCases'] ?? null);
        $t->same(25, $manifest['benchmarkDenominator']['inventory']['typstBoundaryMatrixSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundaryMatrixSummaryCases'] ?? null);
        $t->same(25, $manifest['inventory']['typstBoundaryMatrixSummaryAssertions'] ?? null);
    },

    'summarizes typst boundary matrix cases for reviewer handoff without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => '-',
            'outputPath' => 'build/matrix-summary.pdf',
            'source' => '= Typst Matrix Summary',
            'engineOptions' => [
                '--font-path=/srv/fonts',
                '--pages=1-3,2',
                '--ppi=0',
                '--ppi=300',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst boundary matrix summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/matrix-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/matrix-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $matrix = $plan['typstBoundaryMatrix'];

        $t->same('review', $matrix['reviewStatus']);
        $t->same(5, $matrix['caseCount']);
        $t->same(4, $matrix['reviewCaseCount']);
        $t->same(6, $matrix['issueCount']);
        $t->same(5, $matrix['caseObservedTotal']);
        $t->same([
            'source-input',
            'font-path-policy',
            'boundary-overrides',
            'output-format',
            'pdf-export-controls',
        ], $matrix['caseNames']);
        $t->same(['ok' => 1, 'review' => 4], $matrix['caseReviewStatusCounts']);
        $t->same([
            'ok' => ['output-format'],
            'review' => [
                'boundary-overrides',
                'font-path-policy',
                'pdf-export-controls',
                'source-input',
            ],
        ], $matrix['caseNamesByReviewStatus']);
        $t->same([
            'boundary-overrides' => 1,
            'font-path-policy' => 1,
            'output-format' => 0,
            'pdf-export-controls' => 2,
            'source-input' => 1,
        ], $matrix['caseObservedCounts']);
        $t->same([
            'boundary-overrides' => 1,
            'font-path-policy' => 1,
            'output-format' => 0,
            'pdf-export-controls' => 3,
            'source-input' => 1,
        ], $matrix['caseIssueCounts']);
        $t->contains('source-input:source-stdin-boundary', implode(',', $matrix['issues']));
        $t->contains('font-path-policy:font-path-external-boundary', implode(',', $matrix['issues']));
        $t->contains('pdf-export-controls:pages-overlapping-selection-boundary', implode(',', $matrix['issues']));
        $t->same(true, $result['ok']);
        $t->same($matrix, $result['typstBoundaryMatrix']);
        $t->same($matrix, $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($matrix, $sequence['finalTypstBoundaryMatrix']);
    },
];
