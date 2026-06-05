<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$visibleContent = "BT /F1 12 Tf 72 720 Td (Before DCT Alternate Image) Tj T* (After DCT Alternate Image) Tj ET";
$primaryImage = 'primary image review placeholder';
$jpegPayload = "\xff\xd8\xff\xe0\0\x10JFIF\0\x01\x02\0\0\0\0\0\0\0\xff\xd9";
$postEoiSurplus = "\nBT /F1 12 Tf 72 680 Td (WordPress DCT Alternate Image Leak) Tj ET\n";
$alternatePayload = $jpegPayload . $postEoiSurplus;

$pdf = "%PDF-1.4\n"
    . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
    . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
    . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> /XObject << /Photo 5 0 R >> >> /Contents 4 0 R >> endobj\n"
    . "4 0 obj << /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "5 0 obj << /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Alternates [<< /Image 6 0 R /DefaultForPrinting true >>] /Length " . strlen($primaryImage) . " >>\nstream\n{$primaryImage}\nendstream\nendobj\n"
    . "6 0 obj << /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms << /ColorTransform 0 >> /Length " . strlen($alternatePayload) . " >>\nstream\n{$alternatePayload}\nendstream\nendobj\n"
    . "7 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$alternate = $entry['alternate_images'][0] ?? [];

$expectedLines = ['Before DCT Alternate Image', 'After DCT Alternate Image'];
$surplusExcluded = !str_contains($plainText, 'WordPress DCT Alternate Image Leak')
    && !str_contains($plainText, 'JFIF');
$alternateClipped = ($alternate['raw_length'] ?? null) === strlen($jpegPayload)
    && (($alternate['raw_length'] ?? 0) < strlen($alternatePayload));

if (
    $lines !== $expectedLines
    || !$surplusExcluded
    || ($entry['alternate_image_count'] ?? null) !== 1
    || ($entry['alternates_review_only'] ?? false) !== true
    || ($alternate['filters'] ?? []) !== ['DCTDecode']
    || ($alternate['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($alternate['filter_details'][0]['decode_parms']['color_transform'] ?? null) !== 0
    || ($alternate['filter_details'][0]['decode_parms']['valid_color_transform'] ?? false) !== true
    || ($alternate['native_raster_decode'] ?? true) !== false
    || ($alternate['decoded_with_current_filters'] ?? true) !== false
    || !$alternateClipped
) {
    throw new RuntimeException('DCTDecode alternate image boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-alternate-image-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-alternate-image-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only DCT alternate image handoff',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'alternate_image_count' => $entry['alternate_image_count'] ?? null,
    'dct_decodeparms_color_transform' => $alternate['filter_details'][0]['decode_parms']['color_transform'] ?? null,
    'declared_alternate_payload_length' => strlen($alternatePayload),
    'jpeg_eoi_payload_length' => strlen($jpegPayload),
    'alternate_post_eoi_surplus_clipped' => $alternateClipped,
    'alternate_payload_excluded_from_text' => $surplusExcluded,
    'native_raster_decode' => $alternate['native_raster_decode'] ?? null,
    'decoded_with_current_filters' => $alternate['decoded_with_current_filters'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
