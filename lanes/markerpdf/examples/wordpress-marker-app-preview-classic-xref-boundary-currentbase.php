<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerAppPreview;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /PageLabels << /Nums [0 << /P (Current-) /S /D /St 5 >>] >> >>');
$addObject(2, '<< /Type /Pages /MediaBox [0 0 400 600] /CropBox [20 30 380 540] /Rotate 90 /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /UserUnit 2 >>');

$pdf .= "xref\n"
    . "0 4\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . "trailer\n<< /Size 40 /Root 1 0 R >>\n"
    . "startxref\n999999\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /PageLabels << /Nums [0 << /P (Decoy-) /S /D /St 99 >>] >> >>');
$addObject(21, '<< /Type /Pages /MediaBox [0 0 612 792] /Kids [22 0 R 23 0 R] /Count 2 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /CropBox [0 0 200 200] >>');
$addObject(23, '<< /Type /Page /Parent 21 0 R /CropBox [0 0 300 300] >>');

$decoyXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "20 4\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . "trailer\n<< /Size 40 /Root 20 0 R >>\n"
    . "% startxref\n{$decoyXrefOffset}\n%%EOF";

$preview = new MarkerAppPreview();
$summary = $preview->openPdfSummary($pdf);
$plan = $preview->getPageImagePlan($pdf, 1, 72.0);

$smoke = [
    'scenario' => 'wordpress-marker-app-preview-classic-xref-boundary-currentbase',
    'native_boundary' => 'MarkerAppPreview skips commented startxref tokens before selecting preview page count labels and page geometry',
    'current_preview_root_selected' => ($summary['page_count'] ?? null) === 1,
    'current_page_object_selected' => ($summary['pages'][0]['object_id'] ?? null) === 3,
    'current_page_label_selected' => ($plan['page_label'] ?? null) === 'Current-5',
    'current_geometry_selected' => ($plan['page_bbox'] ?? null) === [20.0, 30.0, 380.0, 540.0],
    'decoy_preview_excluded' => !in_array('Decoy-99', array_column($summary['pages'], 'page_label'), true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'current_preview_root_selected',
    'current_page_object_selected',
    'current_page_label_selected',
    'current_geometry_selected',
    'decoy_preview_excluded',
] as $required) {
    if (($smoke[$required] ?? false) !== true) {
        throw new RuntimeException('MarkerAppPreview classic xref boundary smoke failed: ' . $required);
    }
}

echo '<!-- markerpdf-marker-app-preview-classic-xref-boundary-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:html -->\n";
echo '<div data-marker-preview=\'' . htmlspecialchars(json_encode([
    'page_count' => $summary['page_count'],
    'page_label' => $plan['page_label'],
    'rendered_image_size' => $plan['rendered_image_size'],
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'></div>\n";
echo "<!-- /wp:html -->\n";
