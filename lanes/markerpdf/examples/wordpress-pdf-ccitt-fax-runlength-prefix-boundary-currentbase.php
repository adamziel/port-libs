<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$before = 'BT /F1 12 Tf 72 720 Td (Before RunLength CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After RunLength CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (RL CCITT leak) Tj ET';
$ccittEofb = "\x00\x10\x01";
$literal = "\x80\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x00{$ccittEofb}";
if (strlen($literal) > 128) {
    throw new RuntimeException('RunLength CCITT smoke literal must fit one literal packet.');
}
$runLengthPayload = chr(strlen($literal) - 1) . $literal . chr(128);
$staleTerminatorOffset = strpos($runLengthPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('RunLength CCITT smoke must expose a stale endstream marker.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRunLength 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/RunLengthDecode /CCITTFaxDecode] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$staleTerminatorOffset} >>\nstream\n{$runLengthPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['ccitt_fax_filter_boundary'] ?? []) : [];

if (
    $plainText !== "Before RunLength CCITT import\nAfter RunLength CCITT import"
    || str_contains($plainText, 'RL CCITT leak')
    || ($entry['raw_length'] ?? null) !== strlen($runLengthPayload)
    || ($entry['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('RunLength-prefixed CCITT boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-runlength-prefix-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_prefix_filters' => $boundary['native_prefix_filters'] ?? [],
    'runlength_literal_eod_ignored_until_actual_eod' => ($entry['raw_length'] ?? null) === strlen($runLengthPayload),
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'RL CCITT leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'RL CCITT leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-runlength-prefix-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
