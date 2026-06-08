<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$decodeParmsObject = '[<< /ColorTransform 1 >>] << /ColorTransform 0 >>';
$before = 'BT /F1 12 Tf 72 720 Td (Before DCT array-tail DecodeParms import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After DCT array-tail DecodeParms import) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0array-tail DecodeParms review bytes "
    . 'BT /F1 12 Tf 72 700 Td (WordPress DCT array-tail DecodeParms payload leak) Tj ET'
    . "\xff\xd9";
$imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /DecodeParms 20 0 R /Length ' . strlen($jpegPayload) . ' >>';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n{$decodeParmsObject}\nendobj\n%%EOF";

$objects = [20 => $decodeParmsObject];
$renderer = new PdfImageRenderer();
$extractor = new PdfTextExtractor();
$rendererPlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload, $objects);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererDecodeParms = $rendererPlan['image_filter_details'][0]['decode_parms'] ?? [];
$xobjectDecodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
$expected = ['Before DCT array-tail DecodeParms import', 'After DCT array-tail DecodeParms import'];
$reviewJson = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$payloadExcluded = !str_contains($plainText, 'WordPress DCT array-tail DecodeParms payload leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($reviewJson, 'WordPress DCT array-tail DecodeParms payload leak');
$arrayTailReviewed = ($rendererDecodeParms['decode_parms_review'] ?? null) === 'malformed_dctdecode_decodeparms_fail_closed'
    && ($rendererDecodeParms['decode_parms_operand_detail'] ?? null) === 'array_with_trailing_operands'
    && ($rendererDecodeParms['decode_parms_array_policy'] ?? null) === 'reject_top_level_decodeparms_array_tail'
    && $rendererDecodeParms === $xobjectDecodeParms
    && ($colorPlan['decode_parms_color_transform_valid'] ?? null) === false
    && ($colorPlan['decode_parms_color_transform_ignored'] ?? null) === true
    && ($colorPlan['effective_color_transform'] ?? null) === 0;

if ($lines !== $expected || !$payloadExcluded || !$arrayTailReviewed) {
    throw new RuntimeException('DCTDecode indirect DecodeParms array-tail boundary was not failed closed before WordPress import.');
}

$metadata = [
    'source' => 'native-pdf-dctdecode-indirect-decodeparms-array-tail-currentbase',
    'upstream_boundary' => 'marker.pdf image extraction keeps DCTDecode JPEG payload review-only while malformed DecodeParms operands fail closed',
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'renderer_decode_parms_review' => $rendererDecodeParms['decode_parms_review'] ?? null,
    'xobject_decode_parms_review' => $xobjectDecodeParms['decode_parms_review'] ?? null,
    'decode_parms_operand_detail' => $xobjectDecodeParms['decode_parms_operand_detail'] ?? null,
    'decode_parms_array_policy' => $xobjectDecodeParms['decode_parms_array_policy'] ?? null,
    'decode_parms_color_transform_valid' => $colorPlan['decode_parms_color_transform_valid'] ?? null,
    'decode_parms_color_transform_ignored' => $colorPlan['decode_parms_color_transform_ignored'] ?? null,
    'effective_color_transform' => $colorPlan['effective_color_transform'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'payload_excluded_from_text' => !str_contains($plainText, 'WordPress DCT array-tail DecodeParms payload leak'),
    'payload_excluded_from_review' => !str_contains($reviewJson, 'WordPress DCT array-tail DecodeParms payload leak'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-dctdecode-indirect-decodeparms-array-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
