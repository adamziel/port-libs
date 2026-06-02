<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current repaired filtered object stream page) Tj T* (RunLength carrier expanded after repair) Tj ET';
$fakeOwnerBoundary = "carrier bytes before fake owner boundary\nendstream\nendobj\nobject scanner decoy";

$members = [
    1 => '<< /Type /Catalog /Note (' . $fakeOwnerBoundary . ') /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
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
$plainObjectStream = $header . "\n" . $objectData;
$encodedObjectStream = $runLengthEncode($plainObjectStream);

$helperMembers = [
    30 => (string) strlen($encodedObjectStream),
    31 => '/RunLengthDecode',
];
$helperData = '';
$helperHeaderPairs = [];
$helperMemberIndexes = [];
foreach ($helperMembers as $objectNumber => $body) {
    $helperHeaderPairs[] = $objectNumber . ' ' . strlen($helperData);
    $helperMemberIndexes[$objectNumber] = count($helperMemberIndexes);
    $helperData .= $body . "\n";
}
$helperHeader = implode(' ', $helperHeaderPairs);
$helperObjectStream = $helperHeader . "\n" . $helperData;

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . ' /First ' . (strlen($header) + 1) . " /Length 30 0 R /Filter 31 0 R >>\nstream\n{$encodedObjectStream}\nendstream");
$addObject(7, 0, "<< /Type /ObjStm /N " . count($helperMembers) . ' /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperObjectStream) . " >>\nstream\n{$helperObjectStream}\nendstream");

$xrefRows = ''
    . chr(2) . pack('N', 6) . chr($memberIndexes[1])
    . chr(2) . pack('N', 6) . chr($memberIndexes[2])
    . chr(2) . pack('N', 6) . chr($memberIndexes[3])
    . chr(2) . pack('N', 6) . chr($memberIndexes[4])
    . chr(1) . pack('N', $offsets['5:0']) . chr(0)
    . chr(1) . pack('N', $offsets['6:0']) . chr(0)
    . chr(1) . pack('N', $offsets['7:0']) . chr(0)
    . chr(2) . pack('N', 7) . chr($helperMemberIndexes[30])
    . chr(2) . pack('N', 7) . chr($helperMemberIndexes[31]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 7 30 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-xref-objectstream-filter-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current xref-selected object streams are re-expanded after indirect RunLength filter and length operands repair their carrier boundary',
    'uses_current_repaired_object_stream_page' => str_contains($plainText, 'Current repaired filtered object stream page'),
    'expands_after_repair' => str_contains($plainText, 'RunLength carrier expanded after repair'),
    'excludes_object_scanner_decoy' => !str_contains($plainText, 'object scanner decoy'),
    'excludes_filter_operand_text' => !str_contains($plainText, 'RunLengthDecode'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
