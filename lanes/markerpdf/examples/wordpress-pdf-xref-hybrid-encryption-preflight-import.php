<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous xref page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid current page) Tj T* (Free generation guard) Tj ET';
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n" . $xrefRow(0, 65535, 'f')
    . "1 2\n" . $xrefRow($offsets['1:0']) . $xrefRow($offsets['2:0'])
    . "4 2\n" . $xrefRow($offsets['4:0']) . $xrefRow($offsets['5:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>',
    8 => '<< /Type /Page /Parent 2 0 R /Contents 9 0 R >>',
];
$objectData = '';
$headerPairs = [];
$memberIndexes = [];
$memberIndex = 0;
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = $memberIndex;
    $objectData .= $body . "\n";
    $memberIndex++;
}
$objectStreamPlain = implode(' ', $headerPairs) . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPlain);

$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen(implode(' ', $headerPairs)) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefRows = ''
    . chr(2) . chr(6) . chr($memberIndexes[1])
    . chr(2) . chr(6) . chr($memberIndexes[2])
    . chr(0) . chr(0) . chr(1)
    . chr(2) . chr(6) . chr($memberIndexes[8]);
$compressedXref = gzcompress($xrefRows);
$xrefStreamOffset = strlen($pdf);
$addObject(7, 0, '<< /Type /XRef /Size 10 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 8 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream");

$latestXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "4 1\n" . $xrefRow(0, 1, 'f')
    . "6 2\n" . $xrefRow($offsets['6:0']) . $xrefRow($offsets['7:0'])
    . "9 1\n" . $xrefRow($offsets['9:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R /Prev {$previousXrefOffset} /XRefStm {$xrefStreamOffset} >>\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$encryptedContent = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';
$encryptedPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <00> /U <00> /P -4 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encryptedText = $extractor->extractPlainText($encryptedPdf);

echo '<!-- markerpdf-xref-hybrid-encryption-preflight-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'hybrid xref /Prev object-stream override, free-generation skip, and encrypted PDF fail-closed preflight',
    'uses_current_xref_stream_catalog' => str_contains($plainText, 'Hybrid current page'),
    'excluded_previous_free_page' => !str_contains($plainText, 'Stale previous xref page'),
    'encrypted_text_blocked' => $encryptedText === '',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
