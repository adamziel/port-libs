<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$makePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace XRefStm object-stream page) Tj T* (Hybrid xref stream rows parsed) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress whitespace-XRefStm object-stream smoke fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = ''
        . $xrefStreamRow(2, 6, $memberIndexes[1])
        . $xrefStreamRow(2, 6, $memberIndexes[2])
        . $xrefStreamRow(1, $offsets['3:0'])
        . $xrefStreamRow(2, 6, $memberIndexes[4])
        . $xrefStreamRow(1, $offsets['5:0'])
        . $xrefStreamRow(1, $offsets['6:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress whitespace-XRefStm xref-stream smoke fixture.');
    }

    $xrefStreamWhitespaceOffset = strlen($pdf);
    $pdf .= "\n  \t";
    $xrefStreamObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefTableRow(0, 65535, 'f')
        . "3 1\n"
        . $xrefTableRow($offsets['3:0'])
        . "5 2\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . "20 1\n"
        . $xrefTableRow($xrefStreamObjectOffset)
        . "trailer\n<< /Size 21 /Root 1 0 R /XRefStm {$xrefStreamWhitespaceOffset} >>\n"
        . "startxref\n{$xrefTableOffset}\n%%EOF";

    return $pdf;
};

$assertSmoke = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$extractor = new PdfTextExtractor();
$pdf = $makePdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = $extractor->extractOutlineMetadata($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

$expectedLines = [
    'Current whitespace XRefStm object-stream page',
    'Hybrid xref stream rows parsed',
];

$assertSmoke($lines === $expectedLines, 'Hybrid XRefStm smoke did not recover expected object-stream text lines.');
$assertSmoke(($metadata['pages'] ?? 0) === 1, 'Hybrid XRefStm smoke did not recover a one-page page tree.');
$assertSmoke(($review['compressed_entry_count'] ?? null) === 3, 'Hybrid XRefStm smoke did not report three compressed xref entries.');
$assertSmoke(($entries[4]['selection_policy'] ?? null) === 'explicit_member_index', 'Hybrid XRefStm smoke did not keep the explicit member index for page object 4.');
$assertSmoke(($entries[4]['object_stream_owner_policy'] ?? null) === 'xref_selected_object_stream_carrier', 'Hybrid XRefStm smoke did not bind page object 4 to the selected object-stream carrier.');
$assertSmoke(($review['executes_python_or_models'] ?? true) === false, 'Hybrid XRefStm smoke unexpectedly required Python or models.');
$assertSmoke(($review['executes_external_pdf_tools'] ?? true) === false, 'Hybrid XRefStm smoke unexpectedly required external PDF tools.');
$assertSmoke(!str_contains($plainText, "\0"), 'Hybrid XRefStm smoke leaked binary bytes into extracted text.');

echo '<!-- markerpdf-xref-hybrid-xrefstm-whitespace-object-stream-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'classic table /XRefStm offsets may point to normal PDF whitespace before the xref stream object',
    'uses_whitespace_normalized_xrefstm_offset' => true,
    'compressed_entry_count' => $review['compressed_entry_count'],
    'unresolved_object_stream_carrier_count' => $review['unresolved_object_stream_carrier_count'],
    'page_member_selection_policy' => $entries[4]['selection_policy'] ?? 'missing',
    'page_member_owner_policy' => $entries[4]['object_stream_owner_policy'] ?? 'missing',
    'page_count' => $metadata['pages'] ?? 0,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
