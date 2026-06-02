<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$objectStream = static function (array $members, array &$memberIndexes): array {
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress object-stream smoke fixture.');
    }

    return [$header, $compressed];
};

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous compressed carrier page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current incremental guard page) Tj T* (Unlisted carrier ignored) Tj ET';
$replacementLeak = 'BT /F1 12 Tf 72 720 Td (Unlisted replacement object stream leak) Tj ET';

$previousMemberIndexes = [];
$replacementMemberIndexes = [];
[$previousHeader, $previousCompressed] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
], $previousMemberIndexes);
[$replacementHeader, $replacementCompressed] = $objectStream([
    4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (unlisted replacement carrier member) >>',
], $replacementMemberIndexes);

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
$addObject(5, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream");

$previousRows = ''
    . chr(1) . pack('N', $offsets['1:0']) . chr(0)
    . chr(1) . pack('N', $offsets['2:0']) . chr(0)
    . chr(1) . pack('N', $offsets['3:0']) . chr(0)
    . chr(2) . pack('N', 6) . chr($previousMemberIndexes[4])
    . chr(1) . pack('N', $offsets['5:0']) . chr(0);
$previousCompressedXref = gzcompress($previousRows);
if (!is_string($previousCompressedXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($replacementHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($replacementCompressed) . " >>\nstream\n{$replacementCompressed}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($replacementLeak) . " >>\nstream\n{$replacementLeak}\nendstream");

$currentRows = ''
    . chr(1) . pack('N', $offsets['1:1']) . chr(1)
    . chr(1) . pack('N', $offsets['2:1']) . chr(1)
    . chr(1) . pack('N', $offsets['8:0']) . chr(0)
    . chr(1) . pack('N', $offsets['9:0']) . chr(0)
    . chr(1) . pack('N', $offsets['10:0']) . chr(0);
$currentCompressedXref = gzcompress($currentRows);
if (!is_string($currentCompressedXref)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
    . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = $extractor->extractOutlineMetadata($pdf);

echo 'uses_current_incremental_guard_page=' . (str_contains($plainText, 'Current incremental guard page') ? 'true' : 'false') . "\n";
echo 'skips_unselected_previous_type2_row=' . (!str_contains($plainText, 'Previous compressed carrier page') ? 'true' : 'false') . "\n";
echo 'ignores_unlisted_replacement_object_stream=' . (!str_contains($plainText, 'Unlisted replacement object stream leak') ? 'true' : 'false') . "\n";
echo 'excludes_replacement_member_text=' . (!str_contains($plainText, 'unlisted replacement carrier member') ? 'true' : 'false') . "\n";
echo 'page_count=' . (string) ($metadata['pages'] ?? 0) . "\n";
echo "executes_python_or_models=false\n";
echo "executes_external_pdf_tools=false\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
