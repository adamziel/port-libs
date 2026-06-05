<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode renderer smoke payload must fit one stored deflate block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before Renderer DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Renderer DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Renderer DCT Payload Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode renderer smoke must expose a fake endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rawImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length {$fakeTerminatorOffset} >>\nstream\n{$jpegPayload}\nendstream";
$compressedPayload = $zlibStored($jpegPayload);
$fakeCompressedTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
if ($fakeCompressedTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode renderer Flate smoke must expose a fake compressed endstream marker.');
}
$flateImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /Length {$fakeCompressedTerminatorOffset} >>\nstream\n{$compressedPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rawPreview = $renderer->iccBasedImageStreamPreviewRows($rawImage, $rendererObjects);
$flatePreview = $renderer->iccBasedImageStreamPreviewRows($flateImage, $rendererObjects);

$expected = ['Before Renderer DCT Import', 'After Renderer DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Renderer DCT Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$xobjectRecovered = ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && (($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
$rendererRawRecovered = ($rawPreview['image_stream']['raw_length'] ?? null) === strlen($jpegPayload)
    && (($rawPreview['image_stream']['raw_length'] ?? 0) > $fakeTerminatorOffset);
$rendererFlateRecovered = ($flatePreview['image_stream']['raw_length'] ?? null) === strlen($compressedPayload)
    && (($flatePreview['image_stream']['raw_length'] ?? 0) > $fakeCompressedTerminatorOffset);

if (
    $lines !== $expected
    || !$payloadExcluded
    || !$xobjectRecovered
    || !$rendererRawRecovered
    || !$rendererFlateRecovered
    || ($rawPreview['review_only_image_stream'] ?? false) !== true
    || ($flatePreview['review_only_image_stream'] ?? false) !== true
    || ($rawPreview['image_stream']['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($flatePreview['image_stream']['preview_only_filters'] ?? []) !== ['DCTDecode']
) {
    throw new RuntimeException('DCTDecode renderer boundary smoke failed to preserve review-only image payload metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-renderer-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-renderer-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image direct image stream metadata',
    'stream_filters' => ['DCTDecode'],
    'prefix_stream_filters' => ['FlateDecode', 'DCTDecode'],
    'text_paragraphs' => $lines,
    'xobject_raw_length_recovered' => $xobjectRecovered,
    'renderer_raw_dct_length_recovered' => $rendererRawRecovered,
    'renderer_flate_dct_length_recovered' => $rendererFlateRecovered,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'renderer_preview_only_filters' => $rawPreview['image_stream']['preview_only_filters'] ?? [],
    'prefix_preview_only_filters' => $flatePreview['image_stream']['preview_only_filters'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
