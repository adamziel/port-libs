<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before no length post CCITT) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After no length post CCITT) Tj ET';
$fakeStream = 'BT /F1 12 Tf 72 700 Td (Fake no length post CCITT leak) Tj ET';
$eofb = "\x00\x10\x01";
$payload = "\x01\x02{$eofb}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeStream) . " >>\nstream\n{$fakeStream}\nendstream\nendobj\n"
    . "\x03\x04";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /NoLengthFax 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/CCITTFaxDecode /ASCIIHexDecode] /DecodeParms [<< /K -1 /Columns 16 /Rows 0 /EndOfBlock true >> null] >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

if ($lines !== ['Before no length post CCITT', 'After no length post CCITT']) {
    throw new RuntimeException('Expected WordPress paragraphs to exclude no-Length post-CCITT payload text.');
}

if (str_contains($plainText, 'Fake no length post CCITT leak') || str_contains($encodedReview, 'Fake no length post CCITT leak')) {
    throw new RuntimeException('Stale object text leaked through no-Length post-CCITT boundary.');
}

if (($entry['raw_length'] ?? null) !== strlen($payload)) {
    throw new RuntimeException('Expected image review to own the full no-Length post-CCITT payload.');
}

if (($entry['ccitt_fax_filter_boundary']['post_ccitt_filters_block_native_decode'] ?? null) !== true) {
    throw new RuntimeException('Expected post-CCITT native filters to remain review-only.');
}

$metadata = [
    'scenario' => 'wordpress_pdf_ccitt_fax_no_length_post_filter_currentbase',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? 0,
    'length_operand_present' => false,
    'filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'filters_after_ccitt' => $entry['ccitt_fax_filter_boundary']['filters_after_ccitt'] ?? [],
    'native_filters_after_ccitt' => $entry['ccitt_fax_filter_boundary']['native_filters_after_ccitt'] ?? [],
    'post_ccitt_filters_block_native_decode' => $entry['ccitt_fax_filter_boundary']['post_ccitt_filters_block_native_decode'] ?? null,
    'ccitt_coding_mode' => $entry['ccitt_fax_coding_boundary']['coding_mode'] ?? null,
    'raw_length' => $entry['raw_length'] ?? null,
    'payload_in_visible_text' => $entry['payload_in_visible_text'] ?? null,
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'stale_object_text_excluded' => !str_contains($plainText, 'Fake no length post CCITT leak'),
    'review_text_excludes_stale_object' => !str_contains($encodedReview, 'Fake no length post CCITT leak'),
];

echo '<!-- markerpdf: ' . json_encode($metadata, JSON_UNESCAPED_SLASHES) . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
