<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode native-prefix post-EOI smoke payload must fit one stored deflate block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before Prefix Post EOI DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Prefix Post EOI DCT Import) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0wordpress-prefix-post-eoi\xff\xd9";
$postEoiSurplus = "\nBT /F1 12 Tf 72 700 Td (WordPress Prefix Post EOI DCT Leak) Tj ET\n";
$decodedPayload = $jpegPayload . $postEoiSurplus;
$encodedPayload = $zlibStored($decodedPayload);
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/FlateDecode /DCTDecode] /Length ' . strlen($encodedPayload) . ' >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = str_replace('/DeviceRGB', '30 0 R', $imageDictionary)
    . "\nstream\n{$encodedPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['dctdecode_stream_boundary'] ?? []) : [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$rendererStream = $rendererPreview['image_stream'] ?? [];
$rendererBoundary = is_array($rendererStream) ? ($rendererStream['dctdecode_stream_boundary'] ?? []) : [];

$expected = ['Before Prefix Post EOI DCT Import', 'After Prefix Post EOI DCT Import'];
$surplusExcluded = !str_contains($plainText, 'WordPress Prefix Post EOI DCT Leak')
    && !str_contains($plainText, 'JFIF');
$xobjectClipped = ($boundary['source'] ?? null) === 'dctdecode_jpeg_marker_boundary'
    && ($boundary['review_stream_length'] ?? null) === strlen($jpegPayload)
    && ($entry['native_prefix_decoded_length'] ?? null) === strlen($decodedPayload);
$rendererClipped = ($rendererBoundary['source'] ?? null) === 'dctdecode_jpeg_marker_boundary'
    && ($rendererBoundary['review_stream_length'] ?? null) === strlen($jpegPayload)
    && ($rendererStream['native_prefix_decoded_length'] ?? null) === strlen($decodedPayload);

if (
    $lines !== $expected
    || !$surplusExcluded
    || !$xobjectClipped
    || !$rendererClipped
    || ($entry['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($rendererStream['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($rendererStream['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode native-prefix post-EOI boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-native-prefix-post-eoi-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-native-prefix-post-eoi-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only JPEG payload after native prefix filters',
    'stream_filters' => ['FlateDecode', 'DCTDecode'],
    'paragraphs' => $lines,
    'encoded_payload_length' => strlen($encodedPayload),
    'native_prefix_decoded_length' => strlen($decodedPayload),
    'jpeg_eoi_payload_length' => strlen($jpegPayload),
    'xobject_prefix_post_eoi_surplus_clipped' => $xobjectClipped,
    'renderer_prefix_post_eoi_surplus_clipped' => $rendererClipped,
    'post_eoi_surplus_excluded_from_text' => $surplusExcluded,
    'review_stream_decoded_from_native_prefix' => ($boundary['review_stream_decoded_from_native_prefix'] ?? false) === true,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
