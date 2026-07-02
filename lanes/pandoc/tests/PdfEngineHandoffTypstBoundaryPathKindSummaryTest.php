<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst boundary path kind summary packet keeps path buckets reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Path Kind Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary path kind summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundaryPathKindSummaryCases'] ?? null);
        $t->same(31, $manifest['typstBoundaryPathKindSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundaryPathKindSummaryCases'] ?? null);
        $t->same(31, $manifest['benchmarkDenominator']['breakdown']['typstBoundaryPathKindSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundaryPathKindSummaryCases'] ?? null);
        $t->same(31, $manifest['benchmarkDenominator']['inventory']['typstBoundaryPathKindSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundaryPathKindSummaryCases'] ?? null);
        $t->same(31, $manifest['inventory']['typstBoundaryPathKindSummaryAssertions'] ?? null);
    },

    'summarizes typst boundary path kind buckets without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/path-kind-summary.typ',
            'outputPath' => 'build/path-kind-summary.pdf',
            'source' => '= Typst Boundary Path Kind Summary',
            'engineOptions' => [
                '--root=.',
                '--font-path=fonts',
                '--font-path=https://cdn.example.invalid/fonts',
                '--cert=certs/local.pem',
                '--package-path=/srv/typst/packages',
                '--package-cache=',
                '--deps=-',
                '--timings=build/path-kind-summary-timings.json',
            ],
            'engineEnvironment' => [
                'TYPST_ROOT' => '/srv/shadow-root',
                'TYPST_FONT_PATHS' => 'env-fonts',
                'TYPST_CERT' => 'https://cert.example.invalid/root.pem',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst boundary path kind summary packet\n%%EOF\n";
        $timingsBytes = '{"traceEvents":[{"name":"compile","args":{"file":"workspace/path-kind-summary.typ"}}]}';

        $result = $handoff->fakeRun($plan, [
            'files' => [
                '-' => "build/path-kind-summary.pdf: workspace/path-kind-summary.typ\n",
                'build/path-kind-summary.pdf' => $pdfBytes,
                'build/path-kind-summary-timings.json' => $timingsBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                '-' => "build/path-kind-summary.pdf: workspace/path-kind-summary.typ\n",
                'build/path-kind-summary.pdf' => $pdfBytes,
                'build/path-kind-summary-timings.json' => $timingsBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];

        $t->same(11, $summary['pathEntryCount']);
        $t->same(5, $summary['safePathEntryCount']);
        $t->same(6, $summary['unsafePathEntryCount']);
        $t->same([
            'relative' => 4,
            'workspace' => 1,
            'absolute' => 2,
            'uri' => 2,
            'stdout' => 1,
            'invalid' => 1,
        ], $summary['pathKindCounts']);
        $t->same([
            'relative' => 4,
            'workspace' => 1,
        ], $summary['safePathKindCounts']);
        $t->same([
            'absolute' => 2,
            'uri' => 2,
            'stdout' => 1,
            'invalid' => 1,
        ], $summary['unsafePathKindCounts']);
        $t->same(['engine-option' => 8, 'environment' => 3], $summary['pathSourceCounts']);
        $t->same(3, $summary['environmentPathVariableCount']);
        $t->same(['TYPST_CERT', 'TYPST_FONT_PATHS', 'TYPST_ROOT'], $summary['environmentPathVariables']);
        $t->same(['absolute', 'uri', 'stdout', 'invalid'], $summary['unsafePathKinds']);
        $t->contains('typst-boundary-summary-path-kind:relative:4', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-kind:workspace:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-kind:absolute:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-kind:uri:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-kind:stdout:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-kind:invalid:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-source:engine-option:8', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-path-source:environment:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-unsafe-paths:6', implode(',', $plan['diagnostics']));
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($summary['pathKindCounts'], $result['artifactProvenanceReview']['typstBoundarySummary']['pathKindCounts']);
    },
];
