<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (GoTo destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Plain dictionary destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk << /D [4 0 R /FitV 130] >> /LegacyLaunch << /S /Launch /F (legacy-payload.sh) /D [3 0 R /FitH 88] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(GoTo Action Dest) << /S /GoTo /D [3 0 R /FitH 700] >> (Plain Dest Dict) << /D [4 0 R /XYZ 72 640 0] >> (URI Masquerade) << /S /URI /URI (https://example.com/hidden-destination-action) /D [4 0 R /FitH 111] >> (Launch Masquerade) 11 0 R (JavaScript Masquerade) << /S /JavaScript /JS (app.alert\\(hidden named destination\\)) /D [3 0 R /FitV 222] >> (Malformed Action Type) << /S (GoTo) /D [4 0 R /FitBH 333] >>] >>\nendobj\n"
    . "11 0 obj\n<< /S /Launch /F (launch-payload.exe) /D [4 0 R /FitR 1 2 3 4] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

if ($names !== ['GoTo Action Dest', 'Plain Dest Dict', 'LegacyOk']) {
    throw new RuntimeException('Expected only GoTo and plain destination dictionaries before WordPress output.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'URI Masquerade')
    || str_contains($encoded, 'Launch Masquerade')
    || str_contains($encoded, 'JavaScript Masquerade')
    || str_contains($encoded, 'Malformed Action Type')
    || str_contains($encoded, 'LegacyLaunch')
) {
    throw new RuntimeException('Expected non-GoTo action dictionaries to stay out of destination metadata.');
}
if (!str_contains($plainText, 'GoTo destination page')
    || !str_contains($plainText, 'Plain dictionary destination page')
) {
    throw new RuntimeException('Expected visible text from valid destination pages.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog named-destination dictionaries may be plain /D or /S /GoTo but non-GoTo action dictionaries are rejected before WordPress review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'goto_action_destination_preserved' => ($destinations[0]['fit'] ?? null) === 'FitH',
    'plain_destination_dictionary_preserved' => ($destinations[1]['fit'] ?? null) === 'XYZ',
    'legacy_plain_destination_preserved' => in_array('LegacyOk', $names, true),
    'non_goto_action_dictionaries_rejected' => true,
    'visible_text_excludes_action_payloads' => !str_contains($plainText, 'hidden named destination')
        && !str_contains($plainText, 'hidden-destination-action')
        && !str_contains($plainText, 'launch-payload.exe'),
];

echo '<!-- markerpdf-pdf-named-destination-action-dictionary-boundary-currentbase '
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
