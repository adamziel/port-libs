<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst package storage matrix packet keeps storage kinds reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Package Storage Matrix Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'maps typst package storage kind counts into boundary matrix without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/package-storage-kind-matrix.pdf',
            'source' => '= Typst Package Storage Matrix Packet',
            'engineOptions' => [
                '--package-path=vendor/typst-packages',
                '--package-path=/srv/typst-packages',
                '--package-cache=https://cache.example.invalid/typst',
                '--package-cache=',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst package storage kind matrix packet\n%%EOF\n";

        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $packageStorage = $cases['package-storage'];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/package-storage-kind-matrix.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/package-storage-kind-matrix.pdf' => $pdfBytes,
            ],
        ]]);

        $t->same('review', $packageStorage['reviewStatus']);
        $t->same(4, $packageStorage['observed']);
        $t->same(1, $packageStorage['details']['safeStorageEntryCount']);
        $t->same(3, $packageStorage['details']['unsafeStorageEntryCount']);
        $t->same(2, $packageStorage['details']['packagePathCount']);
        $t->same(2, $packageStorage['details']['packageCacheCount']);
        $t->same(1, $packageStorage['details']['relativeStorageEntryCount']);
        $t->same(0, $packageStorage['details']['workspaceStorageEntryCount']);
        $t->same(1, $packageStorage['details']['absoluteStorageEntryCount']);
        $t->same(1, $packageStorage['details']['uriStorageEntryCount']);
        $t->same(1, $packageStorage['details']['invalidStorageEntryCount']);
        $t->same([
            'package-cache-empty',
            'package-cache-external-boundary',
            'package-path-external-boundary',
        ], $packageStorage['issues']);
        $t->contains('package-storage:package-cache-empty', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('typst-package-storage-policy:review', implode(',', $plan['diagnostics']));
        $t->contains('typst-package-storage-unsafe:3', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($plan['typstBoundaryMatrix'], $result['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
