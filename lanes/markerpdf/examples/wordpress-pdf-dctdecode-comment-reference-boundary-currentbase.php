<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Comment DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Comment DCT Import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Comment DCT Payload Leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0JPEG bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('DCTDecode comment-reference smoke must expose a fake endstream marker.');
}

$filterReference = "10 % filter reference comment\n 0 R";
$decodeParmsReference = "11 % decodeparms reference comment\n 0 R";
$content = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterReference} /DecodeParms {$decodeParmsReference} /Length {$fakeTerminatorOffset} >>";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 20 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n/DCTDecode\nendobj\n"
    . "11 0 obj\n<< /ColorTransform 1 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$renderer = new PdfImageRenderer();
$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    "<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$filterReference} /DecodeParms {$decodeParmsReference} >>",
    [
        10 => '/DCTDecode',
        11 => '<< /ColorTransform 1 >>',
    ]
);

$expected = ['Before Comment DCT Import', 'After Comment DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Comment DCT Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$referencesResolved = ($plan['image_filters'] ?? []) === ['DCTDecode']
    && (($plan['image_filter_details'][0]['decode_parms']['color_transform'] ?? null) === 1);
$boundaryRecovered = ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && (($entry['raw_length'] ?? 0) > $fakeTerminatorOffset)
    && ($entry['filters'] ?? []) === ['DCTDecode']
    && ($entry['preview_only_filters'] ?? []) === ['DCTDecode']
    && (($entry['filter_details'][0]['decode_parms']['color_transform'] ?? null) === 1)
    && ($entry['native_raster_decode'] ?? true) === false;

if ($lines !== $expected || !$payloadExcluded || !$referencesResolved || !$boundaryRecovered) {
    throw new RuntimeException('DCTDecode comment-reference boundary leaked image bytes or lost review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-comment-reference-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-comment-reference-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'filter_reference_comments_resolved' => true,
    'decodeparms_reference_comments_resolved' => true,
    'stream_filters' => ['DCTDecode'],
    'dctdecode_color_transform' => 1,
    'raw_length_after_boundary_recovery' => $entry['raw_length'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
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
