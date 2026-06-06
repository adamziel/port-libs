<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 '
    . '/Filter [/DCTDecode /CCF] '
    . '/DecodeParms [null << /K -1 /Columns 16 /Rows 1 /EndOfBlock true >>] >>'
);

$before = 'BT /F1 12 Tf 72 720 Td (Before pre CCITT preview filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After pre CCITT preview filter) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress pre CCITT preview payload noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /PrePreviewFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/DCTDecode /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 1 /EndOfBlock true >>] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plainText = $extractor->extractPlainText($pdf);
$boundary = $entry['ccitt_fax_filter_boundary'] ?? [];

$previewPrefixBlocked = ($plan['ccitt_fax_filter_boundary']['pre_ccitt_preview_filters_block_native_prefix_decode'] ?? false) === true
    && ($boundary['pre_ccitt_preview_filters_block_native_prefix_decode'] ?? false) === true
    && ($boundary['preview_only_filters_before_ccitt'] ?? []) === ['DCTDecode']
    && ($boundary['native_prefix_filters'] ?? []) === [];
$payloadExcluded = !str_contains($plainText, 'WordPress pre CCITT preview payload noise')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload);

if (!$previewPrefixBlocked || !$payloadExcluded || ($entry['native_raster_decode'] ?? true) !== false) {
    throw new RuntimeException('CCITT Fax preview-prefix boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-preview-prefix-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only filter handoff',
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters_before_ccitt' => $boundary['preview_only_filters_before_ccitt'] ?? [],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'pre_ccitt_preview_filters_block_native_prefix_decode' => $previewPrefixBlocked,
    'payload_in_visible_text' => false,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-preview-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
