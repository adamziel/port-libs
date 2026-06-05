<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Indirect DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Indirect DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Indirect DCT Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('Indirect DCT filter smoke must expose a fake endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 6 0 R /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "6 0 obj\n/DCTDecode\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress Indirect DCT Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$recoveredLength = ($photoReview['raw_length'] ?? null) === strlen($jpegPayload)
    && ($photoReview['raw_length'] ?? 0) > $fakeTerminatorOffset;

if ($lines !== ['Before Indirect DCT Import', 'After Indirect DCT Import'] || !$payloadExcluded || !$recoveredLength) {
    throw new RuntimeException('Indirect DCTDecode filter boundary leaked JPEG payload bytes into WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-indirect-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-indirect-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['DCTDecode'],
    'filter_helper_after_stream' => true,
    'raw_jpeg_boundary_used_for_preliminary_owner_scan' => true,
    'stale_length_fake_endstream_rejected' => true,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_raw_length_recovered' => $recoveredLength,
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
