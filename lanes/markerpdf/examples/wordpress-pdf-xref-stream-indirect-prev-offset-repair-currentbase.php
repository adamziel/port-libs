<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev offset repair page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev offset repair page) Tj T* (Compressed Prev helper repaired current rows) Tj ET';

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
    $plain = $header . "\n" . $objectData;
    $compressed = gzcompress($plain);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress indirect Prev offset-repair smoke object stream.');
    }

    return [$header, $compressed];
};

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$classicRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $classicRow(0, 65535, 'f')
    . $classicRow($offsets['1:0'])
    . $classicRow($offsets['2:0'])
    . $classicRow($offsets['3:0'])
    . $classicRow($offsets['4:0'])
    . $classicRow($offsets['5:0'])
    . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Note (current catalog after Prev) >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$helperMemberIndexes = [];
[$helperHeader, $helperCompressed] = $objectStream([
    9000 => (string) $previousXrefOffset,
], $helperMemberIndexes);
$addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($helperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($helperCompressed) . " >>\nstream\n{$helperCompressed}\nendstream");
$addObject(10, 0, '<< /Type /Font /Subtype /Type3 /CharProcs << /A 5 0 R >> /Encoding << /Type /Encoding /Differences [65 /A] >> /FirstChar 65 /LastChar 65 /Widths [500] >>');

$currentXrefRows = ''
    . $xrefRow(1, 0, 0)
    . $xrefRow(1, 0, 0)
    . $xrefRow(1, 0, 0)
    . $xrefRow(1, $offsets['4:0'], 0)
    . $xrefRow(1, 0, 0)
    . $xrefRow(1, $offsets['7:0'], 0)
    . $xrefRow(1, $offsets['10:0'], 0)
    . $xrefRow(2, 7, $helperMemberIndexes[9000]);
$compressedCurrentXref = gzcompress($currentXrefRows);
if (!is_string($compressedCurrentXref)) {
    throw new RuntimeException('Unable to compress current indirect Prev offset-repair smoke xref stream.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 9001 /Root 1 0 R /Index [1 7 9000 1] /W [1 4 1] /Prev 9000 0 R /Filter /FlateDecode /Length ' . strlen($compressedCurrentXref) . " >>\n"
    . "stream\n{$compressedCurrentXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-stream-indirect-prev-offset-repair-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current xref-stream damaged same-generation rows are repaired only after /Prev resolves from a compressed helper object',
    'uses_current_repaired_page' => $lines === [
        'Current indirect Prev offset repair page',
        'Compressed Prev helper repaired current rows',
    ],
    'recovers_prev_xref_offset_from_high_object_number_helper' => ($entries[9000]['object_stream'] ?? null) === 7,
    'high_object_number_not_misread_as_prev_offset' => $currentXrefOffset < 9000,
    'excludes_stale_prev_page' => !str_contains($plainText, 'Stale indirect Prev offset repair page'),
    'excludes_charproc_fallback_scan' => !str_contains($plainText, 'CharProcs'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
