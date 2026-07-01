<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst warning source policy packet keeps warning origins reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Warning Source Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst warning source policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstWarningSourcePolicyCases'] ?? null);
        $t->same(37, $manifest['typstWarningSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstWarningSourcePolicyCases'] ?? null);
        $t->same(37, $manifest['benchmarkDenominator']['breakdown']['typstWarningSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstWarningSourcePolicyCases'] ?? null);
        $t->same(37, $manifest['benchmarkDenominator']['inventory']['typstWarningSourcePolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstWarningSourcePolicyCases'] ?? null);
        $t->same(37, $manifest['inventory']['typstWarningSourcePolicyAssertions'] ?? null);
    },

    'rolls typst warning sources into policy buckets without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'build/warning-source-policy.pdf',
            'source' => '= Typst Warning Source Policy',
            'engineOptions' => ['--root=workspace', '--diagnostic-format=json'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst warning source policy packet\n%%EOF\n";
        $stderr = implode("\n", [
            json_encode([
                'severity' => 'warning',
                'message' => 'local warning',
                'span' => [
                    'path' => 'workspace/main.typ',
                    'start' => ['line' => 3, 'column' => 1],
                ],
                'hint' => 'local hint',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'warning',
                'message' => 'outside root warning',
                'span' => [
                    'path' => 'shared/theme.typ',
                    'start' => ['line' => 4, 'column' => 2],
                ],
                'hint' => 'move under workspace',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'warning',
                'message' => 'preview package warning',
                'span' => [
                    'path' => '@preview/cetz:0.3.2/lib.typ',
                    'start' => ['line' => 5, 'column' => 3],
                ],
                'hint' => 'vendor preview package',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'warning',
                'message' => 'typst package warning',
                'span' => [
                    'path' => '@typst/tablex:0.0.8/table.typ',
                    'start' => ['line' => 6, 'column' => 4],
                ],
                'hint' => 'vendor typst package',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'warning',
                'message' => 'remote warning',
                'span' => [
                    'path' => 'https://cdn.example.invalid/typst/theme.typ',
                    'start' => ['lineNumber' => 7, 'columnNumber' => 5],
                ],
                'hint' => 'vendor remote source',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'warning',
                'message' => 'missing source warning',
                'help' => 'emit source spans for review',
            ], JSON_THROW_ON_ERROR),
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'stderr' => $stderr,
            'files' => [
                'build/warning-source-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'stderr' => $stderr,
            'files' => [
                'build/warning-source-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $policy = $result['typstWarningSourcePolicy'];
        $matrixCases = [];
        foreach ($result['typstBoundaryMatrix']['cases'] as $case) {
            $matrixCases[$case['case']] = $case;
        }
        $warningDetails = $matrixCases['warning-provenance']['details'];

        $t->same(true, $result['ok']);
        $t->same('review', $policy['reviewStatus']);
        $t->same(6, $policy['warningCount']);
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
        $t->same(['warning-source-external', 'warning-source-missing', 'warning-source-outside-root'], $policy['issues']);
        $t->same($policy, $result['artifactProvenanceReview']['typstWarningSourcePolicy']);
        $t->same($policy, $sequence['finalTypstWarningSourcePolicy']);
        $t->same([
            'workspace/main.typ',
            'shared/theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'theme.typ',
            null,
        ], array_column($result['typstWarningProvenance'], 'sourceFile'));
        $t->same($policy['sourceKindCounts'], $warningDetails['sourceKindCounts']);
        $t->same($policy['sourceClassCounts'], $warningDetails['sourceClassCounts']);
        $t->same($policy['packageReferences'], $warningDetails['packageReferences']);
        $t->same(3, $warningDetails['sourceIssueCount']);
        $t->same(5, $warningDetails['totalSourceIssueCount']);
        $t->contains('typst-warning-source-policy:review', implode(',', $result['diagnostics']));
        $t->contains('typst-warning-source-kind:typst-package:2', implode(',', $result['diagnostics']));
        $t->contains('typst-warning-source-class:preview-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-warning-source-class:typst-registry:1', implode(',', $result['diagnostics']));
        $t->contains('typst-warning-source-packages:2', implode(',', $result['diagnostics']));
        $t->contains('typst-warning-source-issues:5', implode(',', $result['diagnostics']));
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
    },
];
