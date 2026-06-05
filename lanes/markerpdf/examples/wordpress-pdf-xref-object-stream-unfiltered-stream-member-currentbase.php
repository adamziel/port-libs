<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$directContent = 'BT /F1 12 Tf 72 720 Td (Current unfiltered stream-member guard page) Tj ET';
$compressedContent = 'BT /F1 12 Tf 72 700 Td (Unfiltered stream member leak) Tj T* (Object-stream stream member rejected) Tj ET';

$compressedStreamObject = "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream";
$header = '5 0';
$objectStream = $header . "\n" . $compressedStreamObject . "\n";

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
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents [9 0 R 5 0 R] >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress unfiltered stream-member xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 2 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
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
    'native_boundary' => 'unfiltered PDF object streams still reject top-level stream-object members before WordPress paragraph extraction',
    'uses_current_direct_guard_page' => str_contains($plainText, 'Current unfiltered stream-member guard page'),
    'object_stream_carrier_unfiltered' => ($entries[5]['object_stream_carrier_has_filter'] ?? null) === false,
    'rejects_unfiltered_stream_object_member' => ($entries[5]['stream_member_rejected'] ?? false) === true,
    'stream_member_rejection_count' => $review['stream_member_rejection_count'] ?? null,
    'excludes_unfiltered_stream_member_text' => !str_contains($plainText, 'Unfiltered stream member leak'),
    'excludes_stream_member_payload_text' => !str_contains($plainText, 'Object-stream stream member rejected'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

foreach ([
    'uses_current_direct_guard_page',
    'object_stream_carrier_unfiltered',
    'rejects_unfiltered_stream_object_member',
    'excludes_unfiltered_stream_member_text',
    'excludes_stream_member_payload_text',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Unfiltered object-stream stream-member smoke failed: ' . $requiredFlag);
    }
}

echo '<!-- markerpdf-xref-object-stream-unfiltered-stream-member-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
