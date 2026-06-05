<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Object stream destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Object stream appendix page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(5, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
$addObject(6, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
$addObject(7, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$compressedMembers = [
    1 => '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R /Dests << /LegacyCompressed [4 0 R /FitV 96] >> >>',
    8 => '<< /Dests 9 0 R >>',
    9 => '<< /Names [(Compressed Start) [3 0 R /FitH 700] (Compressed Appendix) 10 0 R] >>',
    10 => '<< /D [4 0 R /XYZ 72 640 0] >>',
];
$objectStreamData = '';
$objectStreamHeaderPairs = [];
$memberIndexes = [];
foreach ($compressedMembers as $objectNumber => $body) {
    $objectStreamHeaderPairs[] = $objectNumber . ' ' . strlen($objectStreamData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectStreamData .= $body . "\n";
}
$objectStreamHeader = implode(' ', $objectStreamHeaderPairs) . ' ';
$objectStreamBytes = $objectStreamHeader . $objectStreamData;
$compressedObjectStream = gzcompress($objectStreamBytes);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress named-destination object stream fixture.');
}
$addObject(20, '<< /Type /ObjStm /N ' . count($compressedMembers) . ' /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyStale [3 0 R /FitH 111] >> >>');
$addObject(8, '<< /Dests 9 0 R >>');
$addObject(9, '<< /Names [(Stale Direct Start) [4 0 R /FitH 222] (Stale Direct Appendix) 10 0 R] >>');
$addObject(10, '<< /D [3 0 R /FitV 33] >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if (isset($memberIndexes[$objectNumber])) {
        $rows .= $xrefRow(2, 20, $memberIndexes[$objectNumber]);
        continue;
    }
    if ($objectNumber === 21) {
        $rows .= $xrefRow(1, $xrefOffset);
        continue;
    }
    if (isset($offsets[$objectNumber])) {
        $rows .= $xrefRow(1, $offsets[$objectNumber]);
        continue;
    }

    $rows .= $xrefRow(0, 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress named-destination xref stream fixture.');
}

$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Compressed Start', 'Compressed Appendix', 'LegacyCompressed']) {
    throw new RuntimeException('Expected xref-stream object-stream named destinations before WordPress output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Direct Start')
    || str_contains($encoded, 'Stale Direct Appendix')
    || str_contains($encoded, 'LegacyStale')
    || str_contains($encoded, '222')
    || str_contains($encoded, '33')
) {
    throw new RuntimeException('Expected stale direct named-destination bodies to stay hidden.');
}
if (!str_contains($plainText, 'Object stream destination page')
    || !str_contains($plainText, 'Object stream appendix page')
    || str_contains($plainText, 'Compressed Start')
    || str_contains($plainText, 'Stale Direct Start')
) {
    throw new RuntimeException('Expected visible text to exclude named-destination metadata.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream type-2 object-stream rows select catalog named destinations before stale direct same-number bodies',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'object_stream_named_destination_objects_resolved' => true,
    'stale_direct_named_destination_bodies_excluded' => true,
    'visible_text_excludes_destination_metadata' => true,
];

echo '<!-- markerpdf-pdf-named-destination-object-stream-currentbase '
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
