<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('DCTDecode indirect filter-tail smoke must fit one deflate stored block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before Indirect Filter Tail Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Indirect Filter Tail Import) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0indirect filter tail bytes "
    . 'BT /F1 12 Tf 72 700 Td (WordPress Indirect Filter Tail Payload Leak) Tj ET'
    . "\xff\xd9";
$compressedPayload = $zlibStored($jpegPayload);
$filterObject = '[ /FlateDecode /DCTDecode ] /Crypt';
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter 20 0 R /DecodeParms [null << /ColorTransform 1 >>] /Length ' . strlen($compressedPayload) . ' >>';
$content = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n{$filterObject}\nendobj\n%%EOF";

$renderer = new PdfImageRenderer();
$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, [20 => $filterObject]);

$expected = ['Before Indirect Filter Tail Import', 'After Indirect Filter Tail Import'];
$expectedFilters = ['MalformedFilterOperand', 'FlateDecode', 'DCTDecode'];
$payloadExcluded = !str_contains($plainText, 'WordPress Indirect Filter Tail Payload Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'Crypt');
$filterTailRejected = ($plan['image_filters'] ?? []) === $expectedFilters
    && ($plan['image_filter_boundary']['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null) === 1
    && ($plan['image_filter_boundary']['native_raster_decode'] ?? true) === false
    && (($plan['image_filter_details'][2]['decode_parms']['color_transform'] ?? null) === 1);
$reviewPreserved = ($entry['filters'] ?? []) === $expectedFilters
    && ($entry['preview_only_filters'] ?? []) === ['DCTDecode']
    && ($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($entry['malformed_filter_operand_count'] ?? null) === 1
    && ($entry['native_raster_decode'] ?? true) === false
    && (($entry['filter_details'][2]['decode_parms']['color_transform'] ?? null) === 1);

if ($lines !== $expected || !$payloadExcluded || !$filterTailRejected || !$reviewPreserved) {
    throw new RuntimeException('DCTDecode indirect filter-array tail smoke leaked image bytes or lost review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-indirect-filter-array-tail-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-indirect-filter-array-tail-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'indirect_filter_array_tail_rejected' => true,
    'stream_filters' => $expectedFilters,
    'preview_only_filters' => ['DCTDecode'],
    'filter_operand_policy' => 'reject_malformed_filter_operands',
    'malformed_filter_operand_count' => 1,
    'dctdecode_color_transform' => 1,
    'native_raster_decode' => false,
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
