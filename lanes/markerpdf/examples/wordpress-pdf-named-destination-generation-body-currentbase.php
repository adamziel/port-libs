<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyCurrent 30 1 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 1 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 1 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Current Review) 20 1 R (Direct Current Page) [4 1 R /FitR 10 20 300 740] (Stale Page Generation) [4 0 R /FitH 120] (Bad Destination Generation) 21 1 R] >>\nendobj\n"
    . "20 1 obj\n<< /D [4 1 R /XYZ 72 640 0] >>\nendobj\n"
    . "20 0 obj\n<< /D [3 0 R /FitH 111] >>\nendobj\n"
    . "21 0 obj\n<< /D [3 0 R /FitV 80] >>\nendobj\n"
    . "30 1 obj\n<< /D [3 0 R /FitBH 610] >>\nendobj\n"
    . "30 0 obj\n<< /D [4 0 R /FitBV 90] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$names = array_column($destinations, 'name');
$expected = ['Current Review', 'Direct Current Page', 'LegacyCurrent'];

if ($names !== $expected) {
    throw new RuntimeException('Expected generation-exact named destination rows before WordPress output.');
}

foreach (['Stale Page Generation', 'Bad Destination Generation'] as $staleName) {
    if (in_array($staleName, $names, true)) {
        throw new RuntimeException("Expected stale named destination {$staleName} to stay hidden.");
    }
}

$current = $destinations[0] ?? [];
if (($current['page'] ?? null) !== 1 || ($current['coordinates']['top'] ?? null) !== 640.0) {
    throw new RuntimeException('Expected the generation-one destination dictionary to remain authoritative.');
}

echo '<!-- markerpdf-pdf-named-destination-generation-body-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'same-number stale generation bodies do not override exact N G R destination dictionaries or page-tree leaves before WordPress review',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'generation_specific_destination_body_selected' => ($current['fit'] ?? null) === 'XYZ'
        && ($current['page'] ?? null) === 1
        && ($current['coordinates']['top'] ?? null) === 640.0,
    'stale_page_generation_filtered' => true,
    'stale_same_number_body_excluded' => true,
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
