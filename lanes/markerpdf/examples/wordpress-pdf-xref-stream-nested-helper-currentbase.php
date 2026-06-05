<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current nested xref helper page) Tj T* (Nested compressed helper references selected) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale nested xref helper leak) Tj T* (Unresolved helper fallback) Tj ET';

$objectStream = static function (array $members, array &$memberIndexes): array {
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress nested xref helper smoke object stream.');
    }

    return [$header, $compressed];
};

$currentMemberIndexes = [];
[$currentHeader, $currentObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
], $currentMemberIndexes);

$xrefOperandReferenceIndexes = [];
[$xrefOperandReferenceHeader, $xrefOperandReferenceStream] = $objectStream([
    30 => "33 % nested /W helper reference split by a PDF comment\n0 R",
    31 => "34 % nested /Index helper reference split by a PDF comment\n0 R",
], $xrefOperandReferenceIndexes);

$xrefOperandValueIndexes = [];
[$xrefOperandValueHeader, $xrefOperandValueStream] = $objectStream([
    33 => '[1 4 1]',
    34 => '[1 6 9 1 30 2]',
], $xrefOperandValueIndexes);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (stale direct page fallback) >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
$addObject(7, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($xrefOperandReferenceHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($xrefOperandReferenceStream) . " >>\nstream\n{$xrefOperandReferenceStream}\nendstream");
$addObject(8, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($xrefOperandValueHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($xrefOperandValueStream) . " >>\nstream\n{$xrefOperandValueStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, $currentMemberIndexes[4])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(2, 7, $xrefOperandReferenceIndexes[30])
    . $xrefRow(2, 7, $xrefOperandReferenceIndexes[31]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress nested xref helper smoke xref stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 35 /Root 1 0 R /Index 31 0 R /W 30 0 R /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$pageEntry = $entries[4] ?? [];

echo '<!-- markerpdf-xref-stream-nested-helper-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream /W and /Index arrays resolve through nested compressed helper references before WordPress text import',
    'current_text_selected' => str_contains($plainText, 'Current nested xref helper page'),
    'nested_helper_line_selected' => str_contains($plainText, 'Nested compressed helper references selected'),
    'stale_direct_page_excluded' => !str_contains($plainText, 'stale direct page fallback'),
    'stale_text_excluded' => !str_contains($plainText, 'Stale nested xref helper leak'),
    'fallback_text_excluded' => !str_contains($plainText, 'Unresolved helper fallback'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'page_object_stream' => $pageEntry['object_stream'] ?? null,
    'selection_policy' => $pageEntry['selection_policy'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
