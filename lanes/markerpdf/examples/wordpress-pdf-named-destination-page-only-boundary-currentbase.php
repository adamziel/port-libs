<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Page-only named destination intro paragraph) Tj ET';
$appendixContent = 'BT /F1 12 Tf 72 720 Td (Page-only named destination appendix paragraph) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyAppendix 4 0 R /LegacyIntro 0 /LegacyUnsafe << /S /Launch /F (legacy-run.exe) /D 3 0 R >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Appendix Page) (Review Index)] /Names [(Appendix Page) 12 0 R (Intro Page) << /S /GoTo /D 3 0 R >> (Review Index) 1 (Unsafe Launch) 42 0 R (Missing Page) 99 0 R] >>\nendobj\n"
    . "12 0 obj\n4 0 R\nendobj\n"
    . "42 0 obj\n<< /S /Launch /F (hidden-destination-launch.exe) /D 4 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$names = array_column($destinations, 'name');
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

foreach (['Appendix Page', 'Intro Page', 'Review Index', 'LegacyAppendix', 'LegacyIntro'] as $expected) {
    if (!in_array($expected, $names, true)) {
        throw new RuntimeException("Expected page-only destination {$expected} in WordPress review metadata.");
    }
}
foreach (['Unsafe Launch', 'Missing Page', 'LegacyUnsafe', 'legacy-run.exe', 'hidden-destination-launch.exe'] as $hidden) {
    if ((is_string($encoded) && str_contains($encoded, $hidden)) || str_contains($plainText, $hidden)) {
        throw new RuntimeException("Expected unsafe page-only destination {$hidden} to stay hidden.");
    }
}

echo '<!-- markerpdf-pdf-named-destination-page-only-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog named-destination values that are page references or page indexes normalize to page-only Fit review rows before WordPress import',
    'destination_count' => count($destinations),
    'destination_names' => $names,
    'page_only_fits' => array_values(array_unique(array_column($destinations, 'fit'))) === ['Fit'],
    'page_only_coordinates_empty' => array_reduce(
        $destinations,
        static fn (bool $carry, array $destination): bool => $carry && ($destination['coordinates'] ?? null) === [],
        true
    ),
    'unsafe_page_only_actions_filtered' => !in_array('Unsafe Launch', $names, true)
        && !in_array('LegacyUnsafe', $names, true),
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'Appendix Page')
        && !str_contains($plainText, 'Intro Page')
        && !str_contains($plainText, 'Review Index'),
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
    $json = htmlspecialchars(json_encode($metadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label = htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<li data-marker-named-destination="' . $json . '">' . $label . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
