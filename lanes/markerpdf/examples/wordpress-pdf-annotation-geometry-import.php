<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [5 0 R 6 0 R 7 0 R 8 0 R 9 0 R 11 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Line /Rect [60 596 222 724] /Contents (Arrow review) /L [72 700 216 604] /LE [/OpenArrow /Circle] /LL 12 /LLE 3 /Cap true /IT /LineDimension /CO [4 -6] /C [1 0 0] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Ink /Rect [70 610 210 724] /Contents (Ink review) /InkList 10 0 R /C [0 0 1] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Polygon /Rect [248 604 360 724] /Contents (Triangle review) /Vertices [260 700 330 720 350 620] /IC [0.9 0.9 0.2] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [70 320 260 380] /Contents (Callout review) /CL [72 360 120 344 180 372] /RD [2 2 2 2] /LE [/None /OpenArrow] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Square /Rect [80 410 180 500] /Contents (Square review) /RD [4 6 8 10] /C [0 1 0] >>\nendobj\n"
    . "10 0 obj\n[[72 700 92 708 120 690] [140 640 178 652 206 620]]\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Circle /Rect [240 420 340 500] /Contents (Circle review) /RD [5 0 5 10] /IC [0.2 0.4 0.8] >>\nendobj\n"
    . "%%EOF";

$annotations = [];
foreach ((new PdfAnnotationExtractor())->extractPageAnnotations($pdf) as $page) {
    foreach ($page['annotations'] as $annotation) {
        if (!is_array($annotation['geometry'] ?? null)) {
            continue;
        }
        $annotation['pnum'] = $page['pnum'];
        $annotations[] = $annotation;
    }
}

echo '<!-- markerpdf-pdf-annotation-geometry-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-annotation-review-parser',
    'native_boundary' => 'page /Annots shape geometry for /L, /InkList, /Vertices, /RD, and /CL before WordPress review rendering',
    'annotation_count' => count($annotations),
    'geometry_types' => array_values(array_map(static fn (array $annotation): string => (string) $annotation['geometry']['type'], $annotations)),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'renders_annotation_appearance' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $geometry = $annotation['geometry'];
    $bbox = $geometry['bbox'] ?? $geometry['shape_rect'] ?? $geometry['rect'] ?? null;
    $summary = sprintf(
        '%s geometry: %s%s',
        (string) $annotation['subtype'],
        (string) $geometry['type'],
        is_array($bbox) ? ' bbox ' . implode(',', array_map(static fn (float $value): string => rtrim(rtrim(sprintf('%.2F', $value), '0'), '.'), $bbox)) : ''
    );

    $attrs = [
        'data-marker-page' => (string) $annotation['pnum'],
        'data-marker-annotation-subtype' => (string) $annotation['subtype'],
        'data-marker-annotation-geometry' => (string) $geometry['type'],
    ];

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
