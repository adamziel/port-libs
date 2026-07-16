<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Intermediate limit start page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Intermediate limit summary page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(A) (Zzz)] /Kids [9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(Zzz) (A)] /Kids [10 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 13 0 R (zzzz stale) [4 0 R /Fit]] >>\nendobj\n"
    . "11 0 obj\n<< /Limits [(Summary) (Summaryzz)] /Names [(Summary Appendix) [4 0 R /FitBH 600] (0 stale) [3 0 R /Fit]] >>\nendobj\n"
    . "13 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$names = array_column($destinations, 'name');

$expectedNames = ['Current Start', 'Review Summary', 'Summary Appendix', 'LegacyOnly'];
if ($names !== $expectedNames) {
    throw new RuntimeException('Expected inherited-limit destination names before WordPress navigation output.');
}

foreach (['zzzz stale', '0 stale'] as $staleName) {
    if (in_array($staleName, $names, true) || str_contains($plainText, $staleName)) {
        throw new RuntimeException("Expected out-of-range destination {$staleName} to stay hidden.");
    }
}

echo '<!-- markerpdf-pdf-named-destination-intermediate-limits-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed intermediate /Kids /Limits falls back to inherited name-tree range before WordPress named-destination review',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'intermediate_reversed_limits_fallback' => true,
    'out_of_range_destination_names_filtered' => true,
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Current Start')
        && !str_contains($plainText, 'Review Summary')
        && !str_contains($plainText, 'Summary Appendix')
        && !str_contains($plainText, 'LegacyOnly'),
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
