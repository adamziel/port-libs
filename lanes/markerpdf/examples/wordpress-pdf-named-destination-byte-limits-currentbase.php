<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Byte range destination start page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Byte range destination appendix page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [<18> <41>] /Names [<18> [3 0 R /FitH 700] <41> [4 0 R /XYZ 72 640 0] <80> [4 0 R /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfNamedDestinationExtractor();
$destinations = $extractor->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
$metadataEncoded = json_encode($documentDestinations, JSON_UNESCAPED_SLASHES);

if ($names !== ["\u{02d8}", 'A', 'LegacyOk']) {
    throw new RuntimeException('Expected byte-string /Limits to bound decoded PDFDocEncoding destination keys.');
}
if (!is_string($encoded) || str_contains($encoded, "\u{2022}") || str_contains($encoded, '111')) {
    throw new RuntimeException('Expected out-of-byte-range destination rows to stay out of WordPress metadata.');
}
if (($documentDestinations['names'] ?? []) !== ["\u{02d8}", 'A', 'LegacyOk']
    || !is_string($metadataEncoded)
    || str_contains($metadataEncoded, "\u{2022}")
    || str_contains($metadataEncoded, '111')
) {
    throw new RuntimeException('Expected document destination metadata to use source-byte /Limits boundaries.');
}
if (!str_contains($plainText, 'Byte range destination start page')
    || !str_contains($plainText, 'Byte range destination appendix page')
) {
    throw new RuntimeException('Expected visible PDF page text to be preserved.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests name-tree /Limits compare PDF string source bytes before decoded labels',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'document_destination_count' => $documentDestinations['count'] ?? null,
    'document_destination_names' => $documentDestinations['names'] ?? [],
    'byte_string_limits_applied' => true,
    'out_of_byte_range_decoded_key_rejected' => !in_array("\u{2022}", $names, true),
    'document_metadata_out_of_byte_range_key_rejected' => is_string($metadataEncoded)
        && !str_contains($metadataEncoded, "\u{2022}")
        && !str_contains($metadataEncoded, '111'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, "\u{2022}")
        && !str_contains($plainText, 'LegacyOk'),
];

echo '<!-- markerpdf-pdf-named-destination-byte-limits-currentbase '
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
