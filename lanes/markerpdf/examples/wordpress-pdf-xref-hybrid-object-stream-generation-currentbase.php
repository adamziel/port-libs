<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed generation zero page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid generation page) Tj T* (Referenced generation one recovered) Tj ET';

$members = [
    4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale compressed generation zero) >>',
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
$compressedObjectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object-stream smoke fixture.');
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

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 1 R >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridXref) . " >>\nstream\n{$compressedHybridXref}\nendstream");
$addObject(9, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 4\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:1'], 1)
    . $xrefTableRow($offsets['2:1'], 1)
    . $xrefTableRow($offsets['3:0'])
    . "5 5\n"
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . $xrefTableRow($offsets['7:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($offsets['9:1'], 1)
    . "trailer\n<< /Size 10 /Root 1 1 R /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-hybrid-object-stream-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'hybrid xref companion object-stream generation-zero rows do not satisfy explicit generation-one page references',
    'uses_current_hybrid_generation_page' => str_contains($plainText, 'Current hybrid generation page'),
    'recovers_referenced_generation_one_page' => str_contains($plainText, 'Referenced generation one recovered'),
    'excluded_stale_compressed_generation_zero_page' => !str_contains($plainText, 'Stale compressed generation zero page'),
    'excluded_stale_compressed_generation_zero_metadata' => !str_contains($plainText, 'stale compressed generation zero'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
