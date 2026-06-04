<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$markdown = <<<'MARKDOWN'
---
title: "PDF Review Packet"
author: [Migration Desk]
date: 2026-06-04
---

Reviewer formula $E = mc^2$ and source cite \cite{migration-log}.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$handoff = new PdfEngineHandoff();
$plan = $handoff->plan($document, [
    'engine' => 'xelatex',
    'outputPath' => 'handoff/pdf-review-packet.pdf',
    'templatePath' => 'templates/review-packet.tex',
    'includeInHeader' => 'templates/review-header.tex',
    'resourcePaths' => ['media', 'review assets'],
    'variables' => [
        'documentclass' => 'scrartcl',
        'geometry' => ['margin=1in', 'includeheadfoot'],
        'colorlinks' => true,
        'mainfont' => 'Source Serif 4',
    ],
    'engineOptions' => ['-file-line-error'],
]);
$fakeResult = $handoff->fakeRun($plan, [
    'stdout' => 'fake xelatex runner accepted the planned argv',
    'files' => [
        $plan['sourceFile'] => (string) $plan['sourceBytes'],
        'templates/review-packet.tex' => '\documentclass{$documentclass$}' . "\n" . '$for(include-in-header)$$include-in-header$$endfor$' . "\n" . '\begin{document}$body$\end{document}',
        'templates/review-header.tex' => '\usepackage{fontspec}',
        $plan['outputFile'] => "%PDF-1.7\n% fake WordPress import review packet\n%%EOF\n",
    ],
]);

$summary = [
    'engine' => $plan['engine'],
    'intermediateFormat' => $plan['intermediateFormat'],
    'sourceFile' => $plan['sourceFile'],
    'outputFile' => $plan['outputFile'],
    'argv' => $plan['argv'],
    'writerArguments' => $plan['writerArguments'],
    'templateFile' => $plan['templateFile'],
    'includeInHeaderFiles' => $plan['includeInHeaderFiles'],
    'resourcePaths' => $plan['resourcePaths'],
    'sourceArtifacts' => $plan['sourceArtifacts'],
    'templateVariables' => $plan['templateVariables'],
    'metadata' => $plan['metadata'],
    'sourceSha256' => $plan['sourceSha256'],
    'fakeRun' => [
        'ok' => $fakeResult['ok'],
        'reason' => $fakeResult['reason'],
        'bytes' => $fakeResult['bytes'],
        'sourceArtifactsSha256' => $fakeResult['sourceArtifactsSha256'],
        'diagnostics' => $fakeResult['diagnostics'],
    ],
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'xelatex',
        'latex',
        'handoff/pdf-review-packet.tex',
        'handoff/pdf-review-packet.pdf',
        'PDF Review Packet',
        'templates/review-packet.tex',
        'templates/review-header.tex',
        'documentclass=scrartcl',
        '--resource-path=media:review assets',
        'Source Serif 4',
        'source-artifacts-validated:2',
        'fake-runner-no-execution',
    ] as $needle) {
        if (!str_contains(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $needle)) {
            throw new RuntimeException('PDF engine handoff self-test missing: ' . $needle);
        }
    }

    echo "pdf engine handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
