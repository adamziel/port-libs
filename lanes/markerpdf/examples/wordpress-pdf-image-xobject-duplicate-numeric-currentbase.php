<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Attachment duplicate numeric intro) Tj ET '
    . 'q 12 0 0 6 72 690 cm /Duplicate#20Width#20Image Do Q '
    . 'q 10 0 0 5 100 690 cm /Duplicate#20Height#20Image Do Q '
    . 'q 8 0 0 4 126 690 cm /Duplicate#20Bpc#20Image Do Q '
    . 'BT /F1 12 Tf 72 660 Td (Attachment duplicate numeric outro) Tj ET';
$duplicateWidthPayload = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate width image payload noise) Tj ET';
$duplicateHeightPayload = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate height image payload noise) Tj ET';
$duplicateBpcPayload = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate BPC image payload noise) Tj ET';
$duplicateWidthCompressed = gzcompress($duplicateWidthPayload);
$duplicateHeightCompressed = gzcompress($duplicateHeightPayload);
$duplicateBpcCompressed = gzcompress($duplicateBpcPayload);
if (!is_string($duplicateWidthCompressed) || !is_string($duplicateHeightCompressed) || !is_string($duplicateBpcCompressed)) {
    throw new RuntimeException('Unable to compress duplicate numeric Image XObject smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 800] /Resources << /Font << /F1 4 0 R >> /XObject << /Duplicate#20Width#20Image 5 0 R /Duplicate#20Height#20Image 6 0 R /Duplicate#20Bpc#20Image 7 0 R >> >> /Contents 8 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Width 4 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($duplicateWidthCompressed) . " >>\nstream\n{$duplicateWidthCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /Height 3 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($duplicateHeightCompressed) . " >>\nstream\n{$duplicateHeightCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /BitsPerComponent 1 /Filter /FlateDecode /Length " . strlen($duplicateBpcCompressed) . " >>\nstream\n{$duplicateBpcCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "xref\n0 9\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$duplicateWidth = $entriesByName['Duplicate Width Image'] ?? [];
$duplicateHeight = $entriesByName['Duplicate Height Image'] ?? [];
$duplicateBpc = $entriesByName['Duplicate Bpc Image'] ?? [];
if (
    $plainText !== "Attachment duplicate numeric intro\nAttachment duplicate numeric outro"
    || ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 3
    || ($duplicateWidth['native_raster_decode'] ?? true) !== false
    || ($duplicateWidth['image_dimension_boundary']['width_operand_boundary']['reason'] ?? null) !== 'duplicate_top_level_declaration'
    || ($duplicateHeight['native_raster_decode'] ?? true) !== false
    || ($duplicateHeight['image_dimension_boundary']['height_operand_boundary']['reason'] ?? null) !== 'duplicate_top_level_declaration'
    || ($duplicateBpc['native_raster_decode'] ?? true) !== false
    || ($duplicateBpc['bits_per_component_boundary']['reason'] ?? null) !== 'duplicate_top_level_declaration'
    || str_contains($plainText, 'WordPress duplicate width image payload noise')
    || str_contains($plainText, 'WordPress duplicate height image payload noise')
    || str_contains($plainText, 'WordPress duplicate BPC image payload noise')
) {
    throw new RuntimeException('Image XObject duplicate numeric operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-duplicate-numeric-currentbase',
    'wordpress_path' => 'searchable PDF attachment import keeps visible text clean while duplicate Image XObject numeric operands stay review-only',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image RGB handoff; native raster handoff requires unambiguous image dimensions and bits-per-component',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'plain_text' => $plainText,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'duplicate_width_native_raster_decode' => $duplicateWidth['native_raster_decode'],
    'duplicate_width_reason' => $duplicateWidth['image_dimension_boundary']['width_operand_boundary']['reason'],
    'duplicate_height_native_raster_decode' => $duplicateHeight['native_raster_decode'],
    'duplicate_height_reason' => $duplicateHeight['image_dimension_boundary']['height_operand_boundary']['reason'],
    'duplicate_bpc_native_raster_decode' => $duplicateBpc['native_raster_decode'],
    'duplicate_bpc_reason' => $duplicateBpc['bits_per_component_boundary']['reason'],
    'payload_in_visible_text' => str_contains($plainText, 'WordPress duplicate width image payload noise')
        || str_contains($plainText, 'WordPress duplicate height image payload noise')
        || str_contains($plainText, 'WordPress duplicate BPC image payload noise'),
];

echo '<!-- markerpdf-image-xobject-duplicate-numeric-currentbase: ok -->' . PHP_EOL;
echo '<pre>' . htmlspecialchars(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES, 'UTF-8') . '</pre>' . PHP_EOL;
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
