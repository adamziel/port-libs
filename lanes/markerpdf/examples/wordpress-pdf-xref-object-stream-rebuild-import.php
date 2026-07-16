<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current startxref page) Tj T* (Object stream rebuild guard) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale appended object stream page) Tj ET';

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$offsets = [];
$offsets[1] = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$offsets[2] = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$offsets[3] = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$offsets[4] = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$offsets[5] = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF\n";

$stalePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>';
$objectStreamPlain = "3 0\n{$stalePage}\n";
$compressedObjectStream = gzcompress($objectStreamPlain);
$staleObjectStreamOffset = $addObject(7, 0, '<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$staleContentOffset = $addObject(6, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$staleXrefRows = ''
    . chr(2) . pack('N', 7) . chr(0)
    . chr(1) . pack('N', $staleContentOffset) . chr(0)
    . chr(1) . pack('N', $staleObjectStreamOffset) . chr(0);
$compressedXref = gzcompress($staleXrefRows);
$addObject(20, 0, '<< /Type /XRef /Size 21 /Root 1 0 R /Index [3 1 6 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream");

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-object-stream-rebuild-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'startxref-chain xref table/stream precedence before object-stream rebuild fallback',
    'uses_current_startxref_page' => str_contains($plainText, 'Current startxref page'),
    'excluded_stale_appended_object_stream_page' => !str_contains($plainText, 'Stale appended object stream page'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
