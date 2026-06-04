<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Current Start) (Review Summary)] /Kids [9 0 R 10 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(zz-stale) (zz-stale)] /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 11 0 R (zz-stale) [4 0 R /Fit]] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(zzz-decoy) (zzz-decoy)] /Names [(A Stale Deck) [4 0 R /Fit] (ZZZ Appendix) [4 0 R /Fit]] >>\nendobj\n"
    . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length 59 >>\nstream\nBT /F1 12 Tf 72 720 Td (Current named destination page) Tj ET\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 58 >>\nstream\nBT /F1 12 Tf 72 720 Td (Review summary destination page) Tj ET\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$names = array_column($destinations, 'name');
$staleNames = ['zz-stale', 'A Stale Deck', 'ZZZ Appendix'];

foreach (['Current Start', 'Review Summary', 'LegacyOnly'] as $requiredName) {
    if (!in_array($requiredName, $names, true)) {
        throw new RuntimeException("Expected recovered destination {$requiredName} in WordPress review metadata.");
    }
}

foreach ($staleNames as $staleName) {
    if (in_array($staleName, $names, true) || str_contains($plainText, $staleName)) {
        throw new RuntimeException("Expected stale malformed-limit destination {$staleName} to stay hidden.");
    }
}

echo '<!-- markerpdf-pdf-named-destinations-limits-fallback-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed child /Limits with no matching leaf keys falls back to inherited name-tree range before WordPress destination review',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'recovered_inherited_limit_destinations' => ['Current Start', 'Review Summary'],
    'stale_limit_names_filtered' => true,
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Current Start')
        && !str_contains($plainText, 'Review Summary')
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
