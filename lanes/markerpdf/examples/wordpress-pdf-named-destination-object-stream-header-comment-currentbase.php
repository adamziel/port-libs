<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Comment header destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Comment header appendix page) Tj ET';

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
    1 => '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R /Dests << /LegacyCommented [4 0 R /FitV 96] >> >>',
    8 => '<< /Dests 9 0 R >>',
    9 => '<< /Names [(Commented Start) [3 0 R /FitH 700] (Commented Appendix) 10 0 R] >>',
    10 => '<< /D [4 0 R /XYZ 72 640 0] >>',
];
$objectStreamData = '';
$objectStreamHeader = '';
$memberIndexes = [];
$memberIndex = 0;
foreach ($compressedMembers as $objectNumber => $body) {
    $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamData);
    $objectStreamHeader .= $objectNumber === 1
        ? " % 99 123 fake numeric header row\n"
        : ' ';
    $memberIndexes[$objectNumber] = $memberIndex++;
    $objectStreamData .= $body . "\n";
}
$objectStreamBytes = $objectStreamHeader . $objectStreamData;
$compressedObjectStream = gzcompress($objectStreamBytes);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress named-destination commented object stream fixture.');
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
    throw new RuntimeException('Unable to compress named-destination commented xref stream fixture.');
}

$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$expectedNames = ['Commented Start', 'Commented Appendix', 'LegacyCommented'];
if ($names !== $expectedNames) {
    throw new RuntimeException('Expected commented object-stream header named destinations before WordPress output.');
}

foreach (['Stale Direct Start', 'Stale Direct Appendix', 'LegacyStale', '222', '33'] as $hidden) {
    if (str_contains($encoded, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected stale direct named-destination body to stay hidden: ' . $hidden);
    }
}

if (!str_contains($plainText, 'Comment header destination page')
    || !str_contains($plainText, 'Comment header appendix page')
    || str_contains($plainText, 'Commented Start')
    || str_contains($plainText, 'LegacyCommented')
) {
    throw new RuntimeException('Expected WordPress visible text to exclude destination metadata.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream type-2 object-stream rows keep explicit named-destination member indexes aligned across PDF comments',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'commented_object_stream_header_ignored_numeric_decoys' => true,
    'stale_direct_named_destination_bodies_excluded' => true,
    'visible_text_excludes_destination_metadata' => true,
];

echo '<!-- markerpdf-pdf-named-destination-object-stream-header-comment-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
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
