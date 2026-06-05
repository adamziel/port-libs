<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$guardContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-object guard page) Tj ET';
$firstDuplicateContent = 'BT /F1 12 Tf 72 700 Td (Duplicate object first member leak) Tj ET';
$secondDuplicateContent = 'BT /F1 12 Tf 72 680 Td (Duplicate object selected member leak) Tj ET';

$firstDuplicatePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$secondDuplicatePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>';
$objectData = $firstDuplicatePage . "\n" . $secondDuplicatePage . "\n";
$secondOffset = strlen($firstDuplicatePage . "\n");
$header = '4 0 4 ' . $secondOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress duplicate-object-number object stream smoke fixture.');
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
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($firstDuplicateContent) . " >>\nstream\n{$firstDuplicateContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($secondDuplicateContent) . " >>\nstream\n{$secondDuplicateContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 1)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress duplicate-object-number xref stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-selected object stream members with duplicate header object numbers are rejected before WordPress paragraph extraction',
    'uses_current_guard_page' => str_contains($plainText, 'Current duplicate-object guard page'),
    'rejects_first_duplicate_member' => !str_contains($plainText, 'Duplicate object first member leak'),
    'rejects_xref_selected_duplicate_member' => !str_contains($plainText, 'Duplicate object selected member leak'),
    'duplicate_header_object_number_rejection_count' => $review['duplicate_header_object_number_rejection_count'] ?? null,
    'matching_header_object_number_count' => $entry['matching_header_object_number_count'] ?? null,
    'duplicate_header_object_number_rejected' => $entry['duplicate_header_object_number_rejected'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
    'page_count' => $outline['pages'],
];

foreach ([
    'uses_current_guard_page',
    'rejects_first_duplicate_member',
    'rejects_xref_selected_duplicate_member',
    'duplicate_header_object_number_rejected',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Duplicate object-stream object-number smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['duplicate_header_object_number_rejection_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected one duplicate object-stream header object-number rejection.');
}

if (($smoke['selection_policy'] ?? null) !== 'duplicate_header_object_number') {
    throw new RuntimeException('Expected duplicate header object-number selection policy.');
}

echo '<!-- markerpdf-xref-object-stream-duplicate-object-number-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
