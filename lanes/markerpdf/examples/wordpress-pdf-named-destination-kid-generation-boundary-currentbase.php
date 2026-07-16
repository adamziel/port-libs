<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyFallback [4 0 R /FitV 130] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Kids [9 0 R 10 0 R] /Limits [(Current Review) (Summary Review)] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(Current Review) (Current Review)] /Kids [9 1 R] >>\nendobj\n"
    . "9 1 obj\n<< /Limits [(Current Review) (Current Review)] /Names [(Current Review) [3 0 R /XYZ 72 700 1]] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(Summary Review) (Summary Review)] /Names [(Summary Review) 11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /D [4 0 R /FitBH 640] >>\nendobj\n"
    . "30 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 720 Td (Current destination page) Tj ET\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 720 Td (Summary destination page) Tj ET\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$expected = ['Current Review', 'Summary Review', 'LegacyFallback'];

if ($names !== $expected) {
    throw new RuntimeException('Expected generation-distinct name-tree kid rows before WordPress output.');
}
if (!str_contains($plainText, 'Current destination page') || !str_contains($plainText, 'Summary destination page')) {
    throw new RuntimeException('Expected visible page text to survive named-destination review extraction.');
}
foreach ($expected as $name) {
    if (str_contains($plainText, $name)) {
        throw new RuntimeException("Expected named destination label {$name} to stay out of visible text.");
    }
}

$current = $destinations[0] ?? [];
if (($current['page'] ?? null) !== 0 || ($current['fit'] ?? null) !== 'XYZ') {
    throw new RuntimeException('Expected nested generation-one name-tree kid to resolve the current destination.');
}

echo '<!-- markerpdf-pdf-named-destination-kid-generation-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'same-object name-tree Kids are cycle-checked by object generation before WordPress review metadata',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'nested_generation_kid_resolved' => ($current['name'] ?? null) === 'Current Review'
        && ($current['page'] ?? null) === 0
        && ($current['coordinates']['top'] ?? null) === 700.0,
    'sibling_destination_preserved' => in_array('Summary Review', $names, true),
    'legacy_fallback_preserved' => in_array('LegacyFallback', $names, true),
    'destination_labels_excluded_from_visible_text' => true,
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
