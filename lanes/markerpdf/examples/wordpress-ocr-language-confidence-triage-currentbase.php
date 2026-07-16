<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrLanguage;
use PortLibs\MarkerPDF\OcrRecognition;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (string $text, array $bbox): array {
    return [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => $text,
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['bbox' => $bbox],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => $text, 'bbox' => $bbox],
                ],
            ],
        ],
    ];
};

$languages = new OcrLanguage();
$suryaLanguages = $languages->normalizeAndValidate(['English', 'Spanish'], 'surya');
$recognition = new OcrRecognition();
$pages = [
    $page('@@@ ### !!!', [72.0, 96.0, 540.0, 120.0]),
    $page('### !!! @@@', [72.0, 150.0, 540.0, 174.0]),
];

$recognizedPages = $recognition->buildSuryaRecognitionPages(
    $pages,
    [0, 1],
    [
        [
            [
                'text' => 'Recovered multilingual WordPress import text.',
                'bbox' => [120.0, 192.0, 540.0, 232.0],
                'confidence' => 0.08,
            ],
        ],
        [
            [
                'text' => '@@@ ### !!!',
                'bbox' => [120.0, 300.0, 540.0, 348.0],
                'confidence' => 0.99,
            ],
        ],
    ],
    [
        ['width' => 1200, 'height' => 1600],
        ['width' => 1200, 'height' => 1600],
    ],
    $suryaLanguages
);

$result = $recognition->runWithSuppliedPages($pages, $recognizedPages);
$paragraph = htmlspecialchars(
    (string) $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$html = "<!-- wp:paragraph -->\n<p>{$paragraph}</p>\n<!-- /wp:paragraph -->\n";

if (($result['stats']['ocr_success'] ?? null) !== 1 || ($result['stats']['ocr_failed'] ?? null) !== 1) {
    throw new RuntimeException('Expected one accepted and one rejected OCR page.');
}
if (($result['pages'][0]['ocr_confidence']['min'] ?? null) !== 0.08) {
    throw new RuntimeException('Expected low confidence to be preserved as review metadata.');
}
if (isset($result['pages'][1]['ocr_method'])) {
    throw new RuntimeException('Expected high-confidence garbled OCR to stay rejected.');
}
if (!str_contains($html, 'Recovered multilingual WordPress import text.') || str_contains($html, '@@@ ### !!!')) {
    throw new RuntimeException('Expected WordPress block HTML to include only accepted OCR text.');
}

echo json_encode([
    'scenario' => 'wordpress-ocr-language-confidence-triage-currentbase',
    'source_truth' => 'marker.ocr.recognition.run_ocr accepts pages by detect_bad_ocr text quality; surya OCRResult TextLine confidence is review metadata, not the replacement threshold',
    'languages' => $result['pages'][0]['ocr_languages'] ?? [],
    'lang_token_ids' => $languages->langTokenIds($suryaLanguages ?? []),
    'ocr_stats' => $result['stats'],
    'accepted_confidence' => $result['pages'][0]['ocr_confidence'] ?? null,
    'rejected_page_preserved_text' => $result['pages'][1]['prelim_text'] ?? null,
    'wordpress_blocks' => $html,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
