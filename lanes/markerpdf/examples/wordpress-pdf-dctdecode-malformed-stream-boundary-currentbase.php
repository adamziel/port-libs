<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Malformed DCT Import) Tj ET';
$between = 'BT /F1 12 Tf 72 700 Td (Between Malformed DCT Imports) Tj ET';
$after = 'BT /F1 12 Tf 72 660 Td (After Malformed DCT Import) Tj ET';
$noSoiPayload = 'not a jpeg BT /F1 12 Tf 72 690 Td (WordPress Malformed DCT No SOI Leak) Tj ET';
$noEoiPayload = "\xff\xd8\xff\xe0JFIF\0 incomplete BT /F1 12 Tf 72 680 Td (WordPress Malformed DCT No EOI Leak) Tj ET";
$content = $before . "\n"
    . "q 24 0 0 24 72 680 cm /NoSoi Do Q\n"
    . $between . "\n"
    . "q 24 0 0 24 104 680 cm /NoEoi Do Q\n"
    . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /NoSoi 5 0 R /NoEoi 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$noSoiPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$noEoiPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode >>\nstream\n{$noEoiPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$expected = [
    'Before Malformed DCT Import',
    'Between Malformed DCT Imports',
    'After Malformed DCT Import',
];
$payloadExcluded = !str_contains($plainText, 'WordPress Malformed DCT No SOI Leak')
    && !str_contains($plainText, 'WordPress Malformed DCT No EOI Leak')
    && !str_contains($plainText, 'JFIF');
$noSoiBoundary = $entriesByName['NoSoi']['dctdecode_stream_boundary'] ?? [];
$noEoiBoundary = $entriesByName['NoEoi']['dctdecode_stream_boundary'] ?? [];
$rendererBoundary = $rendererPreview['image_stream']['dctdecode_stream_boundary'] ?? [];

if (
    $lines !== $expected
    || !$payloadExcluded
    || ($noSoiBoundary['source'] ?? null) !== 'dctdecode_jpeg_marker_boundary_unverified'
    || ($noSoiBoundary['invalid_reason'] ?? null) !== 'missing_jpeg_soi'
    || ($noEoiBoundary['invalid_reason'] ?? null) !== 'missing_jpeg_eoi'
    || ($rendererBoundary['invalid_reason'] ?? null) !== 'missing_jpeg_eoi'
    || ($noSoiBoundary['native_raster_decode'] ?? true) !== false
    || ($noEoiBoundary['native_raster_decode'] ?? true) !== false
    || ($rendererPreview['image_stream']['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('Malformed DCTDecode stream boundary leaked image bytes or omitted fail-closed review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-malformed-stream-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-malformed-stream-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image review-only image payloads',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'malformed_dct_no_soi_invalid_reason' => $noSoiBoundary['invalid_reason'] ?? null,
    'malformed_dct_no_eoi_invalid_reason' => $noEoiBoundary['invalid_reason'] ?? null,
    'renderer_invalid_reason' => $rendererBoundary['invalid_reason'] ?? null,
    'valid_jpeg_marker_boundary' => false,
    'dctdecode_payload_excluded_from_text' => $payloadExcluded,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
