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

$dictionary = '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] .5 /F /MalformedPreview';
$payload = "\x7f EI BT /F1 12 Tf 72 690 Td (Dot Tail Inline Noise) Tj ET rawtail";
$content = "BT /F1 12 Tf 72 720 Td (Before Dot Tail Inline) Tj ET\n"
    . "BI {$dictionary} ID\n{$payload}\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Dot Tail Inline) Tj ET";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$expectedLines = [
    'Before Dot Tail Inline',
    'After Dot Tail Inline',
];
$reviewPlan = $renderer->inlineImageReviewPlan($dictionary, "\x7f");

$previewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, "\x7f", [], 1);
} catch (InvalidArgumentException) {
    $previewFailedClosed = true;
}

$payloadExcluded = $lines === $expectedLines
    && !str_contains($plainText, 'Dot Tail Inline Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, '.5 /F')
    && !str_contains($plainText, 'MalformedPreview');

if (
    !$payloadExcluded
    || !$previewFailedClosed
    || ($reviewPlan['inline_image_dictionary_operand_invalid'] ?? false) !== true
    || ($reviewPlan['inline_image']['dictionary_operand_invalid'] ?? false) !== true
    || ($reviewPlan['inline_image']['native_raster_decode'] ?? true) !== false
    || ($reviewPlan['inline_image_payload_excluded_from_text'] ?? false) !== true
) {
    throw new RuntimeException('Dot-leading inline image dictionary tail smoke did not fail closed.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-dot-numeric-tail-currentbase',
    'upstream_boundary' => 'marker searchable-PDF text extraction keeps inline image payloads separate from imported visible text',
    'visible_text_imported' => $lines === $expectedLines,
    'dot_numeric_tail_payload_excluded' => $payloadExcluded,
    'dot_numeric_tail_dictionary_operand_review_only' => $reviewPlan['inline_image_dictionary_operand_invalid'],
    'dot_numeric_tail_preview_failed_closed' => $previewFailedClosed,
    'native_raster_decode' => $reviewPlan['inline_image']['native_raster_decode'],
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-image-tokenizer-dot-numeric-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
