<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid carrier generation zero page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid carrier generation page) Tj T* (Object stream owner generation one) Tj ET';

$objectStream = static function (array $members, array &$memberIndexes): array {
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress hybrid generation-owner smoke object stream.');
    }

    return [$header, $compressed];
};

$staleMemberIndexes = [];
$currentMemberIndexes = [];
[$staleHeader, $staleObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale generation zero carrier member) >>',
], $staleMemberIndexes);
[$currentHeader, $currentObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (current generation one carrier member) >>',
], $currentMemberIndexes);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleObjectStream) . " >>\nstream\n{$staleObjectStream}\nendstream");
$addObject(6, 1, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$hybridRows = $xrefStreamRow(2, 6, $currentMemberIndexes[4]);
$hybridXrefStream = gzcompress($hybridRows);
if (!is_string($hybridXrefStream)) {
    throw new RuntimeException('Unable to compress hybrid generation-owner smoke xref stream.');
}
$hybridXrefOffset = $addObject(
    7,
    0,
    '<< /Type /XRef /Size 10 /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($hybridXrefStream) . " >>\nstream\n{$hybridXrefStream}\nendstream"
);

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 4\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . "5 3\n"
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:1'], 1)
    . $xrefTableRow($hybridXrefOffset)
    . "9 1\n"
    . $xrefTableRow($offsets['9:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf-xref-object-stream-hybrid-generation-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'hybrid xref table generation rows own the object-stream carrier before companion type-2 members are expanded',
    'uses_current_hybrid_carrier_generation_page' => str_contains($plainText, 'Current hybrid carrier generation page'),
    'reports_object_stream_selected_generation' => ($entry['object_stream_selected_generation'] ?? null) === 1,
    'reports_object_stream_owner_policy' => ($entry['object_stream_owner_policy'] ?? null) === 'xref_selected_object_stream_carrier',
    'excludes_stale_carrier_generation_zero_page' => !str_contains($plainText, 'Stale hybrid carrier generation zero page'),
    'excludes_object_stream_payload_text' => !str_contains($plainText, 'current generation one carrier member'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
