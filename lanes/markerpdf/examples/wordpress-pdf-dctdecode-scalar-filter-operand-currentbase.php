<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Scalar DCT Filter Operand Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Scalar DCT Filter Operand Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Scalar DCT Filter Operand Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0scalar-filter operand bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode scalar filter operand smoke must expose a fake endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Crypt null /Length {$fakeTerminatorOffset} >>";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);

$payloadExcluded = !str_contains($plainText, 'WordPress Scalar DCT Filter Operand Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$malformedRejected = ($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($entry['filters'] ?? []) === ['MalformedFilterOperand', 'DCTDecode']
    && ($entry['preview_only_filters'] ?? []) === ['DCTDecode']
    && ($entry['raw_dct_preview_boundary'] ?? false) === true
    && ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && ($entry['decoded_with_current_filters'] ?? true) === false
    && ($entry['native_raster_decode'] ?? true) === false;
$rendererAligned = ($plan['image_filters'] ?? []) === ['MalformedFilterOperand', 'DCTDecode']
    && ($plan['image_filter_boundary']['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($plan['image_filter_boundary']['native_raster_decode'] ?? true) === false;

if (
    $lines !== ['Before Scalar DCT Filter Operand Import', 'After Scalar DCT Filter Operand Import']
    || !$payloadExcluded
    || !$malformedRejected
    || !$rendererAligned
) {
    throw new RuntimeException('DCTDecode scalar filter operand boundary leaked payload bytes or allowed native raster decode.');
}

echo '<!-- markerpdf:pdf-dctdecode-scalar-filter-operand-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters_resolved' => $entry['filters_resolved'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'malformed_filter_operand_count' => $entry['malformed_filter_operand_count'] ?? null,
    'raw_dct_preview_boundary' => $entry['raw_dct_preview_boundary'] ?? false,
    'stale_length_fake_endstream_rejected' => ($entry['raw_length'] ?? 0) > $fakeTerminatorOffset,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'renderer_boundary_matches_xobject' => $rendererAligned,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
