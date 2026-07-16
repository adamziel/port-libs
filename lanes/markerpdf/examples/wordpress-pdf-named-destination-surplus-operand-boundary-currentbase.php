<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Valid jump Numeric jump String jump Action jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Surplus operand destination target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOk [4 0 R /FitR 1 2 3 4] /LegacyBad [4 0 R /Fit (legacy hidden surplus)] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 142 718] /Dest (Valid Target) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [152 700 238 718] /Dest (Numeric Slop Target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 322 718] /Dest (String Payload Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [332 700 410 718] /Dest (Action Payload Target) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [420 700 484 718] /A << /S /URI /URI (https://example.com/surplus-operand-boundary) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Valid Target) [4 0 R /FitH 700] (Numeric Slop Target) [4 0 R /FitB 111 222] (String Payload Target) [4 0 R /Fit (hidden surplus string)] (Action Payload Target) [4 0 R /XYZ 72 640 0 << /S /URI /URI (https://example.com/hidden-surplus-action) >>]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$linkObjects = $links === [] ? [] : array_column($links[0]['links'], 'annotation_object');

foreach (['String Payload Target', 'Action Payload Target', 'LegacyBad'] as $unexpectedName) {
    if (in_array($unexpectedName, $destinationNames, true)) {
        throw new RuntimeException('Malformed surplus named destination survived WordPress review boundary.');
    }
}
if ($linkObjects !== [7, 8, 11]) {
    throw new RuntimeException('Malformed surplus named destination was promoted into WordPress span metadata.');
}
foreach (['hidden surplus string', 'hidden-surplus-action', 'surplus-operand-boundary'] as $hiddenText) {
    if (str_contains($plainText, $hiddenText)) {
        throw new RuntimeException('Named destination surplus payload leaked into visible WordPress text.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-surplus-operand-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'valid_destination_names' => $destinationNames,
    'nonnumeric_surplus_rejected' => true,
    'numeric_surplus_preserved' => in_array('Numeric Slop Target', $destinationNames, true),
    'promoted_link_annotation_objects' => $linkObjects,
    'visible_text_excludes_surplus_payloads' => true,
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
