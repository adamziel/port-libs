<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /Fcid 12 Tf 72 720 Td <004F0062006A006500630074002000730074007200650061006D00200070006100670065> Tj T* /Fplain 12 Tf (Plain Direct Font) Tj ET';
$phantom = 'BT /Fplain 12 Tf 72 720 Td (Phantom stale object stream text) Tj ET';
$members = [
    1 => '<< /Type /Catalog /Pages 10 0 R >>',
    10 => '<< /Type /Page /Contents 9 0 R >>',
    11 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /Fplain 8 0 R >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type0 /BaseFont /ObjectStreamIdentity /Encoding /Identity-H >>',
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

$xrefRows = '';
foreach ([11, 2, 3, 4] as $objectNumber) {
    $xrefRows .= chr(2) . chr(6) . chr($memberIndexes[$objectNumber]);
}
$compressedXref = gzcompress($xrefRows);

$pdf = "%PDF-1.5\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /ObjStm /N " . count($members) . " /First " . (strlen(implode(' ', $headerPairs)) + 1) . " /Filter /FlateDecode /Length " . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($phantom) . " >>\nstream\n{$phantom}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XRef /Size 12 /Root 11 0 R /Index [11 1 2 3] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\nstream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n0\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-object-stream-xref-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 object-stream and xref-stream object recovery before Gutenberg paragraph rendering',
    'excluded_stale_compressed_object' => !str_contains($plainText, 'Phantom stale object stream text'),
    'preserved_identity_font_text' => !str_contains($plainText, "\0"),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
