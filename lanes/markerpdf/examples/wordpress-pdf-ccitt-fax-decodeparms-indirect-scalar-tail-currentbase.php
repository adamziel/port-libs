<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before CCITT scalar tail import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After CCITT scalar tail import) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 700 Td (WordPress CCITT scalar tail payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /ScalarTailFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 14 0 R /EncodedByteAlign 15 0 R /EndOfLine 16 0 R /EndOfBlock 17 0 R /DamagedRowsBeforeError 18 0 R >> /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n-1 /BadK\nendobj\n"
    . "12 0 obj\n16 /BadColumns\nendobj\n"
    . "13 0 obj\n1 /BadRows\nendobj\n"
    . "14 0 obj\ntrue /BadBlackIs1\nendobj\n"
    . "15 0 obj\ntrue /BadEncodedByteAlign\nendobj\n"
    . "16 0 obj\nfalse /BadEndOfLine\nendobj\n"
    . "17 0 obj\nfalse /BadEndOfBlock\nendobj\n"
    . "18 0 obj\n0 /BadDamagedRowsBeforeError\nendobj\n%%EOF";

$invalidFields = [
    'k',
    'columns',
    'rows',
    'black_is_1',
    'encoded_byte_align',
    'end_of_line',
    'end_of_block',
    'damaged_rows_before_error',
];

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$decodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
$boundary = $entry['ccitt_fax_decode_boundary'] ?? [];

$renderer = new PdfImageRenderer();
$inlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline WordPress CCITT scalar tail payload noise) Tj ET final";
$inlineReview = $renderer->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP << /K 11 0 R /Columns 12 0 R /Rows 13 0 R /BlackIs1 14 0 R /EncodedByteAlign 15 0 R /EndOfLine 16 0 R /EndOfBlock 17 0 R /DamagedRowsBeforeError 18 0 R >> /D [1 0]',
    $inlinePayload,
    [
        11 => '-1 /BadK',
        12 => '16 /BadColumns',
        13 => '1 /BadRows',
        14 => 'true /BadBlackIs1',
        15 => 'true /BadEncodedByteAlign',
        16 => 'false /BadEndOfLine',
        17 => 'false /BadEndOfBlock',
        18 => '0 /BadDamagedRowsBeforeError',
    ]
);
$inlineDecodeParms = $inlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$inlineBoundary = $inlineReview['ccitt_fax_decode_boundary'] ?? [];

if (
    ($decodeParms['decode_parms_review'] ?? null) !== 'invalid_ccitt_decodeparms_fail_closed'
    || ($decodeParms['invalid_decode_parms_fields'] ?? null) !== $invalidFields
    || ($boundary['effective_decode_parms']['columns'] ?? null) !== 1728
    || ($boundary['effective_decode_parms']['end_of_block'] ?? null) !== true
    || ($entry['payload_in_visible_text'] ?? null) !== false
    || ($entry['native_raster_decode'] ?? null) !== false
    || ($inlineDecodeParms['decode_parms_review'] ?? null) !== 'invalid_ccitt_decodeparms_fail_closed'
    || ($inlineDecodeParms['invalid_decode_parms_fields'] ?? null) !== $invalidFields
    || ($inlineBoundary['effective_decode_parms']['columns'] ?? null) !== 1728
    || ($inlineBoundary['effective_decode_parms']['end_of_block'] ?? null) !== true
    || ($inlineReview['inline_image_payload_excluded_from_text'] ?? null) !== true
    || str_contains($plainText, 'WordPress CCITT scalar tail payload noise')
    || str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload)
    || str_contains(json_encode($inlineReview, JSON_UNESCAPED_SLASHES) ?: '', 'Inline WordPress CCITT scalar tail payload noise')
) {
    throw new RuntimeException('CCITT DecodeParms indirect scalar-tail boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-decodeparms-indirect-scalar-tail-currentbase',
    'upstream_boundary' => 'marker.pdf.images CCITT image stream review before RGB conversion',
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'xobject_resource_name' => $entry['resource_name'] ?? null,
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_decode_parms_review' => $decodeParms['decode_parms_review'] ?? null,
    'xobject_invalid_decode_parms_fields' => $decodeParms['invalid_decode_parms_fields'] ?? [],
    'xobject_effective_decode_parms' => $boundary['effective_decode_parms'] ?? [],
    'xobject_payload_in_visible_text' => false,
    'inline_filters' => $inlineReview['image_filters'] ?? [],
    'inline_decode_parms_review' => $inlineDecodeParms['decode_parms_review'] ?? null,
    'inline_invalid_decode_parms_fields' => $inlineDecodeParms['invalid_decode_parms_fields'] ?? [],
    'inline_effective_decode_parms' => $inlineBoundary['effective_decode_parms'] ?? [],
    'inline_payload_excluded_from_text' => true,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-indirect-scalar-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
