<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current xref destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Current xref appendix page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyCurrent [4 0 R /FitV 96] >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
$addObject(5, 0, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
$addObject(6, 0, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
$addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(8, 0, '<< /Names [(Current Xref Start) [3 0 R /FitH 700] (Current Xref Appendix) 9 0 R] >>');
$addObject(9, 0, '<< /D [4 0 R /XYZ 72 640 0] >>');
$currentDestinationTreeOffset = $offsets['8:0'];
$currentDestinationDictionaryOffset = $offsets['9:0'];

$addObject(8, 0, '<< /Names [(Stale Scanned Duplicate) [4 0 R /FitH 111] (Stale Dict Duplicate) 9 0 R] >>');
$addObject(9, 0, '<< /D [3 0 R /FitV 22] >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow($offsets['6:0'])
    . $xrefRow($offsets['7:0'])
    . $xrefRow($currentDestinationTreeOffset)
    . $xrefRow($currentDestinationDictionaryOffset)
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Current Xref Start', 'Current Xref Appendix', 'LegacyCurrent']) {
    throw new RuntimeException('Expected xref-selected named destination bodies before WordPress output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Scanned Duplicate')
    || str_contains($encoded, 'Stale Dict Duplicate')
    || str_contains($encoded, '111')
    || str_contains($encoded, '22')
) {
    throw new RuntimeException('Expected later duplicate named destination bodies to stay hidden.');
}
if (!str_contains($plainText, 'Current xref destination page')
    || !str_contains($plainText, 'Current xref appendix page')
) {
    throw new RuntimeException('Expected visible text from current xref-selected pages.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'classic xref direct object offsets select named-destination bodies before later duplicate same-generation scans',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'xref_selected_named_destination_objects' => true,
    'later_duplicate_named_destination_bodies_excluded' => true,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Current Xref Start')
        && !str_contains($plainText, 'Stale Scanned Duplicate'),
];

echo '<!-- markerpdf-pdf-named-destination-xref-offset-boundary-currentbase '
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
