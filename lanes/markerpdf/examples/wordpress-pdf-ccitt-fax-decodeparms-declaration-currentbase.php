<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$expectedDecodeParms = [
    'type' => 'CCITTFaxDecode',
    'k' => null,
    'columns' => null,
    'rows' => null,
    'black_is_1' => null,
    'encoded_byte_align' => null,
    'end_of_line' => null,
    'end_of_block' => null,
    'damaged_rows_before_error' => null,
    'valid_decode_parms' => false,
    'invalid_decode_parms_fields' => ['decode_parms_declaration'],
    'decode_parms_review' => 'duplicate_ccitt_decodeparms_declaration_fail_closed',
    'duplicate_decode_parms_declaration_count' => 1,
    'decode_parms_declaration_policy' => 'reject_duplicate_decodeparms_declarations',
];
$expectedEffective = [
    'k' => 0,
    'columns' => 1728,
    'rows' => 0,
    'black_is_1' => false,
    'encoded_byte_align' => false,
    'end_of_line' => false,
    'end_of_block' => true,
    'damaged_rows_before_error' => 0,
];

$renderer = new PdfImageRenderer();
$inlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline duplicate declaration CCITT payload noise) Tj ET final";
$inlinePlan = $renderer->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP << /K -1 /Columns 16 /Rows 1 /BlackIs1 true >> /DP << /K /Bad /Columns 1 /Rows 1 /EndOfBlock false >> /D [1 0]',
    $inlinePayload
);
$inlineParms = $inlinePlan['image_filter_details'][0]['decode_parms'] ?? null;
$inlineBoundary = $inlinePlan['ccitt_fax_decode_boundary'] ?? [];

$before = 'BT /F1 12 Tf 72 720 Td (Before duplicate declaration CCITT) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After duplicate declaration CCITT) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress duplicate declaration CCITT leak) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /DuplicateDeclarationFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 16 /Rows 1 /BlackIs1 true >> /DecodeParms << /K /Bad /Columns 1 /Rows 1 /EndOfBlock false >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$xobjectParms = $entry['filter_details'][0]['decode_parms'] ?? null;
$xobjectBoundary = $entry['ccitt_fax_decode_boundary'] ?? [];

$inlineRejected = $inlineParms === $expectedDecodeParms
    && ($inlineBoundary['invalid_decode_parms'] ?? null) === true
    && ($inlineBoundary['effective_decode_parms'] ?? null) === $expectedEffective
    && in_array('duplicate_ccitt_decodeparms_declaration_fail_closed', $inlinePlan['notes'] ?? [], true);
$xobjectRejected = $xobjectParms === $expectedDecodeParms
    && ($xobjectBoundary['invalid_decode_parms'] ?? null) === true
    && ($xobjectBoundary['invalid_decode_parms_fields'] ?? null) === ['decode_parms_declaration']
    && ($xobjectBoundary['effective_decode_parms'] ?? null) === $expectedEffective;
$payloadExcluded = !str_contains($plainText, 'WordPress duplicate declaration CCITT leak')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload)
    && !str_contains(json_encode($inlinePlan, JSON_UNESCAPED_SLASHES) ?: '', $inlinePayload);

if (
    !$inlineRejected
    || !$xobjectRejected
    || !$payloadExcluded
    || $lines !== ['Before duplicate declaration CCITT', 'After duplicate declaration CCITT']
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($entry['decoded_with_current_filters'] ?? true) !== false
    || ($entry['payload_in_visible_text'] ?? true) !== false
) {
    throw new RuntimeException('Duplicate top-level CCITT Fax DecodeParms declaration boundary was not rejected before WordPress import.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-decodeparms-declaration-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-ccitt-fax-decodeparms-declaration-currentbase',
    'upstream_boundary' => 'CCITTFaxDecode raster bytes stay image-only while duplicate top-level DecodeParms declarations fail closed',
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'decode_parms_review' => $xobjectParms['decode_parms_review'] ?? null,
    'duplicate_decode_parms_declaration_count' => $xobjectParms['duplicate_decode_parms_declaration_count'] ?? null,
    'xobject_invalid_decode_parms_fields' => $xobjectBoundary['invalid_decode_parms_fields'] ?? [],
    'effective_decode_parms_after_fail_closed' => $xobjectBoundary['effective_decode_parms'] ?? [],
    'inline_duplicate_declaration_rejected' => $inlineRejected,
    'xobject_duplicate_declaration_rejected' => $xobjectRejected,
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'paragraphs' => $lines,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
