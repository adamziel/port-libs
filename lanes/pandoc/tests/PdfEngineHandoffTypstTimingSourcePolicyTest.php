<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst timing source policy packet keeps sidecar origins reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Timing Source Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst timing source policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstTimingSourcePolicyCases'] ?? null);
        $t->same(51, $manifest['typstTimingSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstTimingSourcePolicyCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['breakdown']['typstTimingSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstTimingSourcePolicyCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['inventory']['typstTimingSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstTimingSourcePolicyCases'] ?? null);
        $t->same(51, $manifest['inventory']['typstTimingSourcePolicyAssertions'] ?? null);
    },

    'rolls typst timing sources into package-aware policy buckets without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'build/timing-package-source.pdf',
            'source' => '= Typst Timing Source Policy',
            'engineOptions' => ['--root=workspace', '--timings=build/timing-package-source.json'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst timing source policy packet\n%%EOF\n";
        $timingsBytes = json_encode([
            'traceEvents' => [
                ['name' => 'parse', 'args' => ['file' => 'workspace/main.typ', 'line' => 1]],
                ['name' => 'theme', 'args' => ['path' => 'shared/theme.typ', 'lineNumber' => 3]],
                ['name' => 'preview package', 'args' => ['source' => '@preview/cetz:0.3.2/lib.typ', 'line' => 5]],
                ['name' => 'typst package', 'args' => ['input' => '@typst/tablex:0.0.8/table.typ', 'line' => 7]],
                ['name' => 'remote', 'args' => ['file' => 'https://cdn.example.invalid/typst/theme.typ']],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/timing-package-source.pdf' => $pdfBytes,
                'build/timing-package-source.json' => $timingsBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/timing-package-source.pdf' => $pdfBytes,
                'build/timing-package-source.json' => $timingsBytes,
            ],
        ]]);
        $policy = $result['typstTimingSourcePolicy'];
        $matrixCases = [];
        foreach ($result['typstBoundaryMatrix']['cases'] as $case) {
            $matrixCases[$case['case']] = $case;
        }
        $timingDetails = $matrixCases['timing-provenance']['details'];

        $t->same(true, $result['ok']);
        $t->same('review', $policy['reviewStatus']);
        $t->same(5, $policy['sourceFileCount']);
        $t->same(5, $policy['locatedSourceCount']);
        $t->same(5, $policy['distinctSourceFileCount']);
        $t->same([
            'shared/theme.typ',
            'theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'workspace/main.typ',
        ], $policy['distinctSourceFiles']);
        $t->same(['external' => 1, 'local' => 2, 'typst-package' => 2], $policy['sourceKindCounts']);
        $t->same([
            'external-resource' => 1,
            'local-file' => 2,
            'preview-registry' => 1,
            'typst-registry' => 1,
        ], $policy['sourceClassCounts']);
        $t->same([
            'external-source' => 3,
            'inside-root' => 1,
            'outside-root' => 1,
        ], $policy['boundaryStatusCounts']);
        $t->same(2, $policy['packageReferenceCount']);
        $t->same(['@preview/cetz:0.3.2/lib.typ', '@typst/tablex:0.0.8/table.typ'], $policy['packageReferences']);
        $t->same(1, $policy['insideRootCount']);
        $t->same(1, $policy['outsideRootCount']);
        $t->same(3, $policy['externalSourceCount']);
        $t->same(4, $policy['sourceIssueCount']);
        $t->same(2, $policy['distinctSourceIssueCount']);
        $t->same(['timing-source-external', 'timing-source-outside-root'], $policy['issues']);
        $t->same([
            'workspace/main.typ',
            'shared/theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'theme.typ',
        ], array_column($policy['sourceFiles'], 'sourceFile'));
        $t->same($policy, $result['artifactProvenanceReview']['typstTimingSourcePolicy']);
        $t->same($policy, $sequence['finalTypstTimingSourcePolicy']);
        $t->same('timing-provenance', $matrixCases['timing-provenance']['case']);
        $t->same('review', $matrixCases['timing-provenance']['reviewStatus']);
        $t->same(5, $matrixCases['timing-provenance']['observed']);
        $t->same($policy['sourceKindCounts'], $timingDetails['sourceKindCounts']);
        $t->same($policy['sourceClassCounts'], $timingDetails['sourceClassCounts']);
        $t->same($policy['boundaryStatusCounts'], $timingDetails['boundaryStatusCounts']);
        $t->same($policy['packageReferences'], $timingDetails['packageReferences']);
        $t->same(2, $timingDetails['packageReferenceCount']);
        $t->same(4, $timingDetails['sourceIssueCount']);
        $t->same($policy['distinctSourceFiles'], $timingDetails['sourceFiles']);
        $t->same($result['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($result['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-timing-source-policy:review', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->contains('typst-timing-source-policy:review', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-files:5', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-kind:typst-package:2', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-class:preview-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-class:typst-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-packages:2', implode(',', $result['diagnostics']));
        $t->contains('typst-timing-source-issues:4', implode(',', $result['diagnostics']));
        $t->contains('timing-provenance:timing-source-external', implode(',', $result['typstBoundaryMatrix']['issues']));
        $t->contains('timing-provenance:timing-source-outside-root', implode(',', $result['typstBoundaryMatrix']['issues']));
    },
];
