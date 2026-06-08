<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid keyword jump Bare keyword jump Legacy keyword jump Legacy ok jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Keyword operand destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyKeyword BareKeywordTarget >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alias From Keyword) (Valid String Target)] /Names [(Valid String Target) [4 0 R /FitH 700] BareKeywordTarget [4 0 R /FitH 111] (Alias From Keyword) BareKeywordTarget (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$destinationNames = array_column($destinations, 'name');
$expectedNames = ['Valid String Target', 'Review Summary', 'LegacyOk'];
$hiddenNames = ['BareKeywordTarget', 'Alias From Keyword', 'LegacyKeyword'];

if ($destinationNames !== $expectedNames) {
    throw new RuntimeException('Expected bare keyword operands to be rejected before WordPress destination review.');
}

foreach ($hiddenNames as $hiddenName) {
    if (in_array($hiddenName, $destinationNames, true) || str_contains($plainText, $hiddenName)) {
        throw new RuntimeException("Expected malformed keyword destination {$hiddenName} to stay review-hidden.");
    }
}

echo '<!-- markerpdf-pdf-named-destination-keyword-operand-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'bare PDF keyword operands in /Names /Dests are rejected before WordPress named-destination review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'keyword_operands_rejected' => true,
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Valid String Target')
        && !str_contains($plainText, 'Review Summary')
        && !str_contains($plainText, 'LegacyOk'),
    'visible_text' => $plainText,
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
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
