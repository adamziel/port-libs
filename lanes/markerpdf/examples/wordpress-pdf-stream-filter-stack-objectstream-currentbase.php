<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$directContent = 'BT /F1 12 Tf 72 720 Td (Direct object-stream boundary guard page) Tj ET';
$compressedContent = 'BT /F1 12 Tf 72 700 Td (Tailed object-stream compressed page leak) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$memberHeader = '4 0';
$compressedObjectStream = gzcompress($memberHeader . "\n" . $compressedPage . "\n");
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress object-stream filter stack smoke fixture.');
}

$tailedObjectStream = $ascii85Encode($compressedObjectStream)
    . '~>BT /F1 12 Tf 72 680 Td (Post EOD object-stream tail leak) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($memberHeader) + 1)
    . ' /Filter [ /ASCII85Decode /FlateDecode ] /Length ' . strlen($tailedObjectStream)
    . " >>\nstream\n{$tailedObjectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress object-stream filter stack xref smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

if ($lines !== ['Direct object-stream boundary guard page']) {
    throw new RuntimeException('Expected only the direct WordPress page text after object-stream EOD boundary rejection.');
}
if (str_contains($text, 'Tailed object-stream compressed page leak') || str_contains($text, 'Post EOD object-stream tail leak')) {
    throw new RuntimeException('Tailed object-stream payload leaked into WordPress visible text.');
}
if (($entry['object_stream_carrier_resolved'] ?? null) !== false || ($entry['invalid_object_stream_carrier_rejected'] ?? null) !== true) {
    throw new RuntimeException('Expected tailed object-stream carrier to be rejected before member expansion.');
}

echo '<!-- markerpdf-object-stream-filter-stack-boundary-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-object-stream-filter-stack-boundary',
    'upstream_boundary' => 'PDF object stream filters are decoded as bounded stacks before xref member expansion, matching pdftext/PDFium parser ownership boundaries',
    'native_boundary' => 'non-whitespace bytes after an object-stream filter EOD marker reject the carrier before compressed page members are imported',
    'direct_page_preserved' => $lines === ['Direct object-stream boundary guard page'],
    'tailed_object_stream_rejected' => ($entry['invalid_object_stream_carrier_rejected'] ?? null) === true,
    'compressed_page_text_excluded' => !str_contains($text, 'Tailed object-stream compressed page leak'),
    'post_eod_tail_text_excluded' => !str_contains($text, 'Post EOD object-stream tail leak'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . " -->\n";
echo '<p>Direct object-stream boundary guard page</p>' . "\n";
