<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 740 Td (Attachment intro) Tj ET '
    . 'q 12 0 0 6 72 700 cm /Zero#20Width Do Q '
    . 'q 10 0 0 5 96 700 cm /Decimal#20Height Do Q '
    . 'BT /F1 12 Tf 72 660 Td (Attachment outro) Tj ET';
$zeroWidthPayload = 'BT /F1 12 Tf 72 720 Td (WordPress zero-width image payload noise) Tj ET';
$decimalHeightPayload = 'BT /F1 12 Tf 72 720 Td (WordPress decimal-height image payload noise) Tj ET';
$compressedZeroWidthPayload = gzcompress($zeroWidthPayload);
$compressedDecimalHeightPayload = gzcompress($decimalHeightPayload);
if (!is_string($compressedZeroWidthPayload) || !is_string($compressedDecimalHeightPayload)) {
    throw new RuntimeException('Unable to compress malformed dimension image payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Zero#20Width 5 0 R /Decimal#20Height 6 0 R >> >> /Contents 7 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 0 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedZeroWidthPayload) . " >>\nstream\n{$compressedZeroWidthPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1.5 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedDecimalHeightPayload) . " >>\nstream\n{$compressedDecimalHeightPayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "xref\n0 8\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$zeroWidth = $entriesByName['Zero Width'] ?? [];
$decimalHeight = $entriesByName['Decimal Height'] ?? [];
if (
    $plainText !== "Attachment intro\nAttachment outro"
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($zeroWidth['image_dimensions_valid'] ?? true) !== false
    || ($zeroWidth['native_raster_decode'] ?? true) !== false
    || ($zeroWidth['image_dimension_boundary']['width_positive'] ?? true) !== false
    || ($zeroWidth['image_dimension_boundary']['height_positive'] ?? false) !== true
    || ($zeroWidth['decoded_sha256'] ?? null) !== hash('sha256', $zeroWidthPayload)
    || ($decimalHeight['image_dimensions_valid'] ?? true) !== false
    || ($decimalHeight['native_raster_decode'] ?? true) !== false
    || ($decimalHeight['image_dimension_boundary']['height_integer'] ?? true) !== false
    || ($decimalHeight['image_dimension_boundary']['height'] ?? null) !== 1.5
    || ($decimalHeight['decoded_sha256'] ?? null) !== hash('sha256', $decimalHeightPayload)
    || str_contains($plainText, 'WordPress zero-width image payload noise')
    || str_contains($plainText, 'WordPress decimal-height image payload noise')
) {
    throw new RuntimeException('Image XObject invalid dimensions boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-invalid-dimensions-currentbase',
    'wordpress_path' => 'searchable PDF attachment import keeps visible text clean while malformed Image XObject dimensions stay review-only',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image RGB handoff; native raster handoff requires positive integer image dimensions',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'plain_text' => $plainText,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'zero_width_native_raster_decode' => $zeroWidth['native_raster_decode'],
    'zero_width_boundary' => $zeroWidth['image_dimension_boundary'],
    'decimal_height_native_raster_decode' => $decimalHeight['native_raster_decode'],
    'decimal_height_boundary' => $decimalHeight['image_dimension_boundary'],
    'payload_in_visible_text' => str_contains($plainText, 'WordPress zero-width image payload noise')
        || str_contains($plainText, 'WordPress decimal-height image payload noise'),
];

echo '<!-- markerpdf-image-xobject-invalid-dimensions-currentbase: ok -->' . PHP_EOL;
echo '<pre>' . htmlspecialchars(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES, 'UTF-8') . '</pre>' . PHP_EOL;
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
