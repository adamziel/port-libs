<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current repaired carrier page) Tj T* (Type two row selected) Tj ET';
$staleFallbackContent = 'BT /F1 12 Tf 72 720 Td (Stale fallback stream leaked) Tj ET';

$memberBody = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (compressed page dictionary text excluded) >>';
$header = '4 0';
$objectStream = gzcompress($header . "\n" . $memberBody . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress current-carrier object stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, "<< /Length " . strlen($staleFallbackContent) . " >>\nstream\n{$staleFallbackContent}\nendstream");

$xrefRows = ''
    . $row(1, $offsets['1:0'], 0)
    . $row(1, $offsets['2:0'], 0)
    . $row(1, $offsets['3:0'], 0)
    . $row(2, 6, 0)
    . $row(1, $offsets['5:0'], 0)
    . $row(1, 1, 9);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress current-carrier xref stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current xref-stream type-2 member rows repair a damaged same-section direct ObjStm carrier row before WordPress paragraph extraction',
    'uses_current_repaired_carrier_page' => str_contains($plainText, 'Current repaired carrier page'),
    'uses_current_type2_member' => str_contains($plainText, 'Type two row selected'),
    'excludes_stale_fallback_stream' => !str_contains($plainText, 'Stale fallback stream leaked'),
    'excludes_compressed_member_metadata' => !str_contains($plainText, 'compressed page dictionary text excluded'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'object_stream' => $entry['object_stream'] ?? null,
    'object_stream_xref_entry_type' => $entry['object_stream_xref_entry_type'] ?? null,
    'object_stream_xref_generation' => $entry['object_stream_xref_generation'] ?? null,
    'object_stream_xref_offset_present' => ($entry['object_stream_xref_offset'] ?? 0) > 0,
    'object_stream_owner_policy' => $entry['object_stream_owner_policy'] ?? null,
    'page_count' => $outline['pages'],
];

foreach ([
    'uses_current_repaired_carrier_page',
    'uses_current_type2_member',
    'excludes_stale_fallback_stream',
    'excludes_compressed_member_metadata',
    'object_stream_xref_offset_present',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Current carrier xref repair smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['object_stream_owner_policy'] ?? null) !== 'xref_selected_object_stream_carrier') {
    throw new RuntimeException('Expected current repaired object-stream carrier to be xref-selected.');
}

echo '<!-- markerpdf-xref-object-stream-current-carrier-repair-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
