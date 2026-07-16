<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 30 0 R /Decode [1 0] >>',
    [
        20 => '21 0 R',
        21 => '/CCF',
        30 => '31 0 R',
        31 => '<< /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfBlock false >>',
    ]
);

$cyclicPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 40 0 R /DecodeParms 50 0 R >>',
    [
        40 => '41 0 R',
        41 => '40 0 R',
        50 => '51 0 R',
        51 => '50 0 R',
    ]
);

$before = 'BT /F1 12 Tf 72 720 Td (Before chained CCITT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After chained CCITT filter) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress chained indirect CCITT payload noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /ChainedFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 30 0 R /Decode [1 0] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n21 0 R\nendobj\n"
    . "21 0 obj\n/CCF\nendobj\n"
    . "30 0 obj\n31 0 R\nendobj\n"
    . "31 0 obj\n<< /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfBlock false >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress chained indirect CCITT payload noise')
    && !str_contains(json_encode($entry, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress chained indirect CCITT payload noise')
    && !str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress chained indirect CCITT payload noise');

$chainedFilterResolved = ($plan['image_filters'] ?? []) === ['CCF']
    && ($entry['filters'] ?? []) === ['CCF'];
$chainedDecodeParmsResolved = (($plan['ccitt_fax_decode_boundary']['effective_decode_parms']['columns'] ?? null) === 16)
    && (($entry['ccitt_fax_decode_boundary']['effective_decode_parms']['rows'] ?? null) === 2);
$chainedReviewOnly = ($plan['image_filter_boundary']['native_raster_decode'] ?? true) === false
    && ($entry['native_raster_decode'] ?? true) === false
    && ($entry['decoded_with_current_filters'] ?? true) === false;
$cyclicFilterOperandFailClosed = ($cyclicPlan['image_filters'] ?? []) === ['UnresolvedFilterOperand']
    && ($cyclicPlan['ccitt_fax_decode_boundary'] ?? null) === null
    && str_contains(implode(',', $cyclicPlan['notes'] ?? []), 'unresolved_image_filter_operand_fail_closed');

if (
    !$chainedFilterResolved
    || !$chainedDecodeParmsResolved
    || !$chainedReviewOnly
    || !$cyclicFilterOperandFailClosed
    || !$payloadExcluded
    || $lines !== ['Before chained CCITT filter', 'After chained CCITT filter']
) {
    throw new RuntimeException('Chained indirect CCITT Fax filter boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-indirect-filter-chain-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB review handoff',
    'renderer_filters' => $plan['image_filters'] ?? [],
    'renderer_preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'] ?? [],
    'renderer_native_raster_decode' => $plan['image_filter_boundary']['native_raster_decode'] ?? null,
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'chained_indirect_filter_resolved' => $chainedFilterResolved,
    'chained_indirect_decodeparms_resolved' => $chainedDecodeParmsResolved,
    'chained_indirect_ccitt_review_only' => $chainedReviewOnly,
    'cyclic_filter_operand_fail_closed' => $cyclicFilterOperandFailClosed,
    'payload_excluded_from_review' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-indirect-filter-chain-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
