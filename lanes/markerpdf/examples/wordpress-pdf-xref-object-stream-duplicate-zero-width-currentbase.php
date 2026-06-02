<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate zero-width guard) Tj T* (Ambiguous compressed page ignored) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale duplicate member page) Tj ET';
$duplicateLeakContent = 'BT /F1 12 Tf 72 720 Td (Duplicate zero-width member leak) Tj ET';

$members = [
    [12, '<< /Type /Page /Parent 2 0 R /Note (strict default-index decoy member) >>'],
    [4, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (first duplicate page member) >>'],
    [4, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (second duplicate page member) >>'],
];
$objectData = '';
$headerPairs = [];
foreach ($members as [$objectNumber, $body]) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $objectData .= $body . "\n";
}
$header = implode(' ', $headerPairs);
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress duplicate zero-width object-stream smoke fixture.');
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
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($duplicateLeakContent) . " >>\nstream\n{$duplicateLeakContent}\nendstream");

$xrefRows = ''
    . chr(1) . pack('N', $offsets['1:0'])
    . chr(1) . pack('N', $offsets['2:0'])
    . chr(1) . pack('N', $offsets['3:0'])
    . chr(2) . pack('N', 6)
    . chr(1) . pack('N', $offsets['5:0'])
    . chr(1) . pack('N', $offsets['6:0'])
    . chr(1) . pack('N', $offsets['8:0'])
    . chr(1) . pack('N', $offsets['9:0'])
    . chr(1) . pack('N', $offsets['10:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress duplicate zero-width xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-object-stream-duplicate-zero-width-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 zero-width member indexes recover by object-stream header only when the header object number is unique',
    'uses_current_duplicate_zero_width_guard' => str_contains($plainText, 'Current duplicate zero-width guard'),
    'ambiguous_compressed_page_ignored' => str_contains($plainText, 'Ambiguous compressed page ignored'),
    'excluded_stale_duplicate_member_page' => !str_contains($plainText, 'Stale duplicate member page'),
    'excluded_duplicate_zero_width_member_leak' => !str_contains($plainText, 'Duplicate zero-width member leak'),
    'selection_policy' => $entries[4]['selection_policy'] ?? null,
    'matching_header_object_number_count' => $entries[4]['matching_header_object_number_count'] ?? null,
    'ambiguous_zero_width_member_count' => $review['ambiguous_zero_width_member_count'],
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
