<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$outlinePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /Dests << /Appendix [4 0 R /Fit] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Overview) /Parent 5 0 R /Dest (wp-overview) /Next 7 0 R /First 9 0 R /Count 1 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Appendix) /Parent 5 0 R /A << /S /GoTo /D /Appendix >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(wp-overview) [3 0 R /XYZ null null null] (wp-review) [4 0 R /FitH 640]] >>\nendobj\n"
    . "9 0 obj\n<< /Title (Review Checklist) /Parent 6 0 R /Dest /wp-review >>\nendobj\n"
    . "%%EOF";

$pdfDocEncodedDestinationName = 'wp' . chr(0x80) . 'review';
$destinationPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /migration-start [4 0 R /Fit] /legacy-review [4 0 R /FitV 110] /legacy-stale 13 1 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(media-cleanup) (z-export)] /Kids [9 0 R 10 0 R 11 1 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(media-cleanup) (migration-start)] /Names [(migration-start) [3 0 R /Fit] (media-cleanup) [4 0 R /XYZ 72 650 1] (stale-secret) [4 0 R /Fit] (stale-page-generation) [4 1 R /Fit] 12 1 R [4 0 R /Fit]] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(review-summary) (z-export)] /Names [(migration-start) [4 0 R /Fit] (review-summary) [3 0 R /FitBH 600] (section-indirect-review) [4 0 R 14 0 R 15 0 R] ({$pdfDocEncodedDestinationName}) [4 0 R /FitH 540]] >>\nendobj\n"
    . "11 0 obj\n<< /Limits [(review-summary) (z-export)] /Names [(stale-kid-generation) [4 0 R /Fit]] >>\nendobj\n"
    . "12 0 obj\n(stale-indirect-generation)\nendobj\n"
    . "13 0 obj\n<< /D [4 0 R /FitH 120] >>\nendobj\n"
    . "14 0 obj\n/FitH\nendobj\n"
    . "15 0 obj\n510\nendobj\n"
    . "%%EOF\n";

$toc = (new PdfOutlineExtractor())->getPdfToc($outlinePdf);
$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($destinationPdf);
$destinationNames = array_column($destinations, 'name');

if ($destinationNames !== ['migration-start', 'media-cleanup', 'review-summary', 'section-indirect-review', 'wp' . "\u{2022}" . 'review', 'legacy-review']) {
    throw new RuntimeException('Expected name-tree /Limits to exclude stale named destinations before WordPress import metadata.');
}
if (in_array('stale-secret', $destinationNames, true)) {
    throw new RuntimeException('Expected stale out-of-limits destination rows to stay hidden from WordPress output.');
}
foreach (['stale-page-generation', 'stale-indirect-generation', 'stale-kid-generation', 'legacy-stale'] as $staleName) {
    if (in_array($staleName, $destinationNames, true)) {
        throw new RuntimeException('Expected generation-mismatched destination rows to stay hidden from WordPress output.');
    }
}

echo '<!-- markerpdf-pdf-named-destinations ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-and-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests name-tree /Limits, generation, PDFDocEncoding key, and indirect view-operand boundaries before WordPress named-destination import metadata',
    'toc_count' => count($toc),
    'destination_count' => count($destinations),
    'outline_destination_names' => array_values(array_filter(array_column($toc, 'destination'), 'is_string')),
    'named_destinations' => $destinationNames,
    'out_of_limits_destination_filtered' => true,
    'generation_mismatch_destinations_filtered' => true,
    'pdfdocencoded_destination_name_decoded' => in_array('wp' . "\u{2022}" . 'review', $destinationNames, true),
    'indirect_destination_operands_resolved' => ($destinations[3]['fit'] ?? null) === 'FitH'
        && ($destinations[3]['coordinates'] ?? null) === ['top' => 510.0],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    $attrs = [
        'data-marker-outline-level' => (string) $item['level'],
        'data-marker-outline-page' => (string) $item['page'],
    ];
    if ($item['destination'] !== null) {
        $attrs['data-marker-destination-name'] = $item['destination'];
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
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
