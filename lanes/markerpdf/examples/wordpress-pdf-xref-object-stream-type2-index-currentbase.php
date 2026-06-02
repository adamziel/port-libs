<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current type-2 index page) Tj T* (Object stream base preserved) Tj ET';
$orphanFallbackContent = 'BT /F1 12 Tf 72 720 Td (Stale orphan fallback stream) Tj ET';

$currentMembers = [
    12 => '<< /Type /Page /Parent 2 0 R /Note (decoy first object stream member) >>',
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];
$currentObjectData = '';
$currentHeaderPairs = [];
$currentMemberIndexes = [];
foreach ($currentMembers as $objectNumber => $body) {
    $currentHeaderPairs[] = $objectNumber . ' ' . strlen($currentObjectData);
    $currentMemberIndexes[$objectNumber] = count($currentMemberIndexes);
    $currentObjectData .= $body . "\n";
}
$currentHeader = implode(' ', $currentHeaderPairs);
$compressedCurrentObjectStream = gzcompress($currentHeader . "\n" . $currentObjectData);
if (!is_string($compressedCurrentObjectStream)) {
    throw new RuntimeException('Unable to compress current object-stream smoke fixture.');
}

$malformedMembers = [
    6 => '<< /Type /ObjStm /Note (malformed compressed base decoy) >>',
];
$malformedObjectData = '';
$malformedHeaderPairs = [];
$malformedMemberIndexes = [];
foreach ($malformedMembers as $objectNumber => $body) {
    $malformedHeaderPairs[] = $objectNumber . ' ' . strlen($malformedObjectData);
    $malformedMemberIndexes[$objectNumber] = count($malformedMemberIndexes);
    $malformedObjectData .= $body . "\n";
}
$malformedHeader = implode(' ', $malformedHeaderPairs);
$compressedMalformedObjectStream = gzcompress($malformedHeader . "\n" . $malformedObjectData);
if (!is_string($compressedMalformedObjectStream)) {
    throw new RuntimeException('Unable to compress malformed object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($currentMembers) . ' /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedCurrentObjectStream) . " >>\nstream\n{$compressedCurrentObjectStream}\nendstream");
$addObject(7, 0, '<< /Type /ObjStm /N ' . count($malformedMembers) . ' /First ' . (strlen($malformedHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedMalformedObjectStream) . " >>\nstream\n{$compressedMalformedObjectStream}\nendstream");
$addObject(8, 0, "<< /Length " . strlen($orphanFallbackContent) . " >>\nstream\n{$orphanFallbackContent}\nendstream");

$xrefRows = ''
    . chr(1) . pack('N', $offsets['1:0']) . chr(0)
    . chr(1) . pack('N', $offsets['2:0']) . chr(0)
    . chr(1) . pack('N', $offsets['3:0']) . chr(0)
    . chr(2) . pack('N', 6) . chr($currentMemberIndexes[4])
    . chr(1) . pack('N', $offsets['5:0']) . chr(0)
    . chr(2) . pack('N', 7) . chr($malformedMemberIndexes[6])
    . chr(1) . pack('N', $offsets['7:0']) . chr(0)
    . chr(1) . pack('N', $offsets['8:0']) . chr(0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 8] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-object-stream-type2-index-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 member indexes cannot hide the current direct ObjStm base object',
    'uses_current_type2_index_page' => str_contains($plainText, 'Current type-2 index page'),
    'preserves_direct_object_stream_base' => str_contains($plainText, 'Object stream base preserved'),
    'excluded_decoy_first_member' => !str_contains($plainText, 'decoy first object stream member'),
    'excluded_malformed_compressed_base' => !str_contains($plainText, 'malformed compressed base decoy'),
    'excluded_orphan_fallback_stream' => !str_contains($plainText, 'Stale orphan fallback stream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
