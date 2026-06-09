<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$markdown = <<<'MARKDOWN'
---
title: "PDF Active Content Review"
author: [Migration Desk]
date: 2026-06-06
---

Review packet for a produced PDF whose catalog JavaScript actions are stored
in a nested name tree.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$handoff = new PdfEngineHandoff();
$plan = $handoff->plan($document, [
    'engine' => 'xelatex',
    'outputPath' => 'handoff/pdf-active-content-review.pdf',
]);

$openScript = 'app.alert("Review name tree action")';
$pdfBytes = implode("\n", [
    '%PDF-1.7',
    '1 0 obj',
    '<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 8 0 R >> >>',
    'endobj',
    '2 0 obj',
    '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
    'endobj',
    '3 0 obj',
    '<< /Type /Page /Parent 2 0 R >>',
    'endobj',
    '8 0 obj',
    '<< /Kids [9 0 R 10 0 R] >>',
    'endobj',
    '9 0 obj',
    '<< /Limits [(ReviewOpen) (ReviewOpen)] /Names [(ReviewOpen) 11 0 R] >>',
    'endobj',
    '10 0 obj',
    '<< /Kids [12 0 R] >>',
    'endobj',
    '11 0 obj',
    '<< /S /JavaScript /JS (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $openScript) . ') >>',
    'endobj',
    '12 0 obj',
    '<< /Limits [(ReviewSubmit) (ReviewSubmit)] /Names [(ReviewSubmit) << /S /SubmitForm /F (https://example.test/review/submit) >>] >>',
    'endobj',
    'trailer',
    '<< /Root 1 0 R >>',
    '%%EOF',
    '',
]);

$fakeResult = $handoff->fakeRun($plan, [
    'files' => [
        $plan['outputFile'] => $pdfBytes,
    ],
]);
$fakeSequence = $handoff->fakeRunSequence($plan, [
    [
        'files' => [
            $plan['outputFile'] => $pdfBytes,
        ],
    ],
]);

$summary = [
    'plan' => [
        'kind' => $plan['kind'],
        'willExecute' => $plan['willExecute'],
        'engine' => $plan['engine'],
        'sourceFile' => $plan['sourceFile'],
        'outputFile' => $plan['outputFile'],
    ],
    'fakeRun' => [
        'ok' => $fakeResult['ok'],
        'pdfActiveActions' => $fakeResult['pdfActiveActions'],
        'pdfActiveActionTypes' => $fakeResult['pdfActiveActionTypes'],
        'diagnostics' => $fakeResult['diagnostics'],
    ],
    'fakeRunSequence' => [
        'ok' => $fakeSequence['ok'],
        'finalPdfActiveActions' => $fakeSequence['finalPdfActiveActions'],
        'finalPdfActiveActionTypes' => $fakeSequence['finalPdfActiveActionTypes'],
    ],
];

if (in_array('--self-test', $argv, true)) {
    $json = json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    foreach ([
        '"willExecute":false',
        'catalog.Names.JavaScript.Kids.9 0 R.ReviewOpen',
        'catalog.Names.JavaScript.Kids.10 0 R.Kids.12 0 R.ReviewSubmit',
        '"JavaScript":1',
        '"SubmitForm":1',
        'pdf-byte-active-actions:2',
        'pdf-byte-active-action-types:2',
        'fake-runner-no-execution',
        'finalPdfActiveActions',
    ] as $needle) {
        if (!str_contains($json, $needle)) {
            throw new RuntimeException('PDF JavaScript name-tree handoff self-test missing: ' . $needle);
        }
    }

    echo "pdf javascript name-tree handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
