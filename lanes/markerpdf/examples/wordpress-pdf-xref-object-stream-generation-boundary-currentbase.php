<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleCompressedContent = 'BT /F1 12 Tf 72 720 Td (Stale generation zero compressed page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current nonzero generation boundary page) Tj T* (Compressed member generation zero skipped) Tj ET';

$memberBody = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (compressed generation zero page object) >>';
$header = '4 0';
$objectStream = gzcompress($header . "\n" . $memberBody . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress object-stream generation-boundary smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 1 R 8 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefRows = ''
    . $row(1, $offsets['1:0'], 0)
    . $row(1, $offsets['2:0'], 0)
    . $row(1, $offsets['3:0'], 0)
    . $row(2, 6, 0)
    . $row(1, $offsets['5:0'], 0)
    . $row(1, $offsets['6:0'], 0)
    . $row(1, $offsets['8:0'], 0)
    . $row(1, $offsets['9:0'], 0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref generation-boundary smoke fixture.');
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

echo '<!-- markerpdf-xref-object-stream-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream type-2 object-stream members are generation zero and cannot satisfy nonzero indirect page references',
    'uses_current_nonzero_generation_boundary_page' => str_contains($plainText, 'Current nonzero generation boundary page'),
    'skips_generation_zero_compressed_member' => !str_contains($plainText, 'Stale generation zero compressed page'),
    'generation_boundary_policy' => $entry['generation_boundary_policy'] ?? null,
    'nonzero_referenced_generations' => $entry['nonzero_referenced_generations'] ?? [],
    'compressed_generation_zero_boundary_count' => $review['compressed_generation_zero_boundary_count'],
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
