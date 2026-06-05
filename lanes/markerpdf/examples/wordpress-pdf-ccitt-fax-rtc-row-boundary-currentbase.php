<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$extractor = new PdfTextExtractor();
$eol = "\x00\x10\x01";
$rtc = $eol . $eol . $eol;

$before = 'BT /F1 12 Tf 72 720 Td (Before RTC CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After RTC CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress RTC row CCITT leak) Tj ET';
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$rtc}";
$staleLength = strpos($faxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Unable to build RTC CCITT row-boundary smoke fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRtcRow 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/Crypt /CCITTFaxDecode] /DecodeParms [<< /Name /Identity >> << /K 0 /Columns 16 /Rows 1 /EndOfLine true /EndOfBlock true >>] /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plainText = $extractor->extractPlainText($pdf);

$inlineContent = "BT /F1 12 Tf 72 720 Td (Before inline RTC CCITT import) Tj ET\n"
    . "BI /W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 1 /EndOfLine true /EndOfBlock true >> ID\n"
    . "\x01\x02{$eol}\nEI\n"
    . "BT /F1 12 Tf 72 700 Td (Inline WordPress RTC row CCITT leak) Tj ET\n"
    . "\x03\x04{$rtc}\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After inline RTC CCITT import) Tj ET";
$inlinePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($inlineContent) . " >>\nstream\n{$inlineContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$inlinePlainText = $extractor->extractPlainText($inlinePdf);

if (
    $plainText !== "Before RTC CCITT import\nAfter RTC CCITT import"
    || $inlinePlainText !== "Before inline RTC CCITT import\nAfter inline RTC CCITT import"
    || str_contains($plainText, 'WordPress RTC row CCITT leak')
    || str_contains($inlinePlainText, 'Inline WordPress RTC row CCITT leak')
    || ($entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null) !== 'rtc'
    || ($entry['raw_length'] ?? null) !== strlen($faxPayload)
) {
    throw new RuntimeException('RTC CCITT row-boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-rtc-row-boundary-currentbase',
    'visible_text' => $plainText,
    'inline_visible_text' => $inlinePlainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'end_of_line' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null,
    'end_of_block' => $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null,
    'end_of_block_marker' => $entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null,
    'row_eol_not_terminal_when_end_of_block_true' => !str_contains($plainText, 'WordPress RTC row CCITT leak'),
    'inline_row_eol_not_terminal_when_end_of_block_true' => !str_contains($inlinePlainText, 'Inline WordPress RTC row CCITT leak'),
    'raw_length_preserved_until_rtc' => ($entry['raw_length'] ?? null) === strlen($faxPayload),
    'payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'WordPress RTC row CCITT leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-rtc-row-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
echo htmlspecialchars($inlinePlainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
