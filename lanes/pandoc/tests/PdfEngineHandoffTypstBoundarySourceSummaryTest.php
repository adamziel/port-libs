<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst boundary source summary packet keeps option and environment provenance visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Source Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary source summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundarySourceSummaryCases'] ?? null);
        $t->same(34, $manifest['typstBoundarySourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundarySourceSummaryCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['breakdown']['typstBoundarySourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundarySourceSummaryCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['inventory']['typstBoundarySourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundarySourceSummaryCases'] ?? null);
        $t->same(34, $manifest['inventory']['typstBoundarySourceSummaryAssertions'] ?? null);
    },

    'summarizes typst boundary control sources without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/source-summary.typ',
            'outputPath' => 'build/source-summary.pdf',
            'source' => '= Typst Boundary Source Summary',
            'engineOptions' => [
                '--root=workspace',
                '--font-path=fonts' . PATH_SEPARATOR . '/srv/fonts',
                '--creation-timestamp=1700000100',
                '--features=html,packages',
                '--deps=build/source-summary.d',
                '--diagnostic-format=json',
                '--pages=1-2',
                '--pdf-standard=a-2u',
                '--ignore-system-fonts',
            ],
            'engineEnvironment' => [
                'TYPST_ROOT' => 'shadow-root',
                'TYPST_FONT_PATHS' => 'env-fonts' . PATH_SEPARATOR . 'https://fonts.example.invalid',
                'TYPST_PACKAGE_CACHE_PATH' => 'cache/typst',
                'SOURCE_DATE_EPOCH' => '1700000000',
                'TYPST_FEATURES' => 'legacy-html,a11y',
                'TYPST_IGNORE_EMBEDDED_FONTS' => '1',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst boundary source summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/source-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/source-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySourceSummary'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(18, $summary['controlCount']);
        $t->same(3, $summary['sourceCount']);
        $t->same(['engine-option' => 10, 'environment' => 5, 'environment-shadow' => 3, 'implicit' => 0], $summary['sourceCounts']);
        $t->same(['engine-option' => 4, 'environment' => 4, 'environment-shadow' => 1, 'implicit' => 0], $summary['pathSourceCounts']);
        $t->same([
            'engine-option' => [
                'creation-timestamp',
                'dependency-output',
                'diagnostic-format',
                'features',
                'font-path',
                'pdf-pages',
                'pdf-standard',
                'root',
                'system-fonts',
            ],
            'environment' => ['embedded-fonts', 'font-path', 'package-cache'],
            'environment-shadow' => ['creation-timestamp', 'features', 'root'],
            'implicit' => [],
        ], $summary['controlsBySource']);
        $t->same(6, $summary['environmentVariableCount']);
        $t->same([
            'SOURCE_DATE_EPOCH',
            'TYPST_FEATURES',
            'TYPST_FONT_PATHS',
            'TYPST_IGNORE_EMBEDDED_FONTS',
            'TYPST_PACKAGE_CACHE_PATH',
            'TYPST_ROOT',
        ], $summary['environmentVariables']);
        $t->same(3, $summary['shadowedEnvironmentVariableCount']);
        $t->same(['SOURCE_DATE_EPOCH', 'TYPST_FEATURES', 'TYPST_ROOT'], $summary['shadowedEnvironmentVariables']);
        $t->same(4, $summary['issueCount']);
        $t->contains('font-path-external-boundary', implode(',', $summary['issues']));
        $t->contains('root-environment-shadowed', implode(',', $summary['issues']));
        $t->contains('features-environment-shadowed', implode(',', $summary['issues']));
        $t->contains('creation-timestamp-environment-shadowed', implode(',', $summary['issues']));
        $t->contains('typst-boundary-source-summary:review', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-source:engine-option:10', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-source:environment:5', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-source:environment-shadow:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-source-shadowed-environment:3', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySourceSummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySourceSummary']);
        $t->same($summary, $sequence['finalTypstBoundarySourceSummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
    },
];
