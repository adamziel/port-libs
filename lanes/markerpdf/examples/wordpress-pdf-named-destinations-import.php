<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
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

$toc = (new PdfOutlineExtractor())->getPdfToc($pdf);

echo '<!-- markerpdf-pdf-named-destinations ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'toc_count' => count($toc),
    'destination_names' => array_values(array_filter(array_column($toc, 'destination'), 'is_string')),
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
echo "</ul>\n<!-- /wp:list -->\n";
