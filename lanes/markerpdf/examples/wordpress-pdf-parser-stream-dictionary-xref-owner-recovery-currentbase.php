<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Xref-owner stream dictionary smoke rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner stream dictionary) Tj T* (Recovered xref stream helpers) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref owner stream dictionary leak) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$freeRow = static fn (): string => chr(0) . pack('N', 0) . chr(255);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(7, 0, '00000');
$addObject(8, 0, '/FlateDecode');
$addObject(9, 0, '<< /Predictor 12 /Columns 6 >>');

$xrefOffset = strlen($pdf);
$rows = ''
    . $freeRow()
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $freeRow()
    . $xrefRow(1, $offsets['7:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $xrefOffset);
$encodedRows = gzcompress($pngSubPredictorEncode($rows, 6));
if (!is_string($encodedRows)) {
    throw new RuntimeException('Unable to compress xref-owner stream dictionary smoke fixture.');
}
$encodedLength = str_pad((string) strlen($encodedRows), 5, '0', STR_PAD_LEFT);
$pdf = str_replace("7 0 obj\n00000\nendobj\n", "7 0 obj\n{$encodedLength}\nendobj\n", $pdf);

$pdf .= "10 0 obj\n"
    . "<< /Type /XRef /Size 14 /Root 1 0 R /Index [0 11] /W [1 4 1] /Filter 8 0 R /DecodeParms 9 0 R /Length 7 0 R /Note (99 0 obj fake xref dictionary owner) >>\n"
    . "stream\n{$encodedRows}\nendstream\nendobj\n";

$addObject(7, 1, '1');
$addObject(8, 1, '/ASCIIHexDecode');
$addObject(9, 1, '<< /Predictor /Twelve /Columns 1 >>');
$addObject(1, 1, '<< /Type /Catalog /Pages 11 0 R /Note (stale latest-generation catalog) >>');
$addObject(11, 0, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
$addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 13 0 R >>');
$addObject(13, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$pdf .= "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-stream-dictionary-xref-owner-recovery-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref stream dictionaries resolve Length/Filter/DecodeParms helper object generations before current-base WordPress text extraction',
    'uses_current_xref_owner_stream_dictionary' => str_contains($plainText, 'Current xref owner stream dictionary'),
    'recovers_xref_stream_helper_owners' => str_contains($plainText, 'Recovered xref stream helpers'),
    'stale_xref_owner_page_excluded' => !str_contains($plainText, 'Stale xref owner stream dictionary leak'),
    'stale_helper_names_excluded' => !str_contains($plainText, 'ASCIIHexDecode') && !str_contains($plainText, 'Twelve'),
    'fake_xref_dictionary_owner_excluded' => !str_contains($plainText, '99 0 obj'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
