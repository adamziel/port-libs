<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst open output viewer policy packet keeps viewer boundaries reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Open Output Viewer Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'summarizes typst open output viewer boundary policy without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/open-output-viewer-policy.pdf',
            'source' => '= Typst Open Output Viewer Policy',
            'engineOptions' => [
                '--open=xdg-open',
                '--open=/usr/bin/open',
                '--open=https://viewer.example.invalid/launch',
                '--open',
                'tools/review-viewer',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst open output viewer policy packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/open-output-viewer-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/open-output-viewer-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $policy = $plan['typstBoundaryProvenance']['openOutputViewerPolicy'] ?? null;
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $openCase = $cases['open-output'];

        $t->same('review', $policy['reviewStatus'] ?? null);
        $t->same(4, $policy['viewerCount']);
        $t->same(4, $policy['classifiedViewerCount']);
        $t->same(2, $policy['safeViewerCount']);
        $t->same(2, $policy['unsafeViewerCount']);
        $t->same([
            'absolute' => 1,
            'command' => 1,
            'invalid' => 0,
            'relative' => 1,
            'uri' => 1,
        ], $policy['viewerKindCounts']);
        $t->same('tools/review-viewer', $policy['selectedViewer']);
        $t->same('relative', $policy['selectedViewerKind']);
        $t->same(['/usr/bin/open', 'https://viewer.example.invalid/launch'], $policy['unsafeViewers']);
        $t->same(['open-output-viewer-external-boundary'], $policy['issues']);
        $t->same([
            ['raw' => 'xdg-open', 'viewer' => 'xdg-open', 'kind' => 'command', 'safe' => true, 'issues' => []],
            ['raw' => '/usr/bin/open', 'viewer' => '/usr/bin/open', 'kind' => 'absolute', 'safe' => false, 'issues' => ['open-output-viewer-external-boundary']],
            ['raw' => 'https://viewer.example.invalid/launch', 'viewer' => 'https://viewer.example.invalid/launch', 'kind' => 'uri', 'safe' => false, 'issues' => ['open-output-viewer-external-boundary']],
            ['raw' => 'tools/review-viewer', 'viewer' => 'tools/review-viewer', 'kind' => 'relative', 'safe' => true, 'issues' => []],
        ], $policy['viewers']);

        $t->same('review', $openCase['details']['viewerPolicyReviewStatus']);
        $t->same(2, $openCase['details']['unsafeViewerCount']);
        $t->same($policy['viewerKindCounts'], $openCase['details']['viewerKindCounts']);
        $t->same($policy['unsafeViewers'], $openCase['details']['unsafeViewers']);
        $t->contains('open-output:open-output-viewer-external-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('typst-open-output-viewer-policy:review', implode(',', $plan['diagnostics']));
        $t->contains('typst-open-output-viewer-unsafe:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-open-output-viewer-kind:absolute:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-open-output-viewer-kind:uri:1', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($policy, $result['typstBoundaryProvenance']['openOutputViewerPolicy']);
        $t->same($policy, $result['artifactProvenanceReview']['typstBoundaryProvenance']['openOutputViewerPolicy']);
        $t->same($plan['typstBoundaryMatrix'], $result['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($policy, $sequence['finalTypstBoundaryProvenance']['openOutputViewerPolicy']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
