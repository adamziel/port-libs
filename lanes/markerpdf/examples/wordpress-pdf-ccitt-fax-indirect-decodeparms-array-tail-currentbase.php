<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$decodeParmsObject = '[<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>] << /K /Bad /Columns 1 >>';
$renderer = new PdfImageRenderer();
$rendererPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms 11 0 R >>',
    [11 => $decodeParmsObject]
);

$before = 'BT /F1 12 Tf 72 720 Td (Before indirect array-tail CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After indirect array-tail CCITT import) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress indirect array-tail CCITT payload leak) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxArrayTail 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms 11 0 R /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n{$decodeParmsObject}\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererDecodeParms = $rendererPlan['image_filter_details'][0]['decode_parms'] ?? [];
$xobjectDecodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress indirect array-tail CCITT payload leak')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload);

if ($lines !== ['Before indirect array-tail CCITT import', 'After indirect array-tail CCITT import']) {
    throw new RuntimeException('Indirect array-tail CCITT DecodeParms payload leaked into imported text.');
}
if (($rendererDecodeParms['decode_parms_review'] ?? null) !== 'malformed_ccitt_decodeparms_fail_closed') {
    throw new RuntimeException('Renderer did not fail closed for indirect array-tail CCITT DecodeParms.');
}
if (($xobjectDecodeParms['decode_parms_review'] ?? null) !== 'malformed_ccitt_decodeparms_fail_closed') {
    throw new RuntimeException('XObject review did not fail closed for indirect array-tail CCITT DecodeParms.');
}
if (!$payloadExcluded) {
    throw new RuntimeException('Indirect array-tail CCITT payload leaked into WordPress import metadata.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-indirect-decodeparms-array-tail-currentbase',
    'upstream_boundary' => 'marker.pdf provider image extraction CCITTFaxDecode review handoff',
    'renderer_decode_parms_review' => $rendererDecodeParms['decode_parms_review'] ?? null,
    'xobject_decode_parms_review' => $xobjectDecodeParms['decode_parms_review'] ?? null,
    'invalid_decode_parms_fields' => $entry['ccitt_fax_decode_boundary']['invalid_decode_parms_fields'] ?? [],
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'payload_excluded_from_text' => !str_contains($plainText, 'WordPress indirect array-tail CCITT payload leak'),
    'payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-indirect-decodeparms-array-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
