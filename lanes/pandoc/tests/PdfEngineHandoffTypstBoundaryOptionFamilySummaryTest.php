<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst option-family summary packet keeps boundary provenance visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Boundary Option Family Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst boundary option-family summary assertion count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBoundaryOptionFamilySummaryCases'] ?? null);
        $t->same(28, $manifest['typstBoundaryOptionFamilySummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBoundaryOptionFamilySummaryCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['breakdown']['typstBoundaryOptionFamilySummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBoundaryOptionFamilySummaryCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['inventory']['typstBoundaryOptionFamilySummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBoundaryOptionFamilySummaryCases'] ?? null);
        $t->same(28, $manifest['inventory']['typstBoundaryOptionFamilySummaryAssertions'] ?? null);
    },

    'summarizes typst boundary option families for reviewer handoff without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/build/option-family.typ',
            'outputPath' => 'build/option-family.pdf',
            'source' => '= Typst Option Family Summary',
            'engineOptions' => [
                '--root=workspace',
                '--font-path=/srv/fonts',
                '--cert=https://ca.example.invalid/root.pem',
                '--package-path=vendor/typst',
                '--package-cache=https://cache.example.invalid/typst',
                '--input=audience=reviewer',
                '--input=audience=auditor',
                '--pages=1-3,2',
                '--ppi=0',
                '--format=svg',
                '--format=pdf',
                '--features=html,packages',
                '--features=packages,a11y',
                '--jobs=0',
                '--creation-timestamp=bad',
                '--deps=build/option-family.d',
                '--timings=build/option-family-timings.json',
                '--diagnostic-format=json',
                '--color=always',
                '--open=xdg-open',
                '--ignore-system-fonts',
            ],
            'engineEnvironment' => [
                'TYPST_ROOT' => '/outside/workspace',
                'TYPST_PACKAGE_PATH' => '/tmp/typst-packages',
                'TYPST_PACKAGE_CACHE_PATH' => 'https://cache.example.invalid/env',
                'TYPST_CERT' => '/etc/ssl/certs/env.pem',
                'SOURCE_DATE_EPOCH' => '1234567890',
                'TYPST_FEATURES' => 'legacy-html',
                'TYPST_IGNORE_SYSTEM_FONTS' => '1',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst option-family summary packet\n%%EOF\n";
        $depfile = "build/option-family.pdf: workspace/build/option-family.typ\n";
        $timingsBytes = '{"traceEvents":[{"name":"export","args":{"file":"workspace/build/option-family.typ","line":1}}]}';
        $expectedFamilyCounts = [
            'certificate' => 1,
            'creation-timestamp' => 1,
            'diagnostic-output' => 2,
            'environment' => 7,
            'execution-policy' => 1,
            'feature-gate' => 2,
            'font-access' => 1,
            'font-path' => 1,
            'input-variable' => 2,
            'open-output' => 1,
            'output-format' => 2,
            'override' => 3,
            'package-storage' => 4,
            'pdf-export' => 2,
            'root-boundary' => 1,
            'sidecar-output' => 2,
        ];
        $expectedFamilyIssueCounts = [
            'certificate' => 2,
            'creation-timestamp' => 2,
            'diagnostic-output' => 0,
            'environment' => 11,
            'execution-policy' => 1,
            'feature-gate' => 1,
            'font-access' => 1,
            'font-path' => 1,
            'input-variable' => 2,
            'open-output' => 1,
            'output-format' => 3,
            'override' => 4,
            'package-storage' => 4,
            'pdf-export' => 2,
            'root-boundary' => 2,
            'sidecar-output' => 0,
        ];
        $expectedFamilies = [
            [
                'family' => 'root-boundary',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 2,
                'issues' => ['root-environment-shadowed', 'root-external-boundary'],
            ],
            [
                'family' => 'environment',
                'observed' => 7,
                'reviewStatus' => 'review',
                'issueCount' => 11,
                'issues' => [
                    'certificate-environment-shadowed',
                    'certificate-external-boundary',
                    'creation-timestamp-environment-shadowed',
                    'features-environment-shadowed',
                    'ignore-system-fonts-environment-shadowed',
                    'package-cache-environment-shadowed',
                    'package-cache-external-boundary',
                    'package-path-environment-shadowed',
                    'package-path-external-boundary',
                    'root-environment-shadowed',
                    'root-external-boundary',
                ],
            ],
            [
                'family' => 'font-path',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 1,
                'issues' => ['font-path-external-boundary'],
            ],
            [
                'family' => 'certificate',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 2,
                'issues' => ['certificate-environment-shadowed', 'certificate-external-boundary'],
            ],
            [
                'family' => 'package-storage',
                'observed' => 4,
                'reviewStatus' => 'review',
                'issueCount' => 4,
                'issues' => [
                    'package-cache-environment-shadowed',
                    'package-cache-external-boundary',
                    'package-path-environment-shadowed',
                    'package-path-external-boundary',
                ],
            ],
            [
                'family' => 'input-variable',
                'observed' => 2,
                'reviewStatus' => 'review',
                'issueCount' => 2,
                'issues' => ['input-variable-boundary-overridden', 'input-variable-boundary-overridden:audience'],
            ],
            [
                'family' => 'sidecar-output',
                'observed' => 2,
                'reviewStatus' => 'ok',
                'issueCount' => 0,
                'issues' => [],
            ],
            [
                'family' => 'diagnostic-output',
                'observed' => 2,
                'reviewStatus' => 'ok',
                'issueCount' => 0,
                'issues' => [],
            ],
            [
                'family' => 'font-access',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 1,
                'issues' => ['ignore-system-fonts-environment-shadowed'],
            ],
            [
                'family' => 'output-format',
                'observed' => 2,
                'reviewStatus' => 'review',
                'issueCount' => 3,
                'issues' => ['conflicting-format-options:2', 'explicit-format-not-pdf:svg', 'format-boundary-overridden'],
            ],
            [
                'family' => 'pdf-export',
                'observed' => 2,
                'reviewStatus' => 'review',
                'issueCount' => 2,
                'issues' => ['pages-overlapping-selection-boundary', 'ppi-nonpositive-boundary'],
            ],
            [
                'family' => 'feature-gate',
                'observed' => 2,
                'reviewStatus' => 'review',
                'issueCount' => 1,
                'issues' => ['features-environment-shadowed'],
            ],
            [
                'family' => 'execution-policy',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 1,
                'issues' => ['jobs-nonpositive-boundary'],
            ],
            [
                'family' => 'creation-timestamp',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 2,
                'issues' => ['creation-timestamp-environment-shadowed', 'creation-timestamp-invalid-boundary'],
            ],
            [
                'family' => 'open-output',
                'observed' => 1,
                'reviewStatus' => 'review',
                'issueCount' => 1,
                'issues' => ['open-output-side-effect-boundary'],
            ],
            [
                'family' => 'override',
                'observed' => 3,
                'reviewStatus' => 'review',
                'issueCount' => 4,
                'issues' => [
                    'features-boundary-overridden',
                    'format-boundary-overridden',
                    'input-variable-boundary-overridden',
                    'input-variable-boundary-overridden:audience',
                ],
            ],
        ];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/option-family.d' => $depfile,
                'build/option-family-timings.json' => $timingsBytes,
                'build/option-family.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/option-family.d' => $depfile,
                'build/option-family-timings.json' => $timingsBytes,
                'build/option-family.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(16, $summary['optionFamilyCount']);
        $t->same(14, $summary['optionFamilyReviewCount']);
        $t->same(37, $summary['optionFamilyIssueCount']);
        $t->same($expectedFamilyCounts, $summary['optionFamilyCounts']);
        $t->same($expectedFamilyIssueCounts, $summary['optionFamilyIssueCounts']);
        $t->same($expectedFamilies, $summary['optionFamilies']);
        $t->contains('typst-boundary-summary-option-families:16', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-review-families:14', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-output-formats:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-issues:22', implode(',', $plan['diagnostics']));
        $t->same(['build/option-family.d', 'build/option-family-timings.json'], $plan['expectedEngineArtifacts']);
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->same(hash('sha256', $depfile), $result['producedArtifactsSha256']['build/option-family.d']);
        $t->same(hash('sha256', $timingsBytes), $result['producedArtifactsSha256']['build/option-family-timings.json']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
