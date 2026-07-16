<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$rendererPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [20 0 R] /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] /Decode [1 0] >>',
    [
        20 => '/CCF /DCTDecode',
    ]
);

$before = 'BT /F1 12 Tf 72 720 Td (Before indirect CCITT filter array tail import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After indirect CCITT filter array tail import) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 700 Td (WordPress indirect CCITT filter tail payload noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /TailFilterFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [20 0 R] /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "20 0 obj\n/CCF /DCTDecode\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererNotes = implode(',', $rendererPlan['notes'] ?? []);
$rendererBoundary = $rendererPlan['image_filter_boundary'] ?? [];
$xobjectBoundary = $entry['ccitt_fax_filter_boundary'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress indirect CCITT filter tail payload noise')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload)
    && !str_contains(json_encode($rendererPlan, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload);
$rendererTailRejected = ($rendererPlan['image_filters'] ?? []) === ['MalformedFilterOperand', 'CCF']
    && ($rendererBoundary['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($rendererBoundary['native_raster_decode'] ?? true) === false
    && str_contains($rendererNotes, 'malformed_image_filter_operand_fail_closed');
$xobjectTailRejected = ($entry['filters'] ?? []) === ['MalformedFilterOperand', 'CCF']
    && ($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($entry['preview_only_filters'] ?? []) === ['CCF']
    && ($entry['native_raster_decode'] ?? true) === false
    && ($entry['decoded_with_current_filters'] ?? true) === false
    && ($xobjectBoundary['declared_filter'] ?? null) === 'CCF'
    && ($xobjectBoundary['filters_before_ccitt'] ?? []) === ['MalformedFilterOperand'];

if (
    $lines !== ['Before indirect CCITT filter array tail import', 'After indirect CCITT filter array tail import']
    || !$rendererTailRejected
    || !$xobjectTailRejected
    || !$payloadExcluded
) {
    throw new RuntimeException('CCITT Fax indirect filter-array tail boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-indirect-filter-array-tail-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image keeps CCITT image streams out of searchable text while image filter operands remain parser-reviewed',
    'renderer_filters' => $rendererPlan['image_filters'] ?? [],
    'renderer_preview_only_filters' => $rendererBoundary['preview_only_filters'] ?? [],
    'renderer_filter_operand_policy' => $rendererBoundary['filter_operand_policy'] ?? null,
    'renderer_indirect_filter_array_tail_rejected' => $rendererTailRejected,
    'renderer_native_raster_decode' => $rendererBoundary['native_raster_decode'] ?? null,
    'xobject_resource_name' => $entry['resource_name'] ?? null,
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'xobject_indirect_filter_array_tail_rejected' => $xobjectTailRejected,
    'xobject_payload_in_visible_text' => $entry['payload_in_visible_text'] ?? null,
    'ccitt_declared_filter' => $xobjectBoundary['declared_filter'] ?? null,
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-indirect-filter-array-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
