<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before CCITT DecodeParms array tail import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After CCITT DecodeParms array tail import) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 700 Td (WordPress CCITT DecodeParms array-tail payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /TailArrayFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /EndOfBlock true >>] 7 /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$decodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
$boundary = $entry['ccitt_fax_decode_boundary'] ?? [];

$renderer = new PdfImageRenderer();
$inlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline WordPress CCITT DecodeParms array-tail payload noise) Tj ET final";
$inlineReview = $renderer->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP [<< /K 0 /Columns 16 /Rows 1 /EndOfBlock true >>] 7 /D [1 0]',
    $inlinePayload
);
$inlineDecodeParms = $inlineReview['image_filter_details'][0]['decode_parms'] ?? [];

if (
    ($decodeParms['decode_parms_review'] ?? null) !== 'malformed_ccitt_decodeparms_fail_closed'
    || ($decodeParms['decode_parms_operand_detail'] ?? null) !== 'array_with_trailing_operands'
    || ($decodeParms['decode_parms_array_policy'] ?? null) !== 'reject_top_level_decodeparms_array_tail'
    || ($boundary['invalid_decode_parms'] ?? null) !== true
    || ($entry['payload_in_visible_text'] ?? null) !== false
    || ($inlineDecodeParms['decode_parms_operand_detail'] ?? null) !== 'array_with_trailing_operands'
    || ($inlineDecodeParms['decode_parms_array_policy'] ?? null) !== 'reject_top_level_decodeparms_array_tail'
    || ($inlineReview['inline_image_payload_excluded_from_text'] ?? null) !== true
    || str_contains($plainText, 'WordPress CCITT DecodeParms array-tail payload noise')
    || str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload)
    || str_contains(json_encode($inlineReview, JSON_UNESCAPED_SLASHES) ?: '', 'Inline WordPress CCITT DecodeParms array-tail payload noise')
) {
    throw new RuntimeException('CCITT DecodeParms array-operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-decodeparms-array-operand-currentbase',
    'upstream_boundary' => 'marker.pdf.images CCITT image stream review before RGB conversion',
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'xobject_resource_name' => $entry['resource_name'] ?? null,
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_decode_parms_review' => $decodeParms['decode_parms_review'] ?? null,
    'xobject_decode_parms_operand_detail' => $decodeParms['decode_parms_operand_detail'] ?? null,
    'xobject_decode_parms_policy' => $decodeParms['decode_parms_array_policy'] ?? null,
    'xobject_invalid_decode_parms' => $boundary['invalid_decode_parms'] ?? null,
    'xobject_payload_in_visible_text' => false,
    'inline_filters' => $inlineReview['image_filters'] ?? [],
    'inline_decode_parms_review' => $inlineDecodeParms['decode_parms_review'] ?? null,
    'inline_decode_parms_operand_detail' => $inlineDecodeParms['decode_parms_operand_detail'] ?? null,
    'inline_decode_parms_policy' => $inlineDecodeParms['decode_parms_array_policy'] ?? null,
    'inline_payload_excluded_from_text' => true,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-array-operand-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
