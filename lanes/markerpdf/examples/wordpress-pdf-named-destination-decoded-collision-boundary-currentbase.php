<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Decoded collision first target page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Decoded collision UTF16 target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /Collision [3 0 R /FitV 88] /LegacyTail [4 0 R /FitBH 500] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Collision) <FEFF0043006F006C006C006900730069006F006E>] /Names [(Collision) [3 0 R /FitH 700] <FEFF0043006F006C006C006900730069006F006E> [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$documentDestinations = $metadata['document_destinations'] ?? [];
$destinationNames = array_column($destinations, 'name');
$nameBytesHex = array_values(array_filter(
    array_map(static fn (array $row): ?string => is_string($row['name_bytes_hex'] ?? null) ? $row['name_bytes_hex'] : null, $destinations)
));

if ($destinationNames !== ['Collision', 'Collision', 'LegacyTail']) {
    throw new RuntimeException('Expected decoded-collision named destinations to preserve both raw name-tree byte keys.');
}
if ($nameBytesHex !== ['436f6c6c6973696f6e', 'feff0043006f006c006c006900730069006f006e']) {
    throw new RuntimeException('Expected raw destination source bytes to be exposed for WordPress review provenance.');
}
if (($documentDestinations['names'] ?? null) !== $destinationNames) {
    throw new RuntimeException('Expected document_destinations metadata to match standalone named destinations.');
}
foreach (['Collision', 'LegacyTail', 'FitV 88'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination review metadata to stay out of visible WordPress text.');
    }
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests preserves distinct raw PDF string keys that decode to the same WordPress review label',
    'destination_names' => $destinationNames,
    'destination_pages' => array_column($destinations, 'page'),
    'destination_name_bytes_hex' => $nameBytesHex,
    'metadata_destination_names' => $documentDestinations['names'] ?? [],
    'legacy_same_label_suppressed' => !in_array('FitV', array_column($destinations, 'fit'), true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Collision')
        && !str_contains($plainText, 'LegacyTail'),
];

echo '<!-- markerpdf-pdf-named-destination-decoded-collision-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n\n";
echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $item = [
        'markerDestination' => $destination['name'],
        'markerNameBytesHex' => $destination['name_bytes_hex'] ?? null,
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($item, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
