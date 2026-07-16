<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current filtered fallback page) Tj T* (Object stream carrier excluded) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale filtered fallback page) Tj ET';
$carrierMember = '<< /Producer (WordPress import) >> BT /F1 12 Tf 72 680 Td (Filtered object stream carrier leak) Tj ET';

$currentCompressed = gzcompress($currentContent);
$staleCompressed = gzcompress($staleContent);
$carrierHeader = '12 0';
$carrierPlain = $carrierHeader . "\n" . $carrierMember . "\n";
$carrierCompressed = gzcompress($carrierPlain);
if (!is_string($currentCompressed) || !is_string($staleCompressed) || !is_string($carrierCompressed)) {
    throw new RuntimeException('Unable to compress parser object-stream owner-boundary smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, "<< /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream");
$addObject(2, 0, "<< /Type /ObjStm /N 1 /First " . (strlen($carrierHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($carrierCompressed) . " >>\nstream\n{$carrierCompressed}\nendstream");
$addObject(3, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
$addObject(4, 0, '<< /Type /Catalog /NeedsRendering false >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 5\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow(0, 1, 'f')
    . $xrefRow($offsets[4])
    . "trailer\n<< /Size 5 /Root 4 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-object-stream-filter-owner-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /ObjStm carrier streams are compressed-object containers, not fallback page content streams',
    'current_filtered_fallback_visible' => str_contains($plainText, 'Current filtered fallback page'),
    'object_stream_carrier_excluded' => !str_contains($plainText, 'Filtered object stream carrier leak'),
    'stale_free_stream_excluded' => !str_contains($plainText, 'Stale filtered fallback page'),
    'object_stream_member_metadata_excluded' => !str_contains($plainText, 'WordPress import'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
