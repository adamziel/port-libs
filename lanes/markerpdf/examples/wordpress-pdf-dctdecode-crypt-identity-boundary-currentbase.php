<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Crypt Identity DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Crypt Identity DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Crypt Identity DCT Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0bad segment before false EOI "
    . "\xff\xd9\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "still image bytes before actual boundary \xff\xd9";
$falseEoiEndOffset = strpos($jpegPayload, "\xff\xd9\nendstream\n");
if ($falseEoiEndOffset === false) {
    throw new RuntimeException('Crypt Identity DCT smoke must contain a false EOI before fake endstream.');
}
$staleLength = $falseEoiEndOffset + 2;

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/Crypt /DCTDecode] /DecodeParms [<< /Name /Identity >> null] /Length {$staleLength} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$expected = ['Before Crypt Identity DCT Import', 'After Crypt Identity DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Crypt Identity DCT Leak')
    && !str_contains($plainText, 'bad segment before false EOI')
    && !str_contains($plainText, 'endstream');
$recoveredLength = ($photoReview['raw_length'] ?? null) === strlen($jpegPayload)
    && ($photoReview['raw_length'] ?? 0) > $staleLength;

if ($lines !== $expected || !$payloadExcluded || !$recoveredLength) {
    throw new RuntimeException('Crypt Identity DCTDecode boundary leaked or truncated image payload bytes before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-crypt-identity-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-crypt-identity-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['Crypt', 'DCTDecode'],
    'crypt_filter_name' => 'Identity',
    'false_eoi_before_fake_endstream_ignored' => true,
    'declared_length_stopped_at_false_eoi' => $staleLength,
    'raw_length_after_boundary_recovery' => $photoReview['raw_length'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $photoReview['decoded_with_current_filters'] ?? null,
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
