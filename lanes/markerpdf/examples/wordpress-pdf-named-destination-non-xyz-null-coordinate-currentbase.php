<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Null H jump Null V jump Null R jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Non XYZ null coordinate target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyNull [4 0 R /FitBH 22 0 R] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid XYZ Null) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 236 718] /Dest (Invalid FitH Null) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [246 700 330 718] /Dest (Invalid FitV Null) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [340 700 424 718] /Dest (Invalid FitR Null) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [434 700 498 718] /A << /S /URI /URI (https://example.com/non-xyz-null-coordinate) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Valid XYZ Null) [4 0 R /XYZ null null null] (Valid FitH Numeric) [4 0 R /FitH 700] (Invalid FitH Null) [4 0 R /FitH null] (Invalid FitV Null) [4 0 R /FitV 21 0 R] (Invalid FitR Null) [4 0 R /FitR 1 2 null 4] (Invalid Action FitBH Null) << /S /GoTo /D [4 0 R /FitBH null] >>] >>\nendobj\n"
    . "21 0 obj\nnull\nendobj\n"
    . "22 0 obj\nnull\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$linkObjects = $links === [] ? [] : array_column($links[0]['links'], 'annotation_object');

if ($destinationNames !== ['Valid XYZ Null', 'Valid FitH Numeric', 'LegacyOk']) {
    throw new RuntimeException('Expected non-XYZ null coordinate destinations to stay out of WordPress review metadata.');
}
if ($linkObjects !== [7, 11]) {
    throw new RuntimeException('Expected invalid non-XYZ null coordinate destinations to stay out of WordPress link promotion.');
}
foreach (['Invalid FitH Null', 'Invalid FitV Null', 'Invalid FitR Null', 'Invalid Action FitBH Null', 'LegacyNull'] as $hiddenName) {
    if (in_array($hiddenName, $destinationNames, true) || str_contains($plainText, $hiddenName)) {
        throw new RuntimeException('Malformed null-coordinate named destination leaked into WordPress output.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-non-xyz-null-coordinate-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'only XYZ destination coordinates accept null; FitH/FitV/FitR/FitBH/FitBV null operands are rejected before WordPress metadata and link promotion',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'xyz_null_coordinates_preserved' => ($destinations[0]['coordinates'] ?? null) === ['left' => null, 'top' => null, 'zoom' => null],
    'non_xyz_null_coordinates_rejected' => true,
    'promoted_link_annotation_objects' => $linkObjects,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Invalid FitH Null'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

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
