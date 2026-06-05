<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before RunLength DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After RunLength DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress RunLength DCT Leak) Tj ET';
$encodedPayload = $runLengthEncode("\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete")
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $runLengthEncode("\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9");
$fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('RunLength DCT smoke must contain a fake early EOD endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/RunLengthDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress RunLength DCT Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$recoveredLength = ($photoReview['raw_length'] ?? null) === strlen($encodedPayload)
    && ($photoReview['raw_length'] ?? 0) > $fakeTerminatorOffset;

if ($lines !== ['Before RunLength DCT Import', 'After RunLength DCT Import'] || !$payloadExcluded || !$recoveredLength) {
    throw new RuntimeException('RunLengthDecode prefix DCTDecode boundary leaked JPEG payload bytes into WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-runlength-prefix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-runlength-prefix-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['RunLengthDecode', 'DCTDecode'],
    'runlength_eod_before_incomplete_jpeg_rejected' => true,
    'stale_length_recovered_to_complete_dct_payload' => $recoveredLength,
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
