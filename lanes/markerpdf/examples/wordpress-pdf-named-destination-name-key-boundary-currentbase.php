<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (String key destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyNameKey [4 0 R /FitV 130] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Current String Key) (Review Summary)] /Names [(Current String Key) [3 0 R /FitH 700] /NameObjectStale [4 0 R /FitH 111] 12 0 R [4 0 R /FitBH 222] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "12 0 obj\n/IndirectNameObjectStale\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfNamedDestinationExtractor();
$destinations = $extractor->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['Current String Key', 'Review Summary', 'LegacyNameKey']) {
    throw new RuntimeException('Expected malformed PDF-name name-tree keys to be rejected before WordPress output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'NameObjectStale')
    || str_contains($encoded, 'IndirectNameObjectStale')
    || str_contains($encoded, '111')
    || str_contains($encoded, '222')
) {
    throw new RuntimeException('Expected malformed name-tree key rows and coordinates to stay out of review metadata.');
}
if (!str_contains($plainText, 'String key destination page')
    || !str_contains($plainText, 'Legacy destination page')
) {
    throw new RuntimeException('Expected visible text from current destination pages.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests keys require PDF text strings while legacy /Dests dictionary name keys remain valid',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'name_tree_name_object_keys_rejected' => true,
    'legacy_dests_dictionary_name_key_preserved' => in_array('LegacyNameKey', $names, true),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'NameObjectStale')
        && !str_contains($plainText, 'IndirectNameObjectStale')
        && !str_contains($plainText, 'Current String Key'),
];

echo '<!-- markerpdf-pdf-named-destination-name-key-boundary-currentbase '
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
