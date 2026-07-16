<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed previous generation page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current direct generation page) Tj T* (Hybrid table boundary kept) Tj ET';

$members = [
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];

$objectData = '';
$headerPairs = [];
$memberIndexes = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerPairs);
$objectStreamPlain = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPlain);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object stream smoke fixture.');
}

$xrefRows = chr(2) . chr(6) . chr($memberIndexes[4]);
$compressedHybridXref = gzcompress($xrefRows);
if (!is_string($compressedHybridXref)) {
    throw new RuntimeException('Unable to compress hybrid xref-stream smoke fixture.');
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
$xrefStreamRows = static function (array $rows): string {
    $encoded = '';
    foreach ($rows as $row) {
        [$offset, $generation] = $row;
        $encoded .= chr(1) . pack('N', $offset) . chr($generation);
    }

    return $encoded;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridXref) . " >>\nstream\n{$compressedHybridXref}\nendstream");

$previousRows = $xrefStreamRows([
    [$offsets['1:0'], 0],
    [$offsets['2:0'], 0],
    [$offsets['3:0'], 0],
    [$offsets['5:0'], 0],
    [$offsets['6:0'], 0],
    [$offsets['7:0'], 0],
]);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
$addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n" . $xrefTableRow(0, 65535, 'f')
    . "1 2\n" . $xrefTableRow($offsets['1:1'], 1) . $xrefTableRow($offsets['2:1'], 1)
    . "4 1\n" . $xrefTableRow($offsets['4:1'], 1)
    . "9 1\n" . $xrefTableRow($offsets['9:0'])
    . "trailer\n<< /Size 21 /Root 1 1 R /Prev {$previousXrefOffset} /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-generation-repair-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current hybrid xref table direct-generation rows remain authoritative over companion xref-stream compressed stale members',
    'uses_current_direct_generation_page' => str_contains($plainText, 'Current direct generation page'),
    'keeps_hybrid_table_direct_entry' => str_contains($plainText, 'Hybrid table boundary kept'),
    'excluded_previous_compressed_generation_page' => !str_contains($plainText, 'Stale compressed previous generation page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
