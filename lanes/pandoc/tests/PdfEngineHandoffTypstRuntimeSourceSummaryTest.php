<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst runtime source summary keeps package provenance reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Runtime Source Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst runtime source summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstRuntimeSourceSummaryCases'] ?? null);
        $t->same(51, $manifest['typstRuntimeSourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstRuntimeSourceSummaryCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['breakdown']['typstRuntimeSourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstRuntimeSourceSummaryCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['inventory']['typstRuntimeSourceSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstRuntimeSourceSummaryCases'] ?? null);
        $t->same(51, $manifest['inventory']['typstRuntimeSourceSummaryAssertions'] ?? null);
    },

    'summarizes typst runtime source packages across timing warning and error channels without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'build/runtime-source-summary.pdf',
            'source' => '= Typst Runtime Source Summary',
            'engineOptions' => ['--root=workspace', '--timings=build/runtime-source-summary.json', '--diagnostic-format=json'],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst runtime source summary packet\n%%EOF\n";
        $timingsBytes = json_encode([
            'traceEvents' => [
                ['name' => 'parse', 'args' => ['file' => 'workspace/main.typ', 'line' => 1]],
                ['name' => 'package import', 'args' => ['source' => '@preview/cetz:0.3.2/lib.typ', 'line' => 8]],
            ],
        ], JSON_THROW_ON_ERROR);
        $stderr = implode("\n", [
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
                'message' => 'outside root warning',
                'span' => [
                    'path' => 'shared/theme.typ',
                    'start' => ['line' => 9, 'column' => 2],
                ],
                'hint' => 'move under workspace',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'severity' => 'error',
                'message' => 'typst package error',
                'span' => [
                    'path' => '@typst/tablex:0.0.8/table.typ',
                    'start' => ['line' => 11, 'column' => 4],
                ],
                'hint' => 'vendor typst package',
            ], JSON_THROW_ON_ERROR),
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/runtime-source-summary.pdf' => $pdfBytes,
                'build/runtime-source-summary.json' => $timingsBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'exitCode' => 1,
            'stderr' => $stderr,
            'files' => [
                'build/runtime-source-summary.pdf' => $pdfBytes,
                'build/runtime-source-summary.json' => $timingsBytes,
            ],
        ]]);
        $summary = $result['typstRuntimeSourceSummary'];
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(false, $result['ok']);
        $t->same('engine-exit-1', $result['reason']);
        $t->same('review', $summary['reviewStatus']);
        $t->same(3, $summary['channelCount']);
        $t->same(['timing', 'warning', 'error'], $summary['channels']);
        $t->same(5, $summary['observationCount']);
        $t->same(5, $summary['locatedSourceCount']);
        $t->same(4, $summary['distinctSourceFileCount']);
        $t->same([
            'shared/theme.typ',
            'typst-package:@preview/cetz:0.3.2/lib.typ',
            'typst-package:@typst/tablex:0.0.8/table.typ',
            'workspace/main.typ',
        ], $summary['distinctSourceFiles']);
        $t->same(['local' => 2, 'typst-package' => 3], $summary['sourceKindCounts']);
        $t->same([
            'local-file' => 2,
            'preview-registry' => 2,
            'typst-registry' => 1,
        ], $summary['sourceClassCounts']);
        $t->same([
            'external-source' => 3,
            'inside-root' => 1,
            'outside-root' => 1,
        ], $summary['boundaryStatusCounts']);
        $t->same(2, $summary['packageReferenceCount']);
        $t->same(['@preview/cetz:0.3.2/lib.typ', '@typst/tablex:0.0.8/table.typ'], $summary['packageReferences']);
        $t->same([
            'error' => ['@typst/tablex:0.0.8/table.typ'],
            'timing' => ['@preview/cetz:0.3.2/lib.typ'],
            'warning' => ['@preview/cetz:0.3.2/lib.typ'],
        ], $summary['packageReferencesByChannel']);
        $t->same(4, $summary['sourceIssueCount']);
        $t->same(4, $summary['distinctSourceIssueCount']);
        $t->same([
            'error-source-external',
            'timing-source-external',
            'warning-source-external',
            'warning-source-outside-root',
        ], $summary['issues']);
        $t->same(3, count($summary['channelSummaries']));
        $t->same('timing', $summary['channelSummaries'][0]['channel']);
        $t->same(2, $summary['channelSummaries'][0]['observationCount']);
        $t->same(1, $summary['channelSummaries'][0]['packageReferenceCount']);
        $t->same('warning', $summary['channelSummaries'][1]['channel']);
        $t->same(2, $summary['channelSummaries'][1]['observationCount']);
        $t->same(2, $summary['channelSummaries'][1]['sourceIssueCount']);
        $t->same('error', $summary['channelSummaries'][2]['channel']);
        $t->same(1, $summary['channelSummaries'][2]['observationCount']);
        $t->same(1, $summary['channelSummaries'][2]['sourceIssueCount']);
        $t->same($summary, $result['artifactProvenanceReview']['typstRuntimeSourceSummary']);
        $t->same($summary, $sequence['finalTypstRuntimeSourceSummary']);
        $t->same($summary['packageReferences'], $result['artifactProvenanceReview']['typstRuntimeSourceSummary']['packageReferences']);
        $t->contains('typst-runtime-source-summary:review', $diagnostics);
        $t->contains('typst-runtime-source-channels:3', $diagnostics);
        $t->contains('typst-runtime-source-observations:5', $diagnostics);
        $t->contains('typst-runtime-source-kind:typst-package:3', $diagnostics);
        $t->contains('typst-runtime-source-class:preview-registry:2', $diagnostics);
        $t->contains('typst-runtime-source-class:typst-registry:1', $diagnostics);
        $t->contains('typst-runtime-source-packages:2', $diagnostics);
        $t->contains('typst-runtime-source-issues:4', $diagnostics);
        $t->contains('typst-runtime-source-summary:review', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->same(['@preview/cetz:0.3.2/lib.typ'], $result['typstTimingSourcePolicy']['packageReferences']);
        $t->same(['@preview/cetz:0.3.2/lib.typ'], $result['typstWarningSourcePolicy']['packageReferences']);
        $t->same(['@typst/tablex:0.0.8/table.typ'], $result['typstErrorSourcePolicy']['packageReferences']);
    },
];
