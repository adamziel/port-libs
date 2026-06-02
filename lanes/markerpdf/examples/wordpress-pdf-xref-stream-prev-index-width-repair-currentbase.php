<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale malformed index width repair page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed Index width repair page) Tj T* (Current offset owners repaired) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body, string $tag) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$tag] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRows = static function (array $offsets): string {
    $rows = '';
    foreach ($offsets as $offset) {
        $rows .= pack('N', $offset);
    }

    return $rows;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>', 'prev-1');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', 'prev-2');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', 'prev-3');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 'font-4');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream", 'prev-5');

$previousCompressed = gzcompress($xrefRows([
    $offsets['prev-1'],
    $offsets['prev-2'],
    $offsets['prev-3'],
    $offsets['font-4'],
    $offsets['prev-5'],
]));
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream repair smoke fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream",
    'prev-xref'
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>', 'current-1');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', 'current-2');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', 'current-3');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream", 'current-5');

$currentCompressed = gzcompress($xrefRows([
    $offsets['current-1'],
    $offsets['current-2'],
    $offsets['current-3'],
    $offsets['current-5'],
]));
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream repair smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [30 2 42 2] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-prev-index-width-repair-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream /Prev chain with malformed sparse /Index and zero-width /W repairs current direct-object offsets before stale previous rows',
    'uses_current_index_width_repair_page' => str_contains($plainText, 'Current malformed Index width repair page'),
    'repairs_current_offset_owners' => str_contains($plainText, 'Current offset owners repaired'),
    'excluded_stale_index_width_repair_page' => !str_contains($plainText, 'Stale malformed index width repair page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
