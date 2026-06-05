<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid coordinate jump Missing coordinate jump Bad coordinate jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Coordinate destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBadMissing [4 0 R /FitV] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 178 718] /Dest (Valid FitH Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [188 700 316 718] /Dest (Missing FitH Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [326 700 438 718] /Dest (String FitV Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [448 700 512 718] /A << /S /URI /URI (https://example.com/coordinate-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Valid FitH Target) [4 0 R /FitH 700] (Valid XYZ Null Target) [4 0 R /XYZ null null null] (Missing FitH Target) [4 0 R /FitH] (String FitV Target) [4 0 R /FitV (left)]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$destinationNames = array_column($destinations, 'name');
$linkObjects = $links === [] ? [] : array_column($links[0]['links'], 'annotation_object');

foreach (['Missing FitH Target', 'String FitV Target', 'LegacyBadMissing'] as $unexpectedName) {
    if (in_array($unexpectedName, $destinationNames, true)) {
        throw new RuntimeException('Malformed coordinate named destination survived WordPress review boundary.');
    }
}
if ($linkObjects !== [7, 10]) {
    throw new RuntimeException('Malformed coordinate destination link was promoted into WordPress span metadata.');
}

echo '<!-- markerpdf-pdf-named-destination-coordinate-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'valid_destination_count' => count($destinations),
    'malformed_coordinates_rejected' => true,
    'null_xyz_coordinates_preserved' => ($destinations[1]['coordinates'] ?? null) === ['left' => null, 'top' => null, 'zoom' => null],
    'promoted_link_annotation_objects' => $linkObjects,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $attrs = [
        'data-marker-destination-page' => $destination['page'] === null ? '' : (string) ($destination['page'] + 1),
        'data-marker-fit' => $destination['fit'],
        'data-marker-source' => $destination['source'],
    ];

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
