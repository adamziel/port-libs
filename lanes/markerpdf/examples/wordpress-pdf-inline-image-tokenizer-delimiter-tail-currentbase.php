<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$cases = [
    [
        'label' => 'array_close',
        'dictionary' => '/W 1 /H 1 /CS /G /BPC 8 ] /F /MalformedPreview',
        'noise' => 'Array Close Tail Inline Noise',
        'before' => 'Before Array Close Delimiter Tail',
        'after' => 'After Array Close Delimiter Tail',
    ],
    [
        'label' => 'dictionary_close',
        'dictionary' => '/W 1 /H 1 /CS /G /BPC 8 >> /F /MalformedPreview',
        'noise' => 'Dictionary Close Tail Inline Noise',
        'before' => 'Before Dictionary Close Delimiter Tail',
        'after' => 'After Dictionary Close Delimiter Tail',
    ],
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$paragraphs = [];
$caseMetadata = [];

foreach ($cases as $case) {
    $dictionary = $case['dictionary'];
    $payload = "\x7f EI BT /F1 12 Tf 72 690 Td ({$case['noise']}) Tj ET rawtail";
    $content = "BT /F1 12 Tf 72 720 Td ({$case['before']}) Tj ET\n"
        . "BI {$dictionary} ID\n{$payload}\nEI\n"
        . "BT /F1 12 Tf 72 704 Td ({$case['after']}) Tj ET";
    $lines = $extractor->extractTextLines($pdfWithContent($content));
    $expectedLines = [$case['before'], $case['after']];
    $plainText = implode("\n", $lines);
    $reviewPlan = $renderer->inlineImageReviewPlan($dictionary, "\x7f");

    $previewFailedClosed = false;
    try {
        $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, "\x7f", [], 1);
    } catch (InvalidArgumentException) {
        $previewFailedClosed = true;
    }

    $payloadExcluded = $lines === $expectedLines
        && !str_contains($plainText, $case['noise'])
        && !str_contains($plainText, 'rawtail')
        && !str_contains($plainText, 'MalformedPreview');

    if (
        !$payloadExcluded
        || !$previewFailedClosed
        || ($reviewPlan['inline_image_dictionary_operand_invalid'] ?? false) !== true
        || ($reviewPlan['inline_image']['dictionary_operand_invalid'] ?? false) !== true
        || ($reviewPlan['inline_image']['native_raster_decode'] ?? true) !== false
        || ($reviewPlan['inline_image_payload_excluded_from_text'] ?? false) !== true
    ) {
        throw new RuntimeException($case['label'] . ' inline image delimiter-tail smoke did not fail closed.');
    }

    $paragraphs = array_merge($paragraphs, $lines);
    $caseMetadata[$case['label']] = [
        'visible_text_imported' => $lines === $expectedLines,
        'payload_excluded' => $payloadExcluded,
        'dictionary_operand_review_only' => $reviewPlan['inline_image_dictionary_operand_invalid'],
        'preview_failed_closed' => $previewFailedClosed,
        'native_raster_decode' => $reviewPlan['inline_image']['native_raster_decode'],
        'paragraphs' => $lines,
    ];
}

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-delimiter-tail-currentbase',
    'upstream_boundary' => 'marker searchable-PDF text extraction keeps inline image payload bytes separate from imported visible text',
    'delimiter_tail_payloads_excluded' => true,
    'delimiter_tail_dictionary_operand_review_only' => true,
    'delimiter_tail_preview_failed_closed' => true,
    'cases' => $caseMetadata,
    'paragraphs' => $paragraphs,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-image-tokenizer-delimiter-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
