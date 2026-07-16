<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination intro page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination appendix page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyIndirect [20 0 R /FitV 90] /LegacyInvalid [21 0 R /FitH 111] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Indirect Page Index) [20 0 R /XYZ 72 640 0] (Indirect Page Ref) [23 0 R /FitH 700] (Indirect Negative Index) [21 0 R /FitH 111] (Indirect String Page) [22 0 R /FitH 222] (Indirect Out Of Range) [24 0 R /FitV 333] (Indirect Null Page) [25 0 R /FitR 1 2 3 4]] >>\nendobj\n"
    . "20 0 obj\n1\nendobj\n"
    . "21 0 obj\n-1\nendobj\n"
    . "22 0 obj\n(Not a page)\nendobj\n"
    . "23 0 obj\n3 0 R\nendobj\n"
    . "24 0 obj\n9\nendobj\n"
    . "25 0 obj\nnull\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Indirect Page Index', 'Indirect Page Ref', 'LegacyIndirect']) {
    throw new RuntimeException('Expected indirect page-index named destinations before WordPress output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Indirect Negative Index')
    || str_contains($encoded, 'Indirect String Page')
    || str_contains($encoded, 'Indirect Out Of Range')
    || str_contains($encoded, 'Indirect Null Page')
    || str_contains($encoded, 'LegacyInvalid')
) {
    throw new RuntimeException('Expected malformed indirect page operands to stay out of review metadata.');
}
if (!str_contains($plainText, 'Indirect destination intro page')
    || !str_contains($plainText, 'Indirect destination appendix page')
) {
    throw new RuntimeException('Expected visible page text to remain importable.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'explicit named-destination page operands can be indirect page numbers or indirect page refs before WordPress review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'indirect_page_index_resolved' => ($destinations[0]['page'] ?? null) === 1
        && ($destinations[0]['page_object_id'] ?? null) === null,
    'indirect_page_ref_resolved' => ($destinations[1]['page'] ?? null) === 0
        && ($destinations[1]['page_object_id'] ?? null) === 3,
    'legacy_indirect_page_index_resolved' => in_array('LegacyIndirect', $names, true),
    'malformed_indirect_page_operands_rejected' => true,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Indirect Negative Index')
        && !str_contains($plainText, 'LegacyInvalid'),
    'visible_text' => $plainText,
];

echo '<!-- markerpdf-pdf-named-destination-indirect-page-index-currentbase '
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
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
