<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Prev compressed same-offset generation page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current sparse Index generation page) Tj T* (Same carrier offset preserved) Tj ET';

$objectData = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>' . "\n";
$header = '4 0';
$compressedObjectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress same-offset object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$previousRows = ''
    . $row(1, $offsets['1:0'], 0)
    . $row(1, $offsets['2:0'], 0)
    . $row(1, $offsets['3:0'], 0)
    . $row(2, 6, 0)
    . $row(1, $offsets['5:0'], 0)
    . $row(1, $offsets['6:0'], 0);
$previousCompressedXref = gzcompress($previousRows);
if (!is_string($previousCompressedXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $row(1, $offsets['1:1'], 1)
    . $row(1, $offsets['2:1'], 1)
    . $row(1, $offsets['6:0'], 1)
    . $row(1, $offsets['8:0'], 0)
    . $row(1, $offsets['9:0'], 0);
$currentCompressedXref = gzcompress($currentRows);
if (!is_string($currentCompressedXref)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 6 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
    . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = $extractor->extractOutlineMetadata($pdf);

echo '<!-- markerpdf-xref-stream-prev-index-generation-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream /Prev chain with sparse /Index preserves previous type-2 members when the current carrier row keeps the same explicit offset despite generation noise',
    'uses_current_sparse_index_generation_page' => str_contains($plainText, 'Current sparse Index generation page'),
    'preserves_prev_same_offset_compressed_page' => str_contains($plainText, 'Prev compressed same-offset generation page'),
    'keeps_same_carrier_offset' => str_contains($plainText, 'Same carrier offset preserved'),
    'page_count' => $metadata['pages'] ?? 0,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
