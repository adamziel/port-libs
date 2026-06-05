<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Xref stream previous destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Xref stream previous appendix page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(5, 0, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
$addObject(6, 0, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
$addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(8, 0, '<< /Names [(Prev Start) [3 0 R /FitH 700] (Prev Appendix) 9 0 R] >>');
$addObject(9, 0, '<< /D [4 0 R /XYZ 72 640 0] >>');

$previousRows = '';
for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
    if ($objectNumber === 0) {
        $previousRows .= $xrefRow(0, 0, 255);
        continue;
    }

    $key = $objectNumber . ':0';
    $previousRows .= isset($offsets[$key])
        ? $xrefRow(1, $offsets[$key])
        : $xrefRow(0, 0);
}

$previousXref = gzcompress($previousRows);
if (!is_string($previousXref)) {
    throw new RuntimeException('Unable to compress previous named-destination xref stream fixture.');
}

$previousXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 10 /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousXref) . " >>\n"
    . "stream\n{$previousXref}\nendstream\nendobj\n";

$addObject(8, 0, '<< /Names [(Stale Fallback Start) [4 0 R /FitH 111] (Stale Fallback Dict) 9 0 R] >>');
$addObject(9, 0, '<< /D [3 0 R /FitV 22] >>');
$addObject(10, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyPrev [4 0 R /FitV 96] >> >>');

$currentRows = $xrefRow(0, 0, 255) . $xrefRow(1, $offsets['10:0']);
$currentXref = gzcompress($currentRows);
if (!is_string($currentXref)) {
    throw new RuntimeException('Unable to compress current named-destination xref stream fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Index [0 1 10 1] /Root 10 0 R /Prev ' . $previousXrefOffset
    . ' /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXref) . " >>\n"
    . "stream\n{$currentXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Prev Start', 'Prev Appendix', 'LegacyPrev']) {
    throw new RuntimeException('Expected xref-stream /Prev destinations before fallback duplicate bodies.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Fallback Start')
    || str_contains($encoded, 'Stale Fallback Dict')
    || str_contains($encoded, '111')
    || str_contains($encoded, '22')
) {
    throw new RuntimeException('Expected stale unselected xref-stream fallback destinations to stay out of review metadata.');
}
if (!str_contains($plainText, 'Xref stream previous destination page')
    || !str_contains($plainText, 'Xref stream previous appendix page')
    || str_contains($plainText, 'Prev Start')
    || str_contains($plainText, 'LegacyPrev')
) {
    throw new RuntimeException('Expected visible page text without named-destination metadata leakage.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream /Prev chains select named-destination objects before fallback duplicate object scanning',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'prev_xref_stream_destinations_preserved' => true,
    'stale_fallback_destination_objects_excluded' => true,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Prev Start')
        && !str_contains($plainText, 'Prev Appendix')
        && !str_contains($plainText, 'LegacyPrev'),
];

echo '<!-- markerpdf-pdf-named-destination-xref-stream-prev-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $metadata = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($metadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
