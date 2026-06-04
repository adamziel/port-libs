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
$fakeLog = implode("\n", [
    'This is XeTeX, Version 3.141592653',
    "LaTeX Warning: Citation `migration-log' on page 1 undefined on input line 4.",
    'LaTeX Warning: Label(s) may have changed. Rerun to get cross-references right.',
    'Output written on pdf-review-packet.pdf (1 page, 12345 bytes).',
    '',
]);
$fakeResult = $handoff->fakeRun($plan, [
    'stdout' => 'fake xelatex runner accepted the planned argv',
    'files' => [
        $plan['sourceFile'] => (string) $plan['sourceBytes'],
        'templates/review-packet.tex' => '\documentclass{$documentclass$}' . "\n" . '$for(include-in-header)$$include-in-header$$endfor$' . "\n" . '\begin{document}$body$\end{document}',
        'templates/review-header.tex' => '\usepackage{fontspec}',
        'handoff/pdf-review-packet.aux' => "\\relax\n",
        'handoff/pdf-review-packet.out' => "\n",
        'handoff/pdf-review-packet.log' => $fakeLog,
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
    'engineLogFile' => $plan['engineLogFile'],
    'expectedEngineArtifacts' => $plan['expectedEngineArtifacts'],
    'templateVariables' => $plan['templateVariables'],
    'metadata' => $plan['metadata'],
    'sourceSha256' => $plan['sourceSha256'],
    'fakeRun' => [
        'ok' => $fakeResult['ok'],
        'reason' => $fakeResult['reason'],
        'bytes' => $fakeResult['bytes'],
        'sourceArtifactsSha256' => $fakeResult['sourceArtifactsSha256'],
        'producedArtifactsSha256' => $fakeResult['producedArtifactsSha256'],
        'engineLogFiles' => $fakeResult['engineLogFiles'],
        'engineWarnings' => $fakeResult['engineWarnings'],
        'engineErrors' => $fakeResult['engineErrors'],
        'rerunNeeded' => $fakeResult['rerunNeeded'],
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
        'handoff/pdf-review-packet.log',
        'handoff/pdf-review-packet.aux',
        'documentclass=scrartcl',
        '--resource-path=media:review assets',
        'Source Serif 4',
        'source-artifacts-validated:2',
        'produced-engine-artifacts:3',
        'engine-log-warnings:2',
        'engine-rerun-needed',
        'migration-log',
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
