<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current nested-composite guard page) Tj ET';
$leakContent = 'BT /F1 12 Tf 72 700 Td (Nested object-stream dictionary leak) Tj T* (Composite member offset ignored) Tj ET';
$memberWithNestedPage = '<< /Type /Metadata /Private << /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (nested object-stream page decoy) >> /Tail (still inside metadata) >>';
$nestedPageOffset = strpos($memberWithNestedPage, '<< /Type /Page');
if ($nestedPageOffset === false) {
    throw new RuntimeException('Unable to locate nested object-stream page dictionary.');
}

$header = '12 0 4 ' . $nestedPageOffset;
$objectStreamPlain = $header . "\n" . $memberWithNestedPage . "\n";
$objectStream = gzcompress($objectStreamPlain);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress nested-composite object stream fixture.');
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
$addObject(5, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 1)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress nested-composite xref-stream fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'native_boundary' => 'PDF xref-stream type-2 object-stream offsets must start at object boundaries, not inside nested dictionaries or arrays',
    'current_import_kept' => $lines === ['Current nested-composite guard page'],
    'nested_composite_decoy_excluded' => !str_contains($plainText, 'Nested object-stream dictionary leak')
        && !str_contains($plainText, 'Composite member offset ignored')
        && !str_contains($plainText, 'nested object-stream page decoy'),
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
    'member_offset_token_boundary' => $entry['member_offset_token_boundary'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['current_import_kept', 'nested_composite_decoy_excluded'] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Nested composite object-stream offset smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['selection_policy'] ?? null) !== 'invalid_object_stream_member_offset') {
    throw new RuntimeException('Expected invalid object-stream nested composite member offset review.');
}

echo '<!-- markerpdf-xref-object-stream-nested-composite-offset-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
