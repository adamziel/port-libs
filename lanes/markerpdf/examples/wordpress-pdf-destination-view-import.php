<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageMode /UseOutlines /PageLayout /TwoColumnLeft /OpenAction /review-start >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Full Document) /Parent 5 0 R /Dest [3 0 R /Fit] /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Review Start) /Parent 5 0 R /Dest /review-start /Next 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(review-start) [4 0 R /XYZ 144 640 0]] >>\nendobj\n"
    . "9 0 obj\n<< /Title (Width Fit) /Parent 5 0 R /A << /S /GoTo /D [4 0 R /FitH 700] >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfOutlineExtractor();
$catalogView = $extractor->getCatalogPageViewMetadata($pdf);
$toc = $extractor->getPdfTocWithDestinationViews($pdf);

echo '<!-- markerpdf-pdf-destination-view ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-destination-view-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF catalog /OpenAction and outline destination /Fit*/XYZ view metadata before WordPress navigation review',
    'page_mode' => $catalogView['page_mode'] ?? null,
    'page_layout' => $catalogView['page_layout'] ?? null,
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
    if ($item['view_position'] !== []) {
        $attrs['data-marker-view-position'] = json_encode($item['view_position'], JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
