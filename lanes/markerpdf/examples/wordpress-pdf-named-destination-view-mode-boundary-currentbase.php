<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current destination target page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Review destination target page) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBad [4 0 R /Launch 88] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Current Fit) [3 0 R /FitH 700] (Current Unknown View) [3 0 R /Launch 77] (Indirect Unknown View) [4 0 R 9 0 R 88] (Action Unknown View) << /S /GoTo /D [4 0 R /Movie 99] >> (Valid Bounding Box Fit) [4 0 R /FitB 111 222]] >>\nendobj\n"
    . "9 0 obj\n/RichMedia\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$names = array_column($destinations, 'name');
$fits = array_column($destinations, 'fit');

foreach (['Current Unknown View', 'Indirect Unknown View', 'Action Unknown View', 'LegacyBad'] as $unexpectedName) {
    if (in_array($unexpectedName, $names, true)) {
        throw new RuntimeException('Unexpected malformed named destination survived view-mode boundary.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-view-mode-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'unknown_view_names_rejected' => $names === ['Current Fit', 'Valid Bounding Box Fit', 'LegacyOk'],
    'valid_fitb_surplus_operands_ignored' => ($fits[1] ?? null) === 'FitB' && ($destinations[1]['coordinates'] ?? null) === [],
    'legacy_valid_destination_preserved' => ($destinations[2]['source'] ?? null) === 'legacy-dests',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $attrs = [
        'data-marker-destination-page' => $destination['page'] === null ? '' : (string) ($destination['page'] + 1),
        'data-marker-fit' => $destination['fit'],
        'data-marker-source' => $destination['source'],
    ];

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
