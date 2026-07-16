<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$extractor = new PdfTextExtractor();
$before = 'BT /F1 12 Tf 72 720 Td (Before indirect Rows CCITT) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After indirect Rows CCITT) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake indirect Rows CCITT owner leak) Tj ET';
$eol = "\x00\x10\x01";
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$eol}";
$staleLength = strpos($faxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Focused indirect-Rows CCITT fixture must expose a stale row-end terminator.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRows 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /Rows 11 0 R /EndOfLine true /EndOfBlock false >> /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "11 0 obj\n2\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$expectedLines = ['Before indirect Rows CCITT', 'After indirect Rows CCITT'];
$heightFallbackForStreamOwnership = $lines === $expectedLines
    && ($entry['raw_length'] ?? null) === strlen($faxPayload)
    && !str_contains($plainText, 'Fake indirect Rows CCITT owner leak')
    && !str_contains($plainText, 'endstream');
$indirectRowsResolved = (($entry['ccitt_fax_decode_boundary']['effective_decode_parms']['rows'] ?? null) === 2)
    && (($entry['ccitt_fax_decode_boundary']['rows_match_height'] ?? null) === true);
$payloadExcludedFromReview = !str_contains($encodedReview, $faxPayload)
    && !str_contains($encodedReview, 'Fake indirect Rows CCITT owner leak');
$payloadExcludedFromText = !str_contains($plainText, 'Fake indirect Rows CCITT owner leak')
    && !str_contains($plainText, 'endstream');
$reviewOnly = ($entry['filters'] ?? []) === ['CCITTFaxDecode']
    && ($entry['preview_only_filters'] ?? []) === ['CCITTFaxDecode']
    && ($entry['native_raster_decode'] ?? true) === false
    && ($entry['decoded_with_current_filters'] ?? true) === false;

if (
    !$heightFallbackForStreamOwnership
    || !$indirectRowsResolved
    || !$payloadExcludedFromReview
    || !$payloadExcludedFromText
    || !$reviewOnly
) {
    throw new RuntimeException('Indirect Rows CCITT Fax boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-indirect-rows-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image review handoff',
    'xobject_filters' => $entry['filters'] ?? [],
    'xobject_preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'indirect_rows_resolved' => $indirectRowsResolved,
    'height_fallback_for_stream_ownership' => $heightFallbackForStreamOwnership,
    'payload_excluded_from_review' => $payloadExcludedFromReview,
    'payload_excluded_from_text' => $payloadExcludedFromText,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-indirect-rows-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
