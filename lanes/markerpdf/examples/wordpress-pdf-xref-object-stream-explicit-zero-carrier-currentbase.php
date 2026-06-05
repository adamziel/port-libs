<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current explicit-zero carrier guard page) Tj T* (Explicit invalid carrier row stays review-only) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 700 Td (Stale explicit-zero carrier page leak) Tj T* (Explicit object stream zero must fail closed) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (explicit zero carrier should not recover this member) >>';
$header = '4 0';
$objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress explicit-zero carrier object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale direct page must be suppressed) >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($offsets['8:0'])
    . $xrefTableRow($offsets['9:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$xrefRows = $xrefStreamRow(2, 0, 0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress explicit-zero carrier xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 rows with explicit object-stream carrier 0 are kept as invalid current rows and fail closed before WordPress text import',
    'uses_current_guard_page' => str_contains($plainText, 'Current explicit-zero carrier guard page'),
    'stale_direct_page_suppressed' => !str_contains($plainText, 'Stale explicit-zero carrier page leak'),
    'stale_object_stream_member_suppressed' => !str_contains($plainText, 'explicit zero carrier should not recover this member'),
    'zero_width_object_stream_entry_count' => $review['zero_width_object_stream_entry_count'],
    'unresolved_object_stream_carrier_count' => $review['unresolved_object_stream_carrier_count'],
    'invalid_explicit_object_stream_carrier_count' => $review['invalid_explicit_object_stream_carrier_count'] ?? 0,
    'object_stream_field_is_explicit' => ($entry['object_stream_field_is_explicit'] ?? null) === true,
    'object_stream_field_is_zero_width' => ($entry['object_stream_field_is_zero_width'] ?? null) === true,
    'invalid_object_stream_carrier_rejected' => ($entry['invalid_object_stream_carrier_rejected'] ?? null) === true,
    'object_stream_owner_policy' => $entry['object_stream_owner_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

foreach ([
    'uses_current_guard_page',
    'stale_direct_page_suppressed',
    'stale_object_stream_member_suppressed',
    'object_stream_field_is_explicit',
    'invalid_object_stream_carrier_rejected',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Explicit-zero carrier smoke failed: ' . $requiredFlag);
    }
}
if ($smoke['object_stream_field_is_zero_width'] !== false) {
    throw new RuntimeException('Explicit-zero carrier smoke should not report zero-width carrier ownership.');
}
if ($smoke['invalid_explicit_object_stream_carrier_count'] !== 1) {
    throw new RuntimeException('Expected one invalid explicit object-stream carrier row.');
}
if ($smoke['object_stream_owner_policy'] !== 'invalid_explicit_object_stream_carrier') {
    throw new RuntimeException('Expected invalid explicit object-stream carrier owner policy.');
}

echo '<!-- markerpdf-xref-object-stream-explicit-zero-carrier-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
