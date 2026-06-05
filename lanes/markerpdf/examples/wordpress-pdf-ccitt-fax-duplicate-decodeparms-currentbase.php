<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$inlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline duplicate CCITT DecodeParms payload noise) Tj ET final";
$inlinePlan = $renderer->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP << /K -1 /K 0 /Columns 16 /Rows 1 /BlackIs1 true /BlackIs1 false /EndOfBlock true >> /D [1 0]',
    $inlinePayload
);
$inlineParms = $inlinePlan['image_filter_details'][0]['decode_parms'] ?? [];

$before = 'BT /F1 12 Tf 72 720 Td (Before duplicate CCITT DecodeParms) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After duplicate CCITT DecodeParms) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress duplicate CCITT DecodeParms payload noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /DuplicateParmsFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /K 0 /Columns 16 /Rows 1 /Rows 2 /BlackIs1 true /EndOfBlock true >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$xobjectParms = $entry['filter_details'][0]['decode_parms'] ?? [];
$xobjectBoundary = $entry['ccitt_fax_decode_boundary'] ?? [];

$inlineDuplicateRejected = ($inlineParms['valid_decode_parms'] ?? null) === false
    && ($inlineParms['duplicate_decode_parms_fields'] ?? null) === ['k', 'black_is_1']
    && ($inlineParms['decode_parms_review'] ?? null) === 'duplicate_ccitt_decodeparms_parameter_fail_closed';
$xobjectDuplicateRejected = ($xobjectParms['valid_decode_parms'] ?? null) === false
    && ($xobjectParms['duplicate_decode_parms_fields'] ?? null) === ['k', 'rows']
    && ($xobjectParms['decode_parms_review'] ?? null) === 'duplicate_ccitt_decodeparms_parameter_fail_closed'
    && ($xobjectBoundary['invalid_decode_parms'] ?? null) === true;
$payloadExcluded = !str_contains($plainText, 'WordPress duplicate CCITT DecodeParms payload noise')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload)
    && !str_contains(json_encode($inlinePlan, JSON_UNESCAPED_SLASHES) ?: '', $inlinePayload);

if (
    !$inlineDuplicateRejected
    || !$xobjectDuplicateRejected
    || !$payloadExcluded
    || $lines !== ['Before duplicate CCITT DecodeParms', 'After duplicate CCITT DecodeParms']
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($entry['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('Duplicate CCITT Fax DecodeParms smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-duplicate-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only CCITT handoff',
    'inline_duplicate_fields' => $inlineParms['duplicate_decode_parms_fields'] ?? [],
    'xobject_duplicate_fields' => $xobjectParms['duplicate_decode_parms_fields'] ?? [],
    'decode_parms_review' => $xobjectParms['decode_parms_review'] ?? null,
    'xobject_invalid_decode_parms' => $xobjectBoundary['invalid_decode_parms'] ?? null,
    'effective_k_after_fail_closed_default' => $xobjectBoundary['effective_decode_parms']['k'] ?? null,
    'effective_rows_after_fail_closed_default' => $xobjectBoundary['effective_decode_parms']['rows'] ?? null,
    'native_raster_decode' => false,
    'payload_in_visible_text' => !$payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-duplicate-decodeparms-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
