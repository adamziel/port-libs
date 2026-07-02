<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst boundary path categories keep handoff review provenance visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Path Category Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary summary path category case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundarySummaryPathCategoryCases'] ?? null);
        $t->same(18, $manifest['typstBoundarySummaryPathCategoryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundarySummaryPathCategoryCases'] ?? null);
        $t->same(18, $manifest['benchmarkDenominator']['breakdown']['typstBoundarySummaryPathCategoryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundarySummaryPathCategoryCases'] ?? null);
        $t->same(18, $manifest['benchmarkDenominator']['inventory']['typstBoundarySummaryPathCategoryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundarySummaryPathCategoryCases'] ?? null);
        $t->same(18, $manifest['inventory']['typstBoundarySummaryPathCategoryAssertions'] ?? null);
    },

    'summarizes typst boundary path categories for reviewer handoff without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/path-category.typ',
            'outputPath' => 'build/path-category.pdf',
            'source' => '= Typst Path Category Summary',
            'engineOptions' => [
                '--root=workspace',
                '--font-path=fonts',
                '--font-path=/srv/fonts',
                '--cert=certs/internal.pem',
                '--cert=https://ca.example.invalid/root.pem',
                '--package-path=vendor/typst',
                '--package-cache=https://cache.example.invalid/typst',
                '--deps=build/path-category.d',
                '--timings=build/path-category-timings.json',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst path category summary packet\n%%EOF\n";
        $depfile = "build/path-category.pdf: workspace/path-category.typ\n";
        $timingsBytes = '{"traceEvents":[{"name":"export","args":{"file":"workspace/path-category.typ","line":1}}]}';
        $summary = $plan['typstBoundarySummary'];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/path-category.d' => $depfile,
                'build/path-category-timings.json' => $timingsBytes,
                'build/path-category.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/path-category.d' => $depfile,
                'build/path-category-timings.json' => $timingsBytes,
                'build/path-category.pdf' => $pdfBytes,
            ],
        ]]);

        $t->same([
            'certificate' => 2,
            'dependencyOutput' => 1,
            'fontPath' => 2,
            'packageCache' => 1,
            'packagePath' => 1,
            'root' => 1,
            'timingsOutput' => 1,
        ], $summary['pathEntryCountsByCategory']);
        $t->same([
            'certificate' => [
                'relative' => 1,
                'uri' => 1,
            ],
            'dependencyOutput' => [
                'relative' => 1,
            ],
            'fontPath' => [
                'absolute' => 1,
                'relative' => 1,
            ],
            'packageCache' => [
                'uri' => 1,
            ],
            'packagePath' => [
                'relative' => 1,
            ],
            'root' => [
                'relative' => 1,
            ],
            'timingsOutput' => [
                'relative' => 1,
            ],
        ], $summary['pathEntryKindCountsByCategory']);
        $t->same([
            'certificate' => 1,
            'fontPath' => 1,
            'packageCache' => 1,
        ], $summary['unsafePathEntryCountsByCategory']);
        $t->contains('typst-boundary-summary-categories:7', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-unsafe-categories:3', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
