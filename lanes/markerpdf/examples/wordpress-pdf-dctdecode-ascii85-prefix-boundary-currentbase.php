<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ascii85Encode = static function (string $bytes): string {
    $encoded = '<~';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $padding = 4 - strlen($chunk);
        $padded = $chunk . str_repeat("\0", $padding);
        $value = 0;
        for ($index = 0; $index < 4; $index++) {
            $value = ($value << 8) | ord($padded[$index]);
        }

        if ($value === 0 && $padding === 0) {
            $encoded .= 'z';
            continue;
        }

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($digits, 0, 5 - $padding);
    }

    return $encoded . '~>';
};

$before = 'BT /F1 12 Tf 72 720 Td (Before ASCII85 DCT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After ASCII85 DCT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress ASCII85 DCT prefix leak) Tj ET';
$incompleteJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete";
$completeJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9";
$encodedPayload = $ascii85Encode($incompleteJpeg)
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $ascii85Encode($completeJpeg);
$staleTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('ASCII85 DCT smoke must expose a stale endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCII85Decode /DCTDecode] /Length {$staleTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter [/ASCII85Decode /DCTDecode] /Length {$staleTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$rendererStream = $rendererPreview['image_stream'] ?? [];

if (
    $plainText !== "Before ASCII85 DCT import\nAfter ASCII85 DCT import"
    || str_contains($plainText, 'WordPress ASCII85 DCT prefix leak')
    || ($entry['raw_length'] ?? null) !== strlen($encodedPayload)
    || ($rendererStream['raw_length'] ?? null) !== strlen($encodedPayload)
    || ($rendererStream['native_prefix_decoded'] ?? false) !== true
    || ($entry['decoded_with_current_filters'] ?? true) !== false
    || ($entry['native_raster_decode'] ?? true) !== false
) {
    throw new RuntimeException('ASCII85-prefixed DCT boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-dctdecode-ascii85-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'raw_length' => $entry['raw_length'] ?? null,
    'stale_terminator_offset' => $staleTerminatorOffset,
    'renderer_raw_length' => $rendererStream['raw_length'] ?? null,
    'renderer_native_prefix_decoded' => $rendererStream['native_prefix_decoded'] ?? null,
    'renderer_stopped_before_filter' => $rendererStream['stopped_before_filter'] ?? null,
    'ascii85_member_eod_ignored_until_dct_boundary' => ($entry['raw_length'] ?? null) === strlen($encodedPayload),
    'direct_renderer_payload_repaired_to_later_jpeg_boundary' => ($rendererStream['raw_length'] ?? null) === strlen($encodedPayload),
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'WordPress ASCII85 DCT prefix leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress ASCII85 DCT prefix leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-dctdecode-ascii85-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
