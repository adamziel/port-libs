<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /OpenAction 16 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Indirect Name) /Parent 5 0 R /Dest 12 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Indirect Action) /Parent 5 0 R /A << /S /GoTo /D 16 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Kids [9 0 R 10 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(A) (M)] /Names [12 0 R 13 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(N) (Z)] /Names [16 0 R 17 0 R] >>\nendobj\n"
    . "12 0 obj\n<FEFF0049006E0064006900720065006300740020005200650076006900650077>\nendobj\n"
    . "13 0 obj\n<< /D 14 0 R >>\nendobj\n"
    . "14 0 obj\n[4 0 R /FitBH 620]\nendobj\n"
    . "16 0 obj\n(Page Four)\nendobj\n"
    . "17 0 obj\n<< /D [3 0 R /FitR 10 20 300 740] >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfOutlineExtractor();
$toc = $extractor->getPdfTocWithDestinationViews($pdf);
$catalogView = $extractor->getCatalogPageViewMetadata($pdf);

echo '<!-- markerpdf-pdf-indirect-nametree-destinations ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /Names /Dests indirect string keys and destination dictionaries before WordPress navigation review',
    'toc_count' => count($toc),
    'destination_names' => array_values(array_filter(array_column($toc, 'destination'), 'is_string')),
    'open_action' => $catalogView['open_action'] ?? null,
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
    if ($item['view_mode'] !== null) {
        $attrs['data-marker-view-mode'] = $item['view_mode'];
    }
    if ($item['view_parameters'] !== []) {
        $attrs['data-marker-view-parameters'] = json_encode($item['view_parameters'], JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
