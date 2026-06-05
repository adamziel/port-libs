<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Decode [1 0] '
    . '/Nested << /Filter /FlateDecode /DecodeParms << /Columns 1 >> >> '
    . '/Fil#74er /CCITT#46axDecode '
    . '/Decode#50arms << /K -1 /Columns 16 /Rows 2 /Black#49s1 true /EncodedByte#41lign true /EndOf#4cine true /EndOf#42lock false /DamagedRowsBefore#45rror 2 >> >>'
);

$before = 'BT /F1 12 Tf 72 720 Td (Before escaped CCITT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After escaped CCITT filter) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress escaped CCITT payload noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /EscapedFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Fil#74er /CCITT#46axDecode /Decode#50arms << /K -1 /Columns 16 /Rows 2 /Black#49s1 true /EndOf#42lock false >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress escaped CCITT payload noise');

if (
    ($plan['image_filters'] ?? []) !== ['CCITTFaxDecode']
    || ($plan['image_filter_boundary']['preview_only_filters'] ?? []) !== ['CCITTFaxDecode']
    || ($plan['image_filter_boundary']['native_raster_decode'] ?? true) !== false
    || (($plan['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null) !== -1)
    || (($plan['ccitt_fax_decode_boundary']['effective_width'] ?? null) !== 16)
    || (($plan['ccitt_fax_decode_boundary']['effective_height'] ?? null) !== 2)
    || ($entry['preview_only_filters'] ?? []) !== ['CCITTFaxDecode']
    || ($entry['native_raster_decode'] ?? true) !== false
    || $lines !== ['Before escaped CCITT filter', 'After escaped CCITT filter']
    || !$payloadExcluded
) {
    throw new RuntimeException('Escaped CCITT Fax filter boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-escaped-filter-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB review handoff',
    'renderer_filters' => $plan['image_filters'] ?? [],
    'renderer_preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'] ?? [],
    'renderer_native_raster_decode' => $plan['image_filter_boundary']['native_raster_decode'] ?? null,
    'escaped_filter_key_decoded' => ($plan['image_filters'] ?? []) === ['CCITTFaxDecode'],
    'escaped_decodeparms_key_decoded' => (($plan['ccitt_fax_decode_boundary']['effective_decode_parms']['columns'] ?? null) === 16),
    'nested_decoy_filter_ignored' => !in_array('FlateDecode', $plan['image_filters'] ?? [], true),
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'payload_in_visible_text' => !$payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-escaped-filter-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
