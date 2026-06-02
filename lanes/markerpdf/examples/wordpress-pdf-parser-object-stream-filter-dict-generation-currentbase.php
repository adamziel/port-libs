<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objectStreamMembers = [
    20 => '<< /Review (Object stream dictionary filter member excluded) >>',
    21 => '<< /Review (Stale generation Flate filter ignored) >>',
];

$objectData = '';
$headerPairs = [];
$memberIndexes = [];
foreach ($objectStreamMembers as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerPairs);
$plainObjectStream = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($plainObjectStream, 0);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object-stream filter-dict smoke fixture.');
}

$safeContent = 'BT /F1 12 Tf 72 720 Td (Safe page before object stream filter dictionary) Tj ET';
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($safeContent) . " >>\nstream\n{$safeContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($objectStreamMembers)
    . ' /First ' . (strlen($header) + 1)
    . ' /Length ' . strlen($compressedObjectStream)
    . " /Filter 8 1 R >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(8, 0, '/FlateDecode');
$addObject(8, 1, '<< /Owner (Current object-stream Filter dictionary is not a decoder) /Name /FlateDecode >>');

$xrefRows = ''
    . chr(1) . pack('N', $offsets['1:0']) . chr(0)
    . chr(1) . pack('N', $offsets['2:0']) . chr(0)
    . chr(1) . pack('N', $offsets['3:0']) . chr(0)
    . chr(1) . pack('N', $offsets['4:0']) . chr(0)
    . chr(1) . pack('N', $offsets['5:0']) . chr(0)
    . chr(1) . pack('N', $offsets['6:0']) . chr(0)
    . chr(0) . pack('N', 0) . chr(0)
    . chr(1) . pack('N', $offsets['8:1']) . chr(1)
    . chr(2) . pack('N', 6) . chr($memberIndexes[20])
    . chr(2) . pack('N', 6) . chr($memberIndexes[21]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress object-stream filter-dict xref smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 8 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractObjectStreamStreamDictionaryGenerationReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf-parser-object-stream-filter-dict-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF object-stream Filter operands use current xref-selected generations but dictionary-shaped filters are rejected before WordPress import',
    'safe_page_visible' => str_contains($plainText, 'Safe page before object stream filter dictionary'),
    'object_stream_members_excluded' => !str_contains($plainText, 'Object stream dictionary filter member excluded')
        && !str_contains($plainText, 'Stale generation Flate filter ignored'),
    'filter_dictionary_text_excluded' => !str_contains($plainText, 'Current object-stream Filter dictionary is not a decoder'),
    'object_stream_review_count' => $review['object_stream_count'],
    'xref_selected_operand_count' => $review['xref_selected_operand_count'],
    'invalid_filter_operand_count' => $review['invalid_filter_operand_count'],
    'dictionary_filter_operand_count' => $review['dictionary_filter_operand_count'],
    'filter_resolution_failed' => $entry['filter_resolution_failed'] ?? false,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
