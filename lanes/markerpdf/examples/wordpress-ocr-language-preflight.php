<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrLanguage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$metadata = [
    'migration-spanish-russian.pdf' => ['languages' => ['Spanish', 'Russian']],
    'migration-default-language.pdf' => ['languages' => null],
];

$languages = new OcrLanguage();
$documents = [];

foreach ($metadata as $filename => $item) {
    $documents[] = [
        'filename' => $filename,
        'ocrEngine' => 'ocrmypdf',
        'languages' => $languages->normalizeAndValidate($item['languages'], 'ocrmypdf'),
    ];
}

echo json_encode([
    'scenario' => 'wordpress-multilingual-pdf-ocr-preflight',
    'documents' => $documents,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
