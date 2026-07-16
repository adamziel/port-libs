<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before comment indirect CCITT) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After comment indirect CCITT) Tj ET';
$faxPayload = 'BT /F1 12 Tf 72 700 Td (Comment indirect CCITT payload leak) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /CommentFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 21 0 R /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "20 0 obj\n% source filter comment before the real PDF name\n/CCF\nendobj\n"
    . "21 0 obj\n% DecodeParms comment before the dictionary\n<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EncodedByteAlign true /EndOfBlock true >>\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$renderer = new PdfImageRenderer();
$rendererPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 20 0 R /DecodeParms 21 0 R /Decode [1 0] >>',
    [
        20 => "% source filter comment before the real PDF name\n/CCF",
        21 => "% DecodeParms comment before the dictionary\n<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EncodedByteAlign true /EndOfBlock true >>",
    ]
);
$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];

$rendererResolved = ($rendererPlan['image_filters'] ?? []) === ['CCF']
    && ($rendererPlan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null) === false;
$xobjectResolved = ($entry['filters'] ?? []) === ['CCF']
    && ($entry['preview_only_filters'] ?? []) === ['CCF']
    && ($entry['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null) === false;
$payloadExcluded = !str_contains($plainText, 'Comment indirect CCITT payload leak')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload);

if (
    $lines !== ['Before comment indirect CCITT', 'After comment indirect CCITT']
    || !$rendererResolved
    || !$xobjectResolved
    || !$payloadExcluded
) {
    throw new RuntimeException('CCITT Fax indirect comment boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-indirect-comment-currentbase',
    'upstream_boundary' => 'PDF comments are whitespace around indirect filter and DecodeParms operands before marker.pdf image review',
    'renderer_comment_filter_resolved' => $rendererResolved,
    'xobject_comment_filter_resolved' => $xobjectResolved,
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'effective_decode_parms' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms'] ?? null,
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-indirect-comment-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
