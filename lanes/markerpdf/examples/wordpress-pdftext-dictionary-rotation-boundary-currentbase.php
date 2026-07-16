<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$font = ['name' => 'Helvetica', 'flags' => 0, 'weight' => 400, 'size' => 11.0];
$page = [
    'page' => 28,
    'bbox' => [0.0, 0.0, 400.0, 600.0],
    'width' => 400.0,
    'height' => 600.0,
    'rotation' => 90.0,
    'blocks' => [[
        'bbox' => [0.12, 0.12, 0.70, 0.15],
        'lines' => [[
            'bbox' => [0.12, 0.12, 0.70, 0.15],
            'spans' => [[
                'text' => "Rotated dictionary import\n",
                'bbox' => [0.12, 0.12, 0.70, 0.15],
                'font' => $font,
                'rotation' => 0,
                'char_start_idx' => 0,
                'char_end_idx' => 2,
                'chars' => [
                    [
                        'char' => 'R',
                        'bbox' => [0.12, 0.12, 0.14, 0.15],
                        'rotation' => 12.5,
                        'font' => $font,
                        'char_idx' => 0,
                    ],
                    [
                        'char' => 'o',
                        'bbox' => [0.14, 0.12, 0.16, 0.15],
                        'rotation' => 270.0,
                        'font' => $font,
                        'char_idx' => 1,
                    ],
                ],
            ]],
        ]],
    ]],
];

$extractor = new PdfTextDocumentExtractor();
$document = $extractor->getTextBlocks([$page], maxPages: 1, keepChars: true);
$span = $document['pages'][0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$charSpan = $document['pages'][0]['char_blocks'][0]['lines'][0]['spans'][0] ?? [];

$rejectsPageRotation = static function (mixed $rotation) use ($extractor, $page): bool {
    $candidate = $page;
    $candidate['rotation'] = $rotation;

    try {
        $extractor->getTextBlocks([$candidate], maxPages: 1, keepChars: true);
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};

$fractionalPageRotationRejected = $rejectsPageRotation(90.5);
$unsupportedPageRotationRejected = $rejectsPageRotation(45);
$characterAnglePreserved = ($span['chars'][0]['rotation'] ?? null) === 12.5
    && ($charSpan['chars'][0]['rotation'] ?? null) === 12.5
    && ($span['chars'][1]['rotation'] ?? null) === 270
    && ($charSpan['chars'][1]['rotation'] ?? null) === 270;
$pageRotationAccepted = ($document['pages'][0]['rotation'] ?? null) === 90
    && ($document['pages'][0]['bbox'] ?? null) === [0.0, 0.0, 600.0, 400.0];

if (!$characterAnglePreserved || !$pageRotationAccepted || !$fractionalPageRotationRejected || !$unsupportedPageRotationRejected) {
    throw new RuntimeException('Expected pdftext character angles to survive while malformed page rotations fail closed.');
}

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));

echo '<!-- markerpdf-pdftext-dictionary-rotation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-rotation-boundary-currentbase',
    'source_truth' => 'pdftext records PDFium page rotations as right-angle page metadata and per-character FPDFText_GetCharAngle degrees as character review metadata before markerPDF page conversion',
    'support_component' => 'pdf-text-dictionary-core',
    'visible_wordpress_text' => $blocks[0]['text'] ?? '',
    'page_rotation_accepted' => $pageRotationAccepted,
    'rendered_marker_bbox' => $document['pages'][0]['bbox'] ?? null,
    'fractional_character_angle_preserved' => ($span['chars'][0]['rotation'] ?? null) === 12.5,
    'integral_float_character_angle_normalized' => ($span['chars'][1]['rotation'] ?? null) === 270,
    'fractional_page_rotation_rejected' => $fractionalPageRotationRejected,
    'unsupported_page_rotation_rejected' => $unsupportedPageRotationRejected,
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
