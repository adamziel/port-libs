<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyReview [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits 50 0 R /Kids 51 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Limits 52 0 R /Names 53 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Limits 54 0 R /Names 55 0 R >>\nendobj\n"
    . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "50 0 obj\n[(Alpha Review) (Summary Review)]\nendobj\n"
    . "51 0 obj\n[9 0 R 10 0 R]\nendobj\n"
    . "52 0 obj\n[(Alpha Review) (Current Start)]\nendobj\n"
    . "53 0 obj\n[(Alpha Review) [3 0 R /FitH 700] (Current Start) 11 0 R (zz stale alpha) [4 0 R /Fit]]\nendobj\n"
    . "54 0 obj\n[(Summary Review) (Summary Review)]\nendobj\n"
    . "55 0 obj\n[(A stale summary) [4 0 R /Fit] (Summary Review) [4 0 R /FitBH 600]]\nendobj\n"
    . "30 0 obj\n<< /Length 48 >>\nstream\nBT /F1 12 Tf 72 720 Td (Alpha destination page) Tj ET\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 50 >>\nstream\nBT /F1 12 Tf 72 720 Td (Summary destination page) Tj ET\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$destinationNames = array_column($destinations, 'name');
$expectedNames = ['Alpha Review', 'Current Start', 'Summary Review', 'LegacyReview'];
$staleNames = ['zz stale alpha', 'A stale summary'];

if ($destinationNames !== $expectedNames) {
    throw new RuntimeException('Expected indirect destination name-tree arrays to resolve before WordPress review metadata.');
}

foreach ($staleNames as $staleName) {
    if (in_array($staleName, $destinationNames, true) || str_contains($plainText, $staleName)) {
        throw new RuntimeException("Expected out-of-limits destination {$staleName} to stay hidden.");
    }
}

echo '<!-- markerpdf-pdf-named-destinations-indirect-arrays-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'indirect /Kids, /Names, and /Limits arrays resolve before WordPress named-destination review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'indirect_name_tree_arrays_resolved' => true,
    'indirect_limits_pruned_stale_names' => true,
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Alpha Review')
        && !str_contains($plainText, 'Current Start')
        && !str_contains($plainText, 'Summary Review')
        && !str_contains($plainText, 'LegacyReview'),
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
