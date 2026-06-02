<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current startxref token page) Tj T* (Stream token ignored) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale stream-owned startxref page) Tj T* (Fake latest token leak) Tj ET';
$carrierContent = 'BT /F1 12 Tf 72 680 Td (Carrier stream startxref payload) Tj ET';

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
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0']);
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream"
);
$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$staleRows = ''
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0'])
    . $xrefRow(1, $offsets['11:0'])
    . $xrefRow(1, $offsets['12:0']);
$staleCompressed = gzcompress($staleRows);
if (!is_string($staleCompressed)) {
    throw new RuntimeException('Unable to compress stale xref-stream smoke fixture.');
}

$staleXrefOffset = $addObject(
    40,
    0,
    '<< /Type /XRef /Size 41 /Root 9 0 R /Index [9 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream"
);

$carrierPayload = $carrierContent . "\nstartxref\n{$staleXrefOffset}\n%%EOF\n";
$addObject(30, 0, "<< /Length " . strlen($carrierPayload) . " >>\nstream\n{$carrierPayload}\nendstream");

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

if (!str_contains($plainText, 'Current startxref token page') || !str_contains($plainText, 'Stream token ignored')) {
    throw new RuntimeException('Expected current xref-stream page text.');
}

if (
    str_contains($plainText, 'Stale stream-owned startxref page')
    || str_contains($plainText, 'Fake latest token leak')
    || str_contains($plainText, 'Carrier stream startxref payload')
) {
    throw new RuntimeException('Expected stream-owned startxref payload and stale xref stream to stay excluded.');
}

echo '<!-- markerpdf-parser-xref-stream-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF startxref tokens inside stream payloads cannot redirect current xref-stream page selection',
    'uses_current_startxref_token_page' => str_contains($plainText, 'Current startxref token page'),
    'stream_owned_startxref_token_ignored' => str_contains($plainText, 'Stream token ignored'),
    'excluded_stale_stream_owned_startxref_page' => !str_contains($plainText, 'Stale stream-owned startxref page'),
    'excluded_fake_latest_token_leak' => !str_contains($plainText, 'Fake latest token leak'),
    'carrier_payload_excluded' => !str_contains($plainText, 'Carrier stream startxref payload'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
