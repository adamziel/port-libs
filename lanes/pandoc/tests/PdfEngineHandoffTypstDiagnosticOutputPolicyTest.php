<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic output policy packet keeps review controls metadata-only.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Output Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'summarizes typst diagnostic output policy without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/diagnostic-output-policy.pdf',
            'source' => '= Typst Diagnostic Output Policy',
            'engineOptions' => [
                '--diagnostic-format=short',
                '--diagnostic-format=json',
                '--color=rainbow',
                '--color=always',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst diagnostic output policy packet\n%%EOF\n";
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'controlCount' => 2,
            'formatEntryCount' => 2,
            'colorEntryCount' => 2,
            'selectedFormat' => 'json',
            'selectedFormatMachineReadable' => true,
            'selectedFormatSafe' => true,
            'formatOptions' => ['short', 'json'],
            'distinctFormats' => ['json', 'short'],
            'validFormatCount' => 2,
            'invalidFormatCount' => 0,
            'selectedColor' => 'always',
            'selectedAnsiColor' => 'enabled',
            'selectedColorSafe' => true,
            'colorOptions' => ['always'],
            'distinctColors' => ['always'],
            'validColorCount' => 1,
            'invalidColorCount' => 1,
            'invalidControlCount' => 1,
            'overrideCount' => 2,
            'formatOverrideCount' => 1,
            'colorOverrideCount' => 1,
            'issues' => [
                'diagnostic-color-boundary-overridden',
                'diagnostic-color-invalid-boundary',
                'diagnostic-format-boundary-overridden',
            ],
        ];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/diagnostic-output-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/diagnostic-output-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }

        $t->same($expectedPolicy, $plan['typstBoundaryProvenance']['diagnosticOutputPolicy']);
        $t->same($expectedPolicy, $result['typstBoundaryProvenance']['diagnosticOutputPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['typstBoundaryProvenance']['diagnosticOutputPolicy']);
        $t->same($expectedPolicy, $sequence['finalTypstBoundaryProvenance']['diagnosticOutputPolicy']);
        $t->same('json', $plan['typstBoundaryProvenance']['diagnosticOutput']['format']['format']);
        $t->same('always', $plan['typstBoundaryProvenance']['diagnosticOutput']['color']['color']);
        $t->same('review', $cases['diagnostic-output']['reviewStatus']);
        $t->same(0, $cases['diagnostic-output']['details']['formatHistoryCount']);
        $t->same(2, $cases['diagnostic-output']['details']['colorHistoryCount']);
        $t->same(1, $cases['diagnostic-output']['details']['invalidColorCount']);
        $t->contains('typst-diagnostic-output-policy:review', implode(',', $plan['diagnostics']));
        $t->contains('typst-diagnostic-output-invalid:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-diagnostic-output-overrides:2', implode(',', $plan['diagnostics']));
        $t->contains('diagnostic-output:diagnostic-color-invalid-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same(true, $result['ok']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
    },
];
