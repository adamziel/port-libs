<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
        throw new RuntimeException('Unable to compress bundled object-stream smoke fixture.');
    }

    return [$header, $compressed];
};

$staleMemberContent = 'BT /F1 12 Tf 72 720 Td (Stale bundled compressed member page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current bundled object stream page) Tj T* (Free generation member suppressed) Tj ET';

$staleMemberIndexes = [];
$currentMemberIndexes = [];
[$staleHeader, $staleObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale previous bundled member) >>',
], $staleMemberIndexes);
[$currentHeader, $currentObjectStream] = $objectStream([
    10 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 11 0 R /Note (current rebuilt bundled member) >>',
], $currentMemberIndexes);

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
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleMemberContent) . " >>\nstream\n{$staleMemberContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleObjectStream) . " >>\nstream\n{$staleObjectStream}\nendstream");

$previousRows = ''
    . $row(1, $offsets['1:0'], 0)
    . $row(1, $offsets['2:0'], 0)
    . $row(1, $offsets['3:0'], 0)
    . $row(2, 6, $staleMemberIndexes[4])
    . $row(1, $offsets['5:0'], 0)
    . $row(1, $offsets['6:0'], 0);
$previousXrefStream = gzcompress($previousRows);
if (!is_string($previousXrefStream)) {
    throw new RuntimeException('Unable to compress previous bundled xref smoke stream.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousXrefStream) . " >>\nstream\n{$previousXrefStream}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [10 0 R 4 0 R] /Count 2 >>');
$addObject(6, 1, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
$addObject(11, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $row(1, $offsets['1:1'], 1)
    . $row(1, $offsets['2:1'], 1)
    . $row(0, 4, 1)
    . $row(2, 6, $currentMemberIndexes[10])
    . $row(1, $offsets['11:0'], 0);
$currentXrefStream = gzcompress($currentRows);
if (!is_string($currentXrefStream)) {
    throw new RuntimeException('Unable to compress current bundled xref smoke stream.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefStream) . " >>\n"
    . "stream\n{$currentXrefStream}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);
$review = $extractor->extractXrefPrevObjectStreamGenerationReview($pdf);
$entry = $review['entries'][0] ?? [];

echo '<!-- markerpdf-xref-object-stream-prev-free-generation-bundle-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current xref-stream free generation rows suppress stale Prev object-stream members while current type-2 rows rebuild the object-stream carrier',
    'uses_current_bundled_object_stream_page' => str_contains($plainText, 'Current bundled object stream page'),
    'suppresses_stale_prev_member' => !str_contains($plainText, 'Stale bundled compressed member page'),
    'excludes_object_stream_payload_text' => !str_contains($plainText, 'current rebuilt bundled member') && !str_contains($plainText, 'stale previous bundled member'),
    'skipped_current_free_object_count' => $review['skipped_current_free_object_count'],
    'owner_policy' => $entry['owner_policy'] ?? null,
    'current_object_generation' => $entry['current_object_generation'] ?? null,
    'object_stream' => $entry['object_stream'] ?? null,
    'page_count' => $outline['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
