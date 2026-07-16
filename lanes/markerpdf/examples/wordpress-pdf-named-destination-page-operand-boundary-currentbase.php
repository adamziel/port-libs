<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current page destination body) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Index destination body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [1 /FitV 90] /LegacyBad [-1 /FitH 111] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Valid Page Ref) [3 0 R /FitH 700] (Valid Page Index) [1 /XYZ 72 640 0] (Null Page Operand) [null /Fit] (Negative Page Index) [-1 /FitH 111] (Out Of Range Page Index) [5 /FitV 222] (String Page Operand) [(not a page) /FitR 1 2 3 4] (Name Page Operand) [/PageAlias /FitBH 500] (Dictionary Page Operand) [<< /Type /Page >> /FitH 333]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Valid Page Ref', 'Valid Page Index', 'LegacyOk']) {
    throw new RuntimeException('Expected invalid page operands to be rejected before WordPress destination output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Null Page Operand')
    || str_contains($encoded, 'Negative Page Index')
    || str_contains($encoded, 'Out Of Range Page Index')
    || str_contains($encoded, 'String Page Operand')
    || str_contains($encoded, 'Name Page Operand')
    || str_contains($encoded, 'Dictionary Page Operand')
    || str_contains($encoded, 'LegacyBad')
) {
    throw new RuntimeException('Expected malformed destination page operands to stay out of review metadata.');
}
if (!str_contains($plainText, 'Current page destination body')
    || !str_contains($plainText, 'Index destination body')
) {
    throw new RuntimeException('Expected current page text to remain importable.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'explicit named-destination page operands must be valid page refs or in-range page numbers before WordPress review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'valid_page_ref_preserved' => ($destinations[0]['page_object_id'] ?? null) === 3,
    'valid_page_index_preserved' => ($destinations[1]['page'] ?? null) === 1,
    'legacy_in_range_page_index_preserved' => in_array('LegacyOk', $names, true),
    'invalid_page_operands_rejected' => true,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Null Page Operand')
        && !str_contains($plainText, 'LegacyBad'),
];

echo '<!-- markerpdf-pdf-named-destination-page-operand-boundary-currentbase '
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
