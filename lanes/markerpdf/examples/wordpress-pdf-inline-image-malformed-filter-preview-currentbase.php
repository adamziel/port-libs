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

$renderer = new PdfImageRenderer();
$malformedDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] /D [0 1]';
$unresolvedDictionary = '/W 2 /H 1 /CS [/I /RGB 1 91 0 R] /BPC 1 /F 99 0 R /D [0 1]';
$indexedObjects = [
    91 => '<000000FFFFFF>',
];

$malformedPlan = $renderer->inlineImageReviewPlan($malformedDictionary, 'ABC');
$unresolvedPlan = $renderer->inlineImageReviewPlan($unresolvedDictionary, "\x80", $indexedObjects);

$malformedPreviewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($malformedDictionary, 'ABC', [], 3);
} catch (InvalidArgumentException) {
    $malformedPreviewFailedClosed = true;
}

$unresolvedPreviewFailedClosed = false;
try {
    $renderer->inlineIndexedImageStreamPreviewRows($unresolvedDictionary, "\x80", $indexedObjects, 2);
} catch (InvalidArgumentException) {
    $unresolvedPreviewFailedClosed = true;
}

$content = "BT /F1 12 Tf 72 720 Td (Before Inline Filter Preview) Tj ET\n"
    . "BI {$malformedDictionary} ID\nABC EI BT /F1 12 Tf 72 700 Td (Malformed Preview Leak) Tj ET rawtail\nEI\n"
    . "BI /W 2 /H 1 /CS /G /BPC 8 /F 99 0 R /D [0 1] ID\nXY EI BT /F1 12 Tf 72 690 Td (Unresolved Preview Leak) Tj ET rawtail\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After Inline Filter Preview) Tj ET";
$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$expectedLines = [
    'Before Inline Filter Preview',
    'After Inline Filter Preview',
];

$payloadExcluded = $lines === $expectedLines
    && !str_contains($plainText, 'Malformed Preview Leak')
    && !str_contains($plainText, 'Unresolved Preview Leak')
    && !str_contains($plainText, 'ABC EI')
    && !str_contains($plainText, 'XY EI');

if (
    !$malformedPreviewFailedClosed
    || !$unresolvedPreviewFailedClosed
    || !$payloadExcluded
    || ($malformedPlan['image_filters'] ?? []) !== ['MalformedFilterOperand']
    || ($unresolvedPlan['image_filters'] ?? []) !== ['UnresolvedFilterOperand']
    || ($malformedPlan['inline_image']['native_raster_decode'] ?? true) !== false
    || ($unresolvedPlan['inline_image']['native_raster_decode'] ?? true) !== false
) {
    throw new RuntimeException('Malformed inline image filter preview smoke did not fail closed.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-malformed-filter-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'malformed_inline_filter_preview_failed_closed' => $malformedPreviewFailedClosed,
    'unresolved_inline_filter_preview_failed_closed' => $unresolvedPreviewFailedClosed,
    'malformed_inline_filter_operand_recorded' => $malformedPlan['image_filters'],
    'unresolved_inline_filter_operand_recorded' => $unresolvedPlan['image_filters'],
    'malformed_native_raster_decode' => $malformedPlan['inline_image']['native_raster_decode'],
    'unresolved_native_raster_decode' => $unresolvedPlan['inline_image']['native_raster_decode'],
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-image-malformed-filter-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
