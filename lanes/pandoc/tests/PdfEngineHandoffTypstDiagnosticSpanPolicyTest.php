<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic span policy keeps source ranges reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Span Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst diagnostic span policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstDiagnosticSpanPolicyCases'] ?? null);
        $t->same(48, $manifest['typstDiagnosticSpanPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstDiagnosticSpanPolicyCases'] ?? null);
        $t->same(48, $manifest['benchmarkDenominator']['breakdown']['typstDiagnosticSpanPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstDiagnosticSpanPolicyCases'] ?? null);
        $t->same(48, $manifest['benchmarkDenominator']['inventory']['typstDiagnosticSpanPolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstDiagnosticSpanPolicyCases'] ?? null);
        $t->same(48, $manifest['inventory']['typstDiagnosticSpanPolicyAssertions'] ?? null);
    },

    'rolls typst diagnostic source spans into policy buckets without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'build/diagnostic-span-policy.pdf',
            'source' => '= Typst Diagnostic Span Policy',
            'engineOptions' => ['--root=workspace', '--diagnostic-format=json'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst diagnostic span policy packet\n%%EOF\n";
        $stderr = implode("\n", [
            json_encode([
                'severity' => 'warning',
                'message' => 'ranged warning',
                'span' => [
                    'path' => 'workspace/chapter.typ',
                    'start' => ['line' => 2, 'column' => 3],
                    'end' => ['line' => 4, 'column' => 5],
                ],
                'hints' => ['check local import'],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'ranged package error',
                'location' => [
                    'file' => '@preview/cetz:0.3.2/lib.typ',
                    'range' => [
                        'start' => ['line' => 6, 'column' => 7],
                        'end' => ['line' => 8, 'column' => 9],
                    ],
                ],
                'notes' => [
                    ['message' => 'package hint'],
                ],
            ], JSON_THROW_ON_ERROR),
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/diagnostic-span-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/diagnostic-span-policy.pdf' => $pdfBytes,
            ],
        ]]);

        $warningPolicy = $result['typstWarningSourcePolicy'];
        $errorPolicy = $result['typstErrorSourcePolicy'];
        $matrixCases = [];
        foreach ($result['typstBoundaryMatrix']['cases'] as $case) {
            $matrixCases[$case['case']] = $case;
        }
        $warningDetails = $matrixCases['warning-provenance']['details'];
        $errorDetails = $matrixCases['error-provenance']['details'];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(false, $result['ok']);
        $t->same('engine-exit-1', $result['reason']);
        $t->same('ok', $warningPolicy['reviewStatus']);
        $t->same(1, $warningPolicy['warningCount']);
        $t->same(1, $warningPolicy['lineColumnSourceCount']);
        $t->same(1, $warningPolicy['rangedSourceCount']);
        $t->same(1, $warningPolicy['hintCount']);
        $t->same(1, $warningPolicy['distinctSourceLocationCount']);
        $t->same(['workspace/chapter.typ:2:3'], $warningPolicy['sourceLocations']);
        $t->same(['workspace/chapter.typ'], $warningPolicy['rangedSourceFiles']);
        $t->same(1, $warningDetails['lineColumnSourceCount']);
        $t->same(1, $warningDetails['rangedSourceCount']);
        $t->same(['workspace/chapter.typ:2:3'], $warningDetails['sourceLocations']);
        $t->same(['workspace/chapter.typ'], $warningDetails['rangedSourceFiles']);
        $t->same($warningPolicy, $result['artifactProvenanceReview']['typstWarningSourcePolicy']);
        $t->same($warningPolicy, $sequence['finalTypstWarningSourcePolicy']);

        $t->same('review', $errorPolicy['reviewStatus']);
        $t->same(1, $errorPolicy['errorCount']);
        $t->same(1, $errorPolicy['lineColumnSourceCount']);
        $t->same(1, $errorPolicy['rangedSourceCount']);
        $t->same(1, $errorPolicy['hintCount']);
        $t->same(1, $errorPolicy['distinctSourceLocationCount']);
        $t->same(['typst-package:@preview/cetz:0.3.2/lib.typ:6:7'], $errorPolicy['sourceLocations']);
        $t->same(['typst-package:@preview/cetz:0.3.2/lib.typ'], $errorPolicy['rangedSourceFiles']);
        $t->same(['typst-package' => 1], $errorPolicy['sourceKindCounts']);
        $t->same(['preview-registry' => 1], $errorPolicy['sourceClassCounts']);
        $t->same(['@preview/cetz:0.3.2/lib.typ'], $errorPolicy['packageReferences']);
        $t->same(1, $errorPolicy['sourceIssueCount']);
        $t->same(1, $errorDetails['lineColumnSourceCount']);
        $t->same(1, $errorDetails['rangedSourceCount']);
        $t->same(['typst-package:@preview/cetz:0.3.2/lib.typ:6:7'], $errorDetails['sourceLocations']);
        $t->same(['typst-package:@preview/cetz:0.3.2/lib.typ'], $errorDetails['rangedSourceFiles']);
        $t->same($errorPolicy, $result['artifactProvenanceReview']['typstErrorSourcePolicy']);
        $t->same($errorPolicy, $sequence['finalTypstErrorSourcePolicy']);

        $t->contains('typst-warning-source-ranges:1', $diagnostics);
        $t->contains('typst-warning-source-locations:1', $diagnostics);
        $t->contains('typst-error-source-ranges:1', $diagnostics);
        $t->contains('typst-error-source-locations:1', $diagnostics);
        $t->contains('typst-error-source-hints:1', $diagnostics);
        $t->same('failed', $result['artifactProvenanceReview']['reviewStatus']);
    },
];
