<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$lzwPackCodes = static function (array $codes, int $earlyChange = 1): string {
    $dictSize = 258;
    $codeSize = 9;
    $bits = '';
    foreach ($codes as $code) {
        for ($bit = $codeSize - 1; $bit >= 0; $bit--) {
            $bits .= (($code >> $bit) & 1) === 1 ? '1' : '0';
        }
        if ($code === 256) {
            $dictSize = 258;
            $codeSize = 9;
            continue;
        }
        if ($code !== 257) {
            $dictSize++;
            if ($codeSize < 12 && $dictSize + $earlyChange >= (1 << $codeSize)) {
                $codeSize++;
            }
        }
    }

    $out = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $out .= chr(bindec(str_pad(substr($bits, $offset, 8), 8, '0')));
    }

    return $out;
};

$lzwLiteralEncode = static function (string $bytes) use ($lzwPackCodes): string {
    return $lzwPackCodes([
        256,
        ...array_map('ord', str_split($bytes)),
        257,
    ]);
};

$before = 'BT /F1 12 Tf 72 720 Td (Before LZW DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After LZW DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress LZW DCT Payload Leak) Tj ET';
$incompleteJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete";
$completeJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9";
$encodedPayload = $lzwLiteralEncode($incompleteJpeg)
    . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . $lzwLiteralEncode($completeJpeg);
$fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode LZW-prefix smoke must expose a fake endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/LZWDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter [/LZWDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$payloadExcluded = !str_contains($plainText, 'WordPress LZW DCT Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$expected = ['Before LZW DCT Import', 'After LZW DCT Import'];
$lzwBoundaryHeld = $lines === $expected
    && $payloadExcluded
    && ($entry['filters'] ?? null) === ['LZWDecode', 'DCTDecode']
    && ($entry['preview_only_filters'] ?? null) === ['DCTDecode']
    && ($entry['raw_length'] ?? null) === strlen($encodedPayload)
    && ($entry['native_raster_decode'] ?? true) === false
    && ($entry['payload_in_visible_text'] ?? true) === false
    && ($rendererPreview['image_stream']['raw_length'] ?? null) === strlen($encodedPayload)
    && ($rendererPreview['image_stream']['preview_only_filters'] ?? null) === ['DCTDecode'];

if (!$lzwBoundaryHeld) {
    throw new RuntimeException('LZW-wrapped DCTDecode image payload leaked into WordPress text or review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-lzw-prefix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-lzw-prefix-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['LZWDecode', 'DCTDecode'],
    'lzw_prefix_eod_decoy_rejected' => true,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'image_xobject_raw_length' => $entry['raw_length'] ?? null,
    'renderer_raw_length' => $rendererPreview['image_stream']['raw_length'] ?? null,
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
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
