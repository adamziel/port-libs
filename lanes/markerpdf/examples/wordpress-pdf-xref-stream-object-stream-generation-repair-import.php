<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleCompressedPageContent = 'BT /F1 12 Tf 72 720 Td (Stale direct generation page) Tj ET';
$currentDirectContent = 'BT /F1 12 Tf 72 720 Td (Current direct guard page) Tj ET';
$currentCompressedContent = 'BT /F1 12 Tf 72 720 Td (Current compressed generation page) Tj T* (Object stream index repaired) Tj ET';

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
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($staleCompressedPageContent) . " >>\nstream\n{$staleCompressedPageContent}\nendstream");

$previousRows = ''
    . chr(1) . pack('N', $offsets['1:0']) . chr(0)
    . chr(1) . pack('N', $offsets['2:0']) . chr(0)
    . chr(1) . pack('N', $offsets['3:0']) . chr(0)
    . chr(1) . pack('N', $offsets['4:0']) . chr(0)
    . chr(1) . pack('N', $offsets['5:0']) . chr(0);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$members = [
    12 => '<< /Type /Catalog /Pages 99 0 R /Note (decoy first object stream member) >>',
    4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 1 R >>',
];
$objectData = '';
$headerPairs = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $objectData .= $body . "\n";
}
$header = implode(' ', $headerPairs);
$objectStreamPlain = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPlain);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object-stream smoke fixture.');
}

$currentCatalogOffset = $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$currentPagesOffset = $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$objectStreamOffset = $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$currentDirectPageOffset = $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$currentDirectContentOffset = $addObject(9, 0, "<< /Length " . strlen($currentDirectContent) . " >>\nstream\n{$currentDirectContent}\nendstream");
$currentCompressedContentOffset = $addObject(5, 1, "<< /Length " . strlen($currentCompressedContent) . " >>\nstream\n{$currentCompressedContent}\nendstream");

$currentRows = ''
    . chr(1) . pack('N', $currentCatalogOffset)
    . chr(1) . pack('N', $currentPagesOffset)
    . chr(2) . pack('N', 6)
    . chr(1) . pack('N', $currentCompressedContentOffset)
    . chr(1) . pack('N', $objectStreamOffset)
    . chr(1) . pack('N', $currentDirectPageOffset)
    . chr(1) . pack('N', $currentDirectContentOffset)
    . chr(2) . pack('N', 6);
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 3 8 2 12 1] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-object-stream-generation-repair-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 object-stream member recovery when compressed-object index field is omitted',
    'uses_current_direct_guard_page' => str_contains($plainText, 'Current direct guard page'),
    'repairs_omitted_object_stream_member_index' => str_contains($plainText, 'Object stream index repaired'),
    'uses_current_compressed_generation_page' => str_contains($plainText, 'Current compressed generation page'),
    'excluded_stale_direct_generation_page' => !str_contains($plainText, 'Stale direct generation page'),
    'excluded_decoy_object_stream_member' => !str_contains($plainText, 'decoy first object stream member'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
