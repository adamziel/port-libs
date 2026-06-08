<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before incomplete ASCIIHex DCT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After incomplete ASCIIHex DCT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Incomplete ASCIIHex DCT import leak) Tj ET';
$incompleteJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete";
$encodedPayload = strtoupper(bin2hex($incompleteJpeg))
    . ">\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n";
$fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('Focused ASCIIHex DCT import fixture must expose a fake early endstream marker.');
}

$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$rendererStream = $preview['image_stream'] ?? [];
$boundary = $entry['dctdecode_stream_boundary'] ?? [];

$expectedLines = [
    'Before incomplete ASCIIHex DCT import',
    'After incomplete ASCIIHex DCT import',
];
$payloadExcluded = !str_contains($plainText, 'Incomplete ASCIIHex DCT import leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$xobjectRecovered = ($entry['raw_length'] ?? null) === strlen($encodedPayload)
    && ($entry['native_prefix_decoded'] ?? null) === true
    && ($entry['native_prefix_decoded_length'] ?? null) === strlen($incompleteJpeg)
    && ($entry['payload_in_visible_text'] ?? null) === false
    && ($boundary['invalid_reason'] ?? null) === 'missing_jpeg_eoi';
$rendererRecovered = ($preview['review_only_image_stream'] ?? null) === true
    && ($rendererStream['raw_length'] ?? null) === strlen($encodedPayload)
    && ($rendererStream['native_prefix_decoded_length'] ?? null) === strlen($incompleteJpeg)
    && ($rendererStream['decoded_with_current_filters'] ?? null) === false;

if ($lines !== $expectedLines || !$payloadExcluded || !$xobjectRecovered || !$rendererRecovered) {
    throw new RuntimeException('Incomplete ASCIIHex DCTDecode prefix boundary did not preserve WordPress-safe text import.');
}

echo '<!-- markerpdf:pdf-dctdecode-asciihex-incomplete-prefix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-asciihex-incomplete-prefix-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks image payload exclusion + native PDF image review handoff',
    'stream_filters' => ['ASCIIHexDecode', 'DCTDecode'],
    'xobject_raw_length' => $entry['raw_length'] ?? null,
    'fake_terminator_offset' => $fakeTerminatorOffset,
    'native_prefix_decoded_length' => $entry['native_prefix_decoded_length'] ?? null,
    'dctdecode_invalid_reason' => $boundary['invalid_reason'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'renderer_preview_only_filters' => $rendererStream['preview_only_filters'] ?? [],
    'renderer_decoded_with_current_filters' => $rendererStream['decoded_with_current_filters'] ?? null,
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
