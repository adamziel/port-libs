<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst artifact byte-length provenance keeps boundary review packets inspectable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Artifact Byte Length Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped pdf artifact byte length provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfArtifactByteLengthProvenanceCases'] ?? null);
        $t->same(49, $manifest['pdfArtifactByteLengthProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfArtifactByteLengthProvenanceCases'] ?? null);
        $t->same(49, $manifest['benchmarkDenominator']['breakdown']['pdfArtifactByteLengthProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfArtifactByteLengthProvenanceCases'] ?? null);
        $t->same(49, $manifest['benchmarkDenominator']['inventory']['pdfArtifactByteLengthProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfArtifactByteLengthProvenanceCases'] ?? null);
        $t->same(49, $manifest['inventory']['pdfArtifactByteLengthProvenanceAssertions'] ?? null);
    },

    'records typst source resource and sidecar byte lengths without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/artifact-length.typ',
            'outputPath' => 'build/artifact-length.pdf',
            'source' => '= Typst Artifact Byte Length Packet',
            'templatePath' => 'templates/review.typ',
            'includeInHeader' => 'templates/header.typ',
            'resourceFiles' => ['refs/works.bib', 'media/logo.svg'],
            'engineOptions' => [
                '--deps=build/artifact-length.d',
                '--timings=build/artifact-length-timings.json',
            ],
        ]);
        $template = '#let body = body';
        $header = '#set text(size: 11pt)';
        $logo = '<svg viewBox="0 0 1 1"></svg>';
        $bib = '@book{packet,title={Packet}}';
        $pdfBytes = "%PDF-1.7\n% fake Typst artifact byte length packet\n%%EOF\n";
        $depfile = 'build/artifact-length.pdf: workspace/artifact-length.typ templates/review.typ templates/header.typ media/logo.svg refs/works.bib' . "\n";
        $timingsBytes = '{"traceEvents":[{"name":"compile","args":{"file":"workspace/artifact-length.typ"}}]}';

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'templates/review.typ' => $template,
                'templates/header.typ' => $header,
                'media/logo.svg' => $logo,
                'refs/works.bib' => $bib,
                'build/artifact-length.d' => $depfile,
                'build/artifact-length-timings.json' => $timingsBytes,
                'build/artifact-length.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'templates/review.typ' => $template,
                'templates/header.typ' => $header,
                'media/logo.svg' => $logo,
                'refs/works.bib' => $bib,
                'build/artifact-length.d' => $depfile,
                'build/artifact-length-timings.json' => $timingsBytes,
                'build/artifact-length.pdf' => $pdfBytes,
            ],
        ]]);

        $expectedSourceLengths = [
            'templates/review.typ' => strlen($template),
            'templates/header.typ' => strlen($header),
        ];
        $expectedSourceHashes = [
            'templates/review.typ' => hash('sha256', $template),
            'templates/header.typ' => hash('sha256', $header),
        ];
        $expectedResourceLengths = [
            'media/logo.svg' => strlen($logo),
            'refs/works.bib' => strlen($bib),
        ];
        $expectedResourceHashes = [
            'media/logo.svg' => hash('sha256', $logo),
            'refs/works.bib' => hash('sha256', $bib),
        ];
        $expectedProducedLengths = [
            'build/artifact-length-timings.json' => strlen($timingsBytes),
            'build/artifact-length.d' => strlen($depfile),
        ];
        $expectedProducedHashes = [
            'build/artifact-length-timings.json' => hash('sha256', $timingsBytes),
            'build/artifact-length.d' => hash('sha256', $depfile),
        ];
        ksort($expectedProducedLengths);
        ksort($expectedProducedHashes);
        $expectedDependencyLengths = ['build/artifact-length.d' => strlen($depfile)];
        $expectedDependencyHashes = ['build/artifact-length.d' => hash('sha256', $depfile)];

        $t->same(['build/artifact-length.d', 'build/artifact-length-timings.json'], $plan['expectedEngineArtifacts']);
        $t->same(true, $result['ok']);
        $t->same('ok', $result['status']);
        $t->same(strlen($pdfBytes), $result['bytes']);
        $t->same(hash('sha256', $pdfBytes), $result['pdfSha256']);
        $t->same($expectedSourceHashes, $result['sourceArtifactsSha256']);
        $t->same($expectedSourceLengths, $result['sourceArtifactsByteLengths']);
        $t->same($expectedSourceLengths, $result['handoffInputProvenance']['sourceArtifactsByteLengths']);
        $t->same($expectedResourceHashes, $result['resourceArtifactsSha256']);
        $t->same($expectedResourceLengths, $result['resourceArtifactsByteLengths']);
        $t->same($expectedResourceLengths, $result['handoffInputProvenance']['resourceArtifactsByteLengths']);
        $t->same($expectedProducedHashes, $result['producedArtifactsSha256']);
        $t->same($expectedProducedLengths, $result['producedArtifactsByteLengths']);
        $t->same($expectedDependencyHashes, $result['engineDependencyArtifactsSha256']);
        $t->same($expectedDependencyLengths, $result['engineDependencyArtifactsByteLengths']);
        $t->same($expectedProducedLengths, $result['artifactProvenanceReview']['producedEngineArtifactsByteLengths']);
        $t->same($expectedDependencyLengths, $result['artifactProvenanceReview']['engineDependencyArtifactsByteLengths']);
        $t->same($result['handoffInputProvenance'], $result['artifactProvenanceReview']['handoffInputProvenance']);
        $t->same([], $result['missingResourceFiles']);
        $t->same([], $result['artifactProvenanceReview']['missingExpectedEngineArtifacts']);
        $t->same([
            'media/logo.svg',
            'refs/works.bib',
            'templates/header.typ',
            'templates/review.typ',
            'workspace/artifact-length.typ',
        ], $result['engineInputFiles']);
        $t->same(['build/artifact-length.pdf'], $result['engineOutputFiles']);
        $t->same('ok', $result['typstDependencyOutputPolicy']['reviewStatus']);
        $t->same(true, $result['typstDependencyOutputPolicy']['declaredOutputPresent']);
        $t->same(false, array_key_exists('build/artifact-length.pdf', $result['producedArtifactsByteLengths']));
        $t->same($expectedResourceLengths, $sequence['finalResourceArtifactsByteLengths']);
        $t->same($expectedDependencyLengths, $sequence['finalEngineDependencyArtifactsByteLengths']);
        $t->same(true, $sequence['ok']);
        $t->same(1, $sequence['attempts']);
        $t->same(strlen($pdfBytes), $sequence['finalBytes']);
        $t->same($expectedResourceHashes, $sequence['finalResourceArtifactsSha256']);
        $t->same($expectedDependencyHashes, $sequence['finalEngineDependencyArtifactsSha256']);
        $t->contains('source-artifacts-validated:2', implode(',', $result['diagnostics']));
        $t->contains('resource-files-validated:2', implode(',', $result['diagnostics']));
        $t->contains('produced-engine-artifacts:2', implode(',', $result['diagnostics']));
        $t->contains('engine-dependency-artifacts:1', implode(',', $result['diagnostics']));
        $t->contains('engine-output-files:1', implode(',', $result['diagnostics']));
        $t->contains('artifact-provenance-review:', implode(',', $result['diagnostics']));
        $t->contains('fake-runner-no-execution', implode(',', $result['diagnostics']));
        $t->same('ok', $result['handoffInputProvenance']['validationStatus']);
        $t->same([], $result['handoffInputProvenance']['validationIssues']);
    },
];
