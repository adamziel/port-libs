<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref-stream owner page) Tj T* (Current base xref stream wins) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale embedded xref stream page) Tj T* (Stream-owned xref object leak) Tj ET';
$ownerCarrierText = 'BT /F1 12 Tf 72 680 Td (Owner carrier stream payload) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$freeRow = static fn (): string => chr(0) . pack('N', 0) . chr(1);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$fakeRows = ''
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0'])
    . $xrefRow(1, $offsets['11:0'])
    . $xrefRow(1, $offsets['12:0']);
$fakeCompressedXref = gzcompress($fakeRows);
if (!is_string($fakeCompressedXref)) {
    throw new RuntimeException('Unable to compress fake xref-stream smoke fixture.');
}

$fakeXrefObject = "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 9 0 R /Index [9 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($fakeCompressedXref) . " >>\n"
    . "stream\n{$fakeCompressedXref}\nendstream\nendobj\n";

$ownerPayload = $ownerCarrierText . "\n"
    . "endstream\nendobj\n"
    . $fakeXrefObject
    . 'BT /F1 12 Tf 72 640 Td (Post fake xref owner payload) Tj ET';
$addObject(8, 0, "<< /Length " . strlen($ownerPayload) . " >>\nstream\n{$ownerPayload}\nendstream");

$fakeXrefOffset = strpos($pdf, "20 0 obj\n", $offsets['8:0']);
if ($fakeXrefOffset === false) {
    throw new RuntimeException('Unable to locate embedded fake xref stream object.');
}

$realRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $freeRow()
    . $freeRow()
    . $freeRow()
    . $freeRow()
    . $freeRow();
$realCompressedXref = gzcompress($realRows);
if (!is_string($realCompressedXref)) {
    throw new RuntimeException('Unable to compress real xref-stream smoke fixture.');
}

$addObject(
    21,
    0,
    '<< /Type /XRef /Size 22 /Root 1 0 R /Index [1 5 8 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($realCompressedXref) . " >>\n"
        . "stream\n{$realCompressedXref}\nendstream"
);

$pdf .= "startxref\n{$fakeXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-object-owner-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream object headers embedded inside another stream payload cannot own startxref',
    'uses_current_xref_stream_page' => str_contains($plainText, 'Current xref-stream owner page'),
    'current_base_xref_stream_wins' => str_contains($plainText, 'Current base xref stream wins'),
    'embedded_xref_stream_rejected' => !str_contains($plainText, 'Stale embedded xref stream page'),
    'stream_owned_xref_payload_excluded' => !str_contains($plainText, 'Stream-owned xref object leak'),
    'owner_carrier_payload_excluded' => !str_contains($plainText, 'Owner carrier stream payload'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
