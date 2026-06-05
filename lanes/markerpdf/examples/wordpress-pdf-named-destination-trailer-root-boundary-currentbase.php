<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale named destination body) Tj ET';
$currentOneContent = 'BT /F1 12 Tf 72 720 Td (Current named destination body) Tj ET';
$currentTwoContent = 'BT /F1 12 Tf 72 720 Td (Current appendix destination body) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /StaleLegacy [3 0 R /FitV 88] >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(8, 0, '<< /Names [(Stale Start) [3 0 R /FitH 111] (Stale Dict) 9 0 R] >>');
$addObject(9, 0, '<< /D [3 0 R /XYZ 9 99 0] >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['8:0'])
    . $xrefRow($offsets['9:0'])
    . "trailer\n<< /Size 30 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(10, 0, '<< /Type /Catalog /Pages 11 0 R /Names << /Dests 18 0 R >> /Dests << /LegacyCurrent [13 0 R /FitV 90] >> >>');
$addObject(11, 0, '<< /Type /Pages /Kids [12 0 R 13 0 R] /Count 2 >>');
$addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 15 0 R >> >> /Contents 16 0 R >>');
$addObject(13, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 15 0 R >> >> /Contents 17 0 R >>');
$addObject(15, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(16, 0, "<< /Length " . strlen($currentOneContent) . " >>\nstream\n{$currentOneContent}\nendstream");
$addObject(17, 0, "<< /Length " . strlen($currentTwoContent) . " >>\nstream\n{$currentTwoContent}\nendstream");
$addObject(18, 0, '<< /Names [(Current Start) [12 0 R /FitH 700] (Current Appendix) 19 0 R] >>');
$addObject(19, 0, '<< /D [13 0 R /XYZ 72 640 0] >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefRow(0, 65535, 'f')
    . "10 10\n"
    . $xrefRow($offsets['10:0'])
    . $xrefRow($offsets['11:0'])
    . $xrefRow($offsets['12:0'])
    . $xrefRow($offsets['13:0'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['15:0'])
    . $xrefRow($offsets['16:0'])
    . $xrefRow($offsets['17:0'])
    . $xrefRow($offsets['18:0'])
    . $xrefRow($offsets['19:0'])
    . "% trailer << /Root 1 0 R /CommentOnly /Stale#52oot >>\n"
    . "trailer\n<< /Size 30 /Ro#6ft 10 0 R /Pre#76 {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Current Start', 'Current Appendix', 'LegacyCurrent']) {
    throw new RuntimeException('Expected current trailer-root named destinations for WordPress review.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Stale Start')
    || str_contains($encoded, 'Stale Dict')
    || str_contains($encoded, 'StaleLegacy')
) {
    throw new RuntimeException('Expected stale body catalog destinations to stay out of review metadata.');
}
if (!str_contains($plainText, 'Current named destination body')
    || !str_contains($plainText, 'Current appendix destination body')
    || str_contains($plainText, 'Stale named destination body')
) {
    throw new RuntimeException('Expected visible text to follow the current trailer root.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest startxref trailer /Root catalog owns named destinations before stale earlier catalog bodies in WordPress review',
    'destination_names' => $names,
    'current_trailer_root_catalog_selected' => true,
    'stale_body_catalog_destinations_excluded' => true,
    'visible_text_uses_current_trailer_root' => true,
];

echo '<!-- markerpdf-pdf-named-destination-trailer-root-boundary-currentbase '
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
