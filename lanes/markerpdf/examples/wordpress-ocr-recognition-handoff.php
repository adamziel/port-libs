<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrRecognition;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (string $text): array {
    return [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => $text,
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['bbox' => [72.0, 96.0, 540.0, 120.0]],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => $text, 'bbox' => [72.0, 96.0, 540.0, 120.0]],
                ],
            ],
        ],
    ];
};

$recognition = new OcrRecognition();
$result = $recognition->runWithSuppliedPages(
    [$page('@@@ ### !!!')],
    [$page('Recovered OCR text for a migrated PDF page.')]
);

$paragraph = htmlspecialchars(
    (string) $result['pages'][0]['blocks'][0]['lines'][0]['text'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo json_encode([
    'scenario' => 'wordpress-pdf-ocr-recognition-handoff',
    'stats' => $result['stats'],
    'block' => "<!-- wp:paragraph -->\n<p>{$paragraph}</p>\n<!-- /wp:paragraph -->",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
