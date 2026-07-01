<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst error source policy packet keeps failed engine origins reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Error Source Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst error source policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstErrorSourcePolicyCases'] ?? null);
        $t->same(52, $manifest['typstErrorSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstErrorSourcePolicyCases'] ?? null);
        $t->same(52, $manifest['benchmarkDenominator']['breakdown']['typstErrorSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstErrorSourcePolicyCases'] ?? null);
        $t->same(52, $manifest['benchmarkDenominator']['inventory']['typstErrorSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstErrorSourcePolicyCases'] ?? null);
        $t->same(52, $manifest['inventory']['typstErrorSourcePolicyAssertions'] ?? null);
    },

    'rolls typst error sources into policy buckets without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'build/error-source-policy.pdf',
            'source' => '= Typst Error Source Policy',
            'engineOptions' => ['--root=workspace', '--diagnostic-format=json'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst error source policy packet\n%%EOF\n";
        $stderr = implode("\n", [
            json_encode([
                'severity' => 'error',
                'message' => 'local error',
                'span' => [
                    'path' => 'workspace/main.typ',
                    'start' => ['line' => 3, 'column' => 1],
                ],
                'hint' => 'local hint',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'outside root error',
                'span' => [
                    'path' => 'shared/theme.typ',
                    'start' => ['line' => 4, 'column' => 2],
                ],
                'hint' => 'move under workspace',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'preview package error',
                'span' => [
                    'path' => '@preview/cetz:0.3.2/lib.typ',
                    'start' => ['line' => 5, 'column' => 3],
                ],
                'hint' => 'vendor preview package',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'typst package error',
                'span' => [
                    'path' => '@typst/tablex:0.0.8/table.typ',
                    'start' => ['line' => 6, 'column' => 4],
                ],
                'hint' => 'vendor typst package',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'remote error',
                'span' => [
                    'path' => 'https://cdn.example.invalid/typst/theme.typ',
                    'start' => ['lineNumber' => 7, 'columnNumber' => 5],
                ],
                'hint' => 'vendor remote source',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'missing source error',
                'help' => 'emit source spans for review',
            ], JSON_THROW_ON_ERROR),
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/error-source-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/error-source-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $policy = $result['typstErrorSourcePolicy'];
        $matrixCases = [];
        foreach ($result['typstBoundaryMatrix']['cases'] as $case) {
            $matrixCases[$case['case']] = $case;
        }
        $errorDetails = $matrixCases['error-provenance']['details'];

        $t->same(false, $result['ok']);
        $t->same('engine-exit-1', $result['reason']);
        $t->same('review', $policy['reviewStatus']);
        $t->same(6, $policy['errorCount']);
        $t->same(5, $policy['locatedSourceCount']);
        $t->same(5, $policy['sourceFileCount']);
        $t->same([
            'shared/theme.typ',
            'theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'workspace/main.typ',
        ], $policy['sourceFiles']);
        $t->same(['external' => 1, 'local' => 2, 'typst-package' => 2, 'unknown' => 1], $policy['sourceKindCounts']);
        $t->same([
            'external-resource' => 1,
            'local-file' => 2,
            'preview-registry' => 1,
            'typst-registry' => 1,
            'unknown-source' => 1,
        ], $policy['sourceClassCounts']);
        $t->same([
            'external-source' => 3,
            'inside-root' => 1,
            'outside-root' => 1,
            'unknown-source' => 1,
        ], $policy['boundaryStatusCounts']);
        $t->same(2, $policy['packageReferenceCount']);
        $t->same(['@preview/cetz:0.3.2/lib.typ', '@typst/tablex:0.0.8/table.typ'], $policy['packageReferences']);
        $t->same(5, $policy['sourceIssueCount']);
        $t->same(3, $policy['distinctSourceIssueCount']);
        $t->same(['error-source-external', 'error-source-missing', 'error-source-outside-root'], $policy['issues']);
        $t->same($policy, $result['artifactProvenanceReview']['typstErrorSourcePolicy']);
        $t->same($policy, $sequence['finalTypstErrorSourcePolicy']);
        $t->same([
            'workspace/main.typ',
            'shared/theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'theme.typ',
            null,
        ], array_column($result['typstErrorProvenance'], 'sourceFile'));
        $t->same('error-provenance', $matrixCases['error-provenance']['case']);
        $t->same('review', $matrixCases['error-provenance']['reviewStatus']);
        $t->same(6, $matrixCases['error-provenance']['observed']);
        $t->same($policy['sourceKindCounts'], $errorDetails['sourceKindCounts']);
        $t->same($policy['sourceClassCounts'], $errorDetails['sourceClassCounts']);
        $t->same($policy['packageReferences'], $errorDetails['packageReferences']);
        $t->same(3, $errorDetails['sourceIssueCount']);
        $t->same(5, $errorDetails['totalSourceIssueCount']);
        $t->same(5, $errorDetails['locatedSourceCount']);
        $t->same(3, $errorDetails['externalSourceCount']);
        $t->same(1, $errorDetails['insideRootCount']);
        $t->same(1, $errorDetails['outsideRootCount']);
        $t->same(1, $errorDetails['unknownSourceCount']);
        $t->same(6, $errorDetails['hintCount']);
        $t->same($result['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($result['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
        $t->same($result['typstErrorProvenance'], $result['artifactProvenanceReview']['typstErrorProvenance']);
        $t->same($result['typstErrorProvenance'], $sequence['finalTypstErrorProvenance']);
        $t->contains('typst-error-source-policy:review', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-kind:typst-package:2', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-class:preview-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-class:typst-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-packages:2', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-issues:5', implode(',', $result['diagnostics']));
        $t->contains('typst-error-source-issues:5', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->same('failed', $result['artifactProvenanceReview']['reviewStatus']);
    },
];
