<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current zero-width member page) Tj T* (Recovered by header object number) Tj ET';

$members = [
    12 => '<< /Type /Page /Parent 2 0 R /Note (strict zero member decoy) >>',
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];
$objectData = '';
$headerPairs = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $objectData .= $body . "\n";
}
$header = implode(' ', $headerPairs);
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress zero-width member object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = ''
    . chr(1) . pack('N', $offsets['1:0'])
    . chr(1) . pack('N', $offsets['2:0'])
    . chr(1) . pack('N', $offsets['3:0'])
    . chr(2) . pack('N', 6)
    . chr(1) . pack('N', $offsets['5:0'])
    . chr(1) . pack('N', $offsets['6:0'])
    . chr(2) . pack('N', 6);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress zero-width member xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 12 1] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-object-stream-index-zero-width-member-review-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 zero-width member indexes are review-visible when PHP recovery uses object-stream header object numbers',
    'uses_current_zero_width_member_page' => str_contains($plainText, 'Current zero-width member page'),
    'recovered_by_header_object_number' => ($entries[4]['selection_policy'] ?? null) === 'recovered_by_header_object_number',
    'strict_dependency_would_reject_recovered_member' => ($entries[4]['strict_dependency_would_reject'] ?? null) === true,
    'strict_zero_member_decoy_is_review_only' => ($entries[12]['selection_policy'] ?? null) === 'default_zero_member_index',
    'excluded_strict_zero_member_decoy_text' => !str_contains($plainText, 'strict zero member decoy'),
    'zero_width_index_entry_count' => $review['zero_width_index_entry_count'],
    'recovered_zero_width_member_count' => $review['recovered_zero_width_member_count'],
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
