<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous hybrid page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref stream owner page) Tj T* (Previous hybrid row skipped) Tj ET';
$reusedCarrierContent = 'BT /F1 12 Tf 72 720 Td (Current replaced object stream leak) Tj ET';

$previousMembers = [
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];
$previousObjectData = '';
$previousHeaderPairs = [];
$previousMemberIndexes = [];
foreach ($previousMembers as $objectNumber => $body) {
    $previousHeaderPairs[] = $objectNumber . ' ' . strlen($previousObjectData);
    $previousMemberIndexes[$objectNumber] = count($previousMemberIndexes);
    $previousObjectData .= $body . "\n";
}
$previousHeader = implode(' ', $previousHeaderPairs);
$previousObjectStream = gzcompress($previousHeader . "\n" . $previousObjectData);
if (!is_string($previousObjectStream)) {
    throw new RuntimeException('Unable to compress previous object stream smoke fixture.');
}
$previousHybridRows = chr(2) . chr(6) . chr($previousMemberIndexes[4]);
$previousHybridXrefStream = gzcompress($previousHybridRows);
if (!is_string($previousHybridXrefStream)) {
    throw new RuntimeException('Unable to compress previous hybrid xref-stream smoke fixture.');
}

$currentMembers = [
    4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (current carrier replacement must not satisfy stale Prev row) >>',
];
$currentObjectData = '';
$currentHeaderPairs = [];
foreach ($currentMembers as $objectNumber => $body) {
    $currentHeaderPairs[] = $objectNumber . ' ' . strlen($currentObjectData);
    $currentObjectData .= $body . "\n";
}
$currentHeader = implode(' ', $currentHeaderPairs);
$currentObjectStream = gzcompress($currentHeader . "\n" . $currentObjectData);
if (!is_string($currentObjectStream)) {
    throw new RuntimeException('Unable to compress current object stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousObjectStream) . " >>\nstream\n{$previousObjectStream}\nendstream");
$previousHybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 11 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($previousHybridXrefStream) . " >>\nstream\n{$previousHybridXrefStream}\nendstream");

$previousTableOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 4\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . "5 3\n"
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . $xrefTableRow($offsets['7:0'])
    . "trailer\n<< /Size 11 /Root 1 0 R /XRefStm {$previousHybridXrefOffset} >>\n"
    . "startxref\n{$previousTableOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$currentPagesOffset = $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$currentObjectStreamOffset = $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
$currentPageOffset = $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$currentContentOffset = $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$reusedCarrierContentOffset = $addObject(10, 0, "<< /Length " . strlen($reusedCarrierContent) . " >>\nstream\n{$reusedCarrierContent}\nendstream");

$currentRows = ''
    . chr(1) . pack('N', $currentCatalogOffset) . chr(1)
    . chr(1) . pack('N', $currentPagesOffset) . chr(1)
    . chr(1) . pack('N', $currentObjectStreamOffset) . chr(0)
    . chr(1) . pack('N', $currentPageOffset) . chr(0)
    . chr(1) . pack('N', $currentContentOffset) . chr(0)
    . chr(1) . pack('N', $reusedCarrierContentOffset) . chr(0);
$currentXrefStream = gzcompress($currentRows);
if (!is_string($currentXrefStream)) {
    throw new RuntimeException('Unable to compress current xref stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousTableOffset . ' /Index [1 2 6 1 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefStream) . " >>\n"
    . "stream\n{$currentXrefStream}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-prev-hybrid-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest xref-stream /Prev hybrid type-2 rows cannot bind to a replaced current object-stream carrier',
    'uses_current_xref_stream_owner_page' => str_contains($plainText, 'Current xref stream owner page'),
    'skips_previous_hybrid_type2_row' => str_contains($plainText, 'Previous hybrid row skipped'),
    'excluded_stale_previous_hybrid_page' => !str_contains($plainText, 'Stale previous hybrid page'),
    'excluded_current_replaced_object_stream_leak' => !str_contains($plainText, 'Current replaced object stream leak'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
