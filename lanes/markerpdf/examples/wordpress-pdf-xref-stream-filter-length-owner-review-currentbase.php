<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref operand owner page) Tj T* (Filter length owners reviewed) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref operand owner page) Tj ET';

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
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$filterObjectOffset = $addObject(30, 0, '/FlateDecode');
$lengthObjectOffset = $addObject(31, 0, '0');

$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(0, 0, 1)
    . $xrefRow(0, 0, 1)
    . $xrefRow(0, 0, 1)
    . $xrefRow(0, 0, 1)
    . $xrefRow(1, $filterObjectOffset)
    . $xrefRow(1, $lengthObjectOffset);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref-stream operand owner smoke fixture.');
}

$pdf = substr($pdf, 0, $lengthObjectOffset)
    . "31 0 obj\n" . strlen($compressedXref) . "\nendobj\n"
    . substr($pdf, $lengthObjectOffset + strlen("31 0 obj\n0\nendobj\n"));

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 9 30 2] /W [1 4 1] /Filter 30 0 R /Length 31 0 R >>' . "\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];
$lengthOperand = $entry['length_operand'] ?? [];

echo '<!-- markerpdf-xref-stream-filter-length-owner-review-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream indirect Filter and Length operands are review-visible only when selected by the current xref stream',
    'uses_current_xref_operand_owner_page' => str_contains($plainText, 'Current xref operand owner page'),
    'filter_length_owners_reviewed' => str_contains($plainText, 'Filter length owners reviewed'),
    'excluded_stale_xref_operand_owner_page' => !str_contains($plainText, 'Stale xref operand owner page'),
    'xref_stream_count' => $review['xref_stream_count'],
    'indirect_filter_count' => $review['indirect_filter_count'],
    'indirect_length_count' => $review['indirect_length_count'],
    'xref_selected_operand_count' => $review['xref_selected_operand_count'],
    'filter_owner_policy' => $filterOperand['owner_policy'] ?? null,
    'length_owner_policy' => $lengthOperand['owner_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
