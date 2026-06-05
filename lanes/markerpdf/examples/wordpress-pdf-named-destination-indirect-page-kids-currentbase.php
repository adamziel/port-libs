<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstLogicalPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect Kids first logical page) Tj ET';
$secondLogicalPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect Kids second logical page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacySecond [3 0 R /FitV 90] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids 12 0 R /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(First Logical) [4 0 R /FitH 700] (Second Logical) [3 0 R /XYZ 72 640 0] (Detached Decoy) [5 0 R /FitH 111]] >>\nendobj\n"
    . "12 0 obj\n[4 0 R 3 0 R]\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstLogicalPageContent) . " >>\nstream\n{$firstLogicalPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondLogicalPageContent) . " >>\nstream\n{$secondLogicalPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$documentDestinations = $metadata['document_destinations'] ?? [];
$names = array_column($destinations, 'name');
$encoded = json_encode([$destinations, $documentDestinations], JSON_UNESCAPED_SLASHES);

if ($names !== ['First Logical', 'Second Logical', 'LegacySecond']) {
    throw new RuntimeException('Expected indirect page-tree Kids order before WordPress named-destination output.');
}
if (($destinations[0]['page'] ?? null) !== 0
    || ($destinations[0]['page_object_id'] ?? null) !== 4
    || ($destinations[1]['page'] ?? null) !== 1
    || ($destinations[1]['page_object_id'] ?? null) !== 3
) {
    throw new RuntimeException('Expected indirect Kids array order to define destination page indexes.');
}
if (!is_string($encoded) || str_contains($encoded, 'Detached Decoy') || str_contains($encoded, 'FitH 111')) {
    throw new RuntimeException('Expected detached page destination rows to stay out of WordPress review metadata.');
}
if (!str_contains($plainText, 'Indirect Kids first logical page')
    || !str_contains($plainText, 'Indirect Kids second logical page')
) {
    throw new RuntimeException('Expected visible logical page text to remain importable.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog named destinations use indirect page-tree Kids array order and reject detached page objects before WordPress review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'document_destination_count' => $documentDestinations['count'] ?? null,
    'document_destination_page_count' => $documentDestinations['page_count'] ?? null,
    'indirect_page_kids_order_resolved' => ($destinations[0]['page'] ?? null) === 0
        && ($destinations[0]['page_object_id'] ?? null) === 4
        && ($destinations[1]['page'] ?? null) === 1
        && ($destinations[1]['page_object_id'] ?? null) === 3,
    'detached_page_destination_excluded' => is_string($encoded) && !str_contains($encoded, 'Detached Decoy'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Detached Decoy')
        && !str_contains($plainText, 'LegacySecond'),
    'visible_text' => $plainText,
];

echo '<!-- markerpdf-pdf-named-destination-indirect-page-kids-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $item = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($item, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
