<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner cycle page) Tj T* (Direct xref stream preserved) Tj ET';
$compressedXrefMemberLeak = 'BT /F1 12 Tf 72 700 Td (Compressed xref owner cycle leak) Tj ET';

$compressedOwnerMembers = [
    20 => '<< /Type /XRef /Length ' . strlen($compressedXrefMemberLeak) . " >>\nstream\n{$compressedXrefMemberLeak}\nendstream",
];
$compressedOwnerData = '';
$compressedOwnerHeaderPairs = [];
$compressedOwnerMemberIndexes = [];
foreach ($compressedOwnerMembers as $objectNumber => $body) {
    $compressedOwnerHeaderPairs[] = $objectNumber . ' ' . strlen($compressedOwnerData);
    $compressedOwnerMemberIndexes[$objectNumber] = count($compressedOwnerMemberIndexes);
    $compressedOwnerData .= $body . "\n";
}

$compressedOwnerHeader = implode(' ', $compressedOwnerHeaderPairs);
$compressedOwnerStream = gzcompress($compressedOwnerHeader . "\n" . $compressedOwnerData);
if (!is_string($compressedOwnerStream)) {
    throw new RuntimeException('Unable to compress xref owner-cycle object stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($compressedOwnerHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedOwnerStream) . " >>\nstream\n{$compressedOwnerStream}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(2, 6, $compressedOwnerMemberIndexes[20]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress current xref owner-cycle stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-stream-object-owner-cycle-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'direct xref stream owners are preserved when decoded type-2 rows form a compressed owner cycle',
    'uses_current_xref_owner_cycle_page' => str_contains($plainText, 'Current xref owner cycle page'),
    'preserves_direct_xref_stream_owner' => ($entries[20]['owner_policy'] ?? null) === 'direct_xref_stream_owner_preserved',
    'rejects_compressed_xref_owner_cycle' => ($entries[20]['owner_cycle_rejected'] ?? false) === true,
    'excluded_compressed_xref_owner_cycle_leak' => !str_contains($plainText, 'Compressed xref owner cycle leak'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
