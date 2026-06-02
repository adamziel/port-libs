<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [5 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 680 220 700] /Contents (Editorial note) /T (Reviewer) /C [1 0.5 0] /CA .65 /Border [0 0 2 [3 1]] /Popup 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [240 640 420 720] /Parent 5 0 R /Open true >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [72 640 220 660] /Contents (Highlighted import) /C [0 0.25 1] /CA 1 /BS << /W 4 /S /D /D [2 2] >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [230 620 420 680] /Parent 8 0 R /Open false >>\nendobj\n"
    . "%%EOF";

$pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$annotations = [];
foreach ($pages as $page) {
    foreach ($page['annotations'] as $annotation) {
        $annotation['pnum'] = $page['pnum'];
        $annotations[] = $annotation;
    }
}

echo '<!-- markerpdf-pdf-annotation-border-color-popup-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-annotation-review-parser',
    'native_boundary' => 'page /Annots presentation metadata for /Border, /BS, /C, /CA, and /Popup before Gutenberg review rendering',
    'annotation_count' => count($annotations),
    'popup_count' => count(array_filter($annotations, static fn (array $annotation): bool => is_array($annotation['popup']))),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $border = is_array($annotation['border']) ? $annotation['border'] : [];
    $color = is_array($annotation['border_color']) ? $annotation['border_color'] : [];
    $popup = is_array($annotation['popup']) ? $annotation['popup'] : [];
    $attrs = [
        'data-marker-page' => (string) $annotation['pnum'],
        'data-marker-annotation-subtype' => (string) $annotation['subtype'],
        'data-marker-annotation-color' => (string) ($color['hex'] ?? ''),
        'data-marker-annotation-opacity' => $annotation['opacity'] === null ? '' : (string) $annotation['opacity'],
        'data-marker-annotation-border' => (string) ($border['style'] ?? ''),
        'data-marker-annotation-popup-open' => isset($popup['open']) ? ($popup['open'] ? 'true' : 'false') : '',
    ];

    $attrText = '';
    foreach ($attrs as $name => $value) {
        if ($value === '') {
            continue;
        }
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    $label = trim((string) ($annotation['contents'] ?? 'Annotation'));
    $summary = $annotation['subtype']
        . ': '
        . ($label === '' ? 'review metadata' : $label)
        . ' ['
        . ($color['hex'] ?? 'no color')
        . ', '
        . ($border['style'] ?? 'no border')
        . ', opacity '
        . ($annotation['opacity'] === null ? 'default' : (string) $annotation['opacity'])
        . ']';

    echo '<li' . $attrText . '>' . htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
