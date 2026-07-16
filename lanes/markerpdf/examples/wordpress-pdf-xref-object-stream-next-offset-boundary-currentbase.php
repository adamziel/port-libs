<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$compressedContent = 'BT /F1 12 Tf 72 720 Td (Current next-offset compressed page) Tj T* (Bad later member offset ignored) Tj ET';
$directContent = 'BT /F1 12 Tf 72 700 Td (Direct guard page after compressed member) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 680 Td (Malformed next-offset member leak) Tj ET';

$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (object stream next-offset boundary decoy) >>';
$contentsOffset = strpos($compressedPage, 'Contents');
$badOffset = $contentsOffset === false ? false : $contentsOffset + 3;
if ($badOffset === false) {
    throw new RuntimeException('Unable to locate next-offset decoy inside compressed page member.');
}
$decoyPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (malformed member must stay rejected) >>';
$objectData = $compressedPage . "\n" . $decoyPage . "\n";
$header = '4 0 12 ' . $badOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress next-offset object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R 12 0 R] /Count 3 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0'])
    . $xrefRow(2, 6, 1);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress next-offset xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'object-stream member end offsets ignore malformed later header rows that point inside an earlier page member',
    'preserves_current_compressed_page' => str_contains($plainText, 'Current next-offset compressed page'),
    'preserves_current_compressed_page_followup_line' => str_contains($plainText, 'Bad later member offset ignored'),
    'preserves_direct_guard_page' => str_contains($plainText, 'Direct guard page after compressed member'),
    'excludes_malformed_later_member_stream' => !str_contains($plainText, 'Malformed next-offset member leak'),
    'excludes_malformed_later_member_dictionary' => !str_contains($plainText, 'malformed member must stay rejected'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'],
    'valid_member_selection_policy' => $entries[4]['selection_policy'] ?? null,
    'invalid_member_selection_policy' => $entries[12]['selection_policy'] ?? null,
    'invalid_member_offset_rejected' => $entries[12]['invalid_member_offset_rejected'] ?? null,
];

foreach ([
    'preserves_current_compressed_page',
    'preserves_current_compressed_page_followup_line',
    'preserves_direct_guard_page',
    'excludes_malformed_later_member_stream',
    'excludes_malformed_later_member_dictionary',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Object-stream next-offset smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['valid_member_selection_policy'] ?? null) !== 'explicit_member_index'
    || ($smoke['invalid_member_selection_policy'] ?? null) !== 'invalid_object_stream_member_offset'
    || ($smoke['invalid_member_offset_rejected'] ?? null) !== true
) {
    throw new RuntimeException('Expected malformed later object-stream member offset to stay rejected.');
}

echo '<!-- markerpdf-xref-object-stream-next-offset-boundary-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
