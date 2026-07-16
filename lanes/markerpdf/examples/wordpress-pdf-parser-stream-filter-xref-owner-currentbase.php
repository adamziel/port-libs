<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$leakingContent = 'BT /F1 12 Tf 72 720 Td (Mismatched filter generation leak) Tj ET';
$visibleContent = 'BT /F1 12 Tf 72 680 Td (Safe current direct page) Tj ET';
$compressed = gzcompress($leakingContent);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress filter-generation owner smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>');
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(20, 0, '/ASCIIHexDecode');
$addObject(20, 1, '/FlateDecode');
$addObject(4, 0, "<< /Filter 20 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream");
$addObject(6, 0, "<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 21\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 20; $objectNumber++) {
    if ($objectNumber === 20) {
        $pdf .= $xrefRow($offsets['20:1'], 1);
        continue;
    }

    $pdf .= isset($offsets[$objectNumber . ':0'])
        ? $xrefRow($offsets[$objectNumber . ':0'])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 21 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-stream-filter-xref-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF stream filter indirect references must match the current xref-selected generation before decoding',
    'stale_generation_filter_rejected' => !str_contains($plainText, 'Mismatched filter generation leak'),
    'current_direct_stream_preserved' => str_contains($plainText, 'Safe current direct page'),
    'filter_helper_generation_mismatch' => '20 0 R rejected while current xref selects 20 1',
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
