<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Malformed UTF16 destination source page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Current review destination target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Current Review) (LegacyOk)] /Names [<FEFF004100> [4 0 R /FitH 111] (Current Review) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$errors = [];
set_error_handler(static function (int $severity, string $message) use (&$errors): bool {
    $errors[] = $severity . ':' . $message;

    return true;
});
try {
    $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
} finally {
    restore_error_handler();
}

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$destinationNames = array_column($destinations, 'name');
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

if ($errors !== []) {
    throw new RuntimeException('Expected malformed UTF-16 destination keys to decode fail-closed without PHP notices.');
}
if ($destinationNames !== ['Current Review', 'LegacyOk']) {
    throw new RuntimeException('Expected malformed UTF-16 destination key row to be excluded before WordPress review metadata.');
}
if (str_contains($encodedReview, '111')
    || str_contains($plainText, 'Current Review')
    || str_contains($plainText, 'LegacyOk')
) {
    throw new RuntimeException('Expected destination metadata and malformed coordinate rows to stay out of visible WordPress text.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed UTF-16BE catalog /Names /Dests keys fail closed before WordPress named-destination review',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'malformed_utf16_key_filtered' => true,
    'php_decode_notices' => count($errors),
    'malformed_coordinate_excluded' => !str_contains($encodedReview, '111'),
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Current Review')
        && !str_contains($plainText, 'LegacyOk'),
];

echo '<!-- markerpdf-pdf-named-destination-malformed-utf16-boundary-currentbase '
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
