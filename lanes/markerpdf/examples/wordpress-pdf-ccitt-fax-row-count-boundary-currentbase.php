<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$extractor = new PdfTextExtractor();
$eol = "\x00\x10\x01";

$before = 'BT /F1 12 Tf 72 720 Td (Before multirow CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After multirow CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake multirow CCITT import leak) Tj ET';
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$eol}";
$staleTerminatorOffset = strpos($faxPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('CCITT row-count smoke must expose a stale first-row terminator.');
}

$xobjectPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRows 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /Rows 2 /EndOfLine true /EndOfBlock false >> /Length {$staleTerminatorOffset} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$inlineContent = "BT /F1 12 Tf 72 720 Td (Before inline multirow CCITT import) Tj ET\n"
    . "BI /W 16 /H 2 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 2 /EndOfLine true /EndOfBlock false >> ID\n"
    . "\x01\x02{$eol}\nEI\n"
    . "BT /F1 12 Tf 72 700 Td (Inline first row CCITT import leak) Tj ET\n"
    . "\x03\x04{$eol}\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After inline multirow CCITT import) Tj ET";
$inlinePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($inlineContent) . " >>\nstream\n{$inlineContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$xobjectPlainText = $extractor->extractPlainText($xobjectPdf);
$xobjectReview = $extractor->extractImageXObjectBoundaryReview($xobjectPdf);
$entry = $xobjectReview['entries'][0] ?? [];
$inlinePlainText = $extractor->extractPlainText($inlinePdf);

if (
    $xobjectPlainText !== "Before multirow CCITT import\nAfter multirow CCITT import"
    || $inlinePlainText !== "Before inline multirow CCITT import\nAfter inline multirow CCITT import"
    || str_contains($xobjectPlainText, 'Fake multirow CCITT import leak')
    || str_contains($inlinePlainText, 'Inline first row CCITT import leak')
    || ($entry['raw_length'] ?? null) !== strlen($faxPayload)
) {
    throw new RuntimeException('CCITT row-count boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-row-count-boundary-currentbase',
    'visible_text' => $xobjectPlainText,
    'inline_visible_text' => $inlinePlainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'declared_rows' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['rows'] ?? null,
    'end_of_line' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null,
    'end_of_block' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'row_count_owned_before_fake_object' => ($entry['raw_length'] ?? null) === strlen($faxPayload),
    'stale_first_row_payload_excluded_from_visible_text' => !str_contains($xobjectPlainText, 'Fake multirow CCITT import leak'),
    'stale_first_row_payload_excluded_from_review' => !str_contains(json_encode($xobjectReview, JSON_UNESCAPED_SLASHES) ?: '', 'Fake multirow CCITT import leak'),
    'inline_first_row_ei_ignored_until_declared_rows' => !str_contains($inlinePlainText, 'Inline first row CCITT import leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-row-count-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $xobjectPlainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
