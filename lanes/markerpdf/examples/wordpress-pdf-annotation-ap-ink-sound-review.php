<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageStream = "BT /F1 12 Tf 72 744 Td (Page visible text) Tj ET";
$inkAppearance = "q BT /F1 10 Tf 72 690 Td (Ink AP selected) Tj ET Q";
$offAppearance = "q BT /F1 10 Tf 72 690 Td (Stale Off AP) Tj ET Q";
$soundAppearance = "q BT /F1 10 Tf 260 690 Td (Sound AP review) Tj ET Q";
$soundBytes = "LOUD PAYLOAD TEXT";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 8 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n" . $pageStream . "\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Ink /Rect [60 660 230 724] /Contents (Reviewer ink) /InkList [[72 700 92 708 120 690] [150 682 198 704]] /C [0 0 1] /BS << /W 2 /S /S >> /AS /BlueStroke /AP << /N << /BlueStroke 7 0 R /Off 12 0 R >> /R 13 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [60 660 230 724] /Matrix [1 0 0 1 0 0] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($inkAppearance) . " >>\nstream\n" . $inkAppearance . "\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Sound /Rect [250 660 360 724] /Contents (Audio review) /Name /Speaker /Sound 9 0 R /AP << /N 11 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /R 44100 /C 2 /B 16 /E /Signed /CO /ALaw /Filter /ASCIIHexDecode /Length " . strlen($soundBytes) . " >>\nstream\n" . bin2hex($soundBytes) . ">\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [252 620 420 700] /Parent 8 0 R /Open false /Contents (Audio popup review) >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [250 660 360 724] /Matrix [1 0 0 1 0 0] /Length " . strlen($soundAppearance) . " >>\nstream\n" . $soundAppearance . "\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [60 660 230 724] /Length " . strlen($offAppearance) . " >>\nstream\n" . $offAppearance . "\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /BBox [60 660 230 724] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$page = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0] ?? ['annotations' => []];
$annotations = $page['annotations'];
$ink = $annotations[0] ?? [];
$sound = $annotations[1] ?? [];
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-annotation-review-parser',
    'native_boundary' => 'page /Annots /AP state dictionaries plus /Sound streams are review metadata before WordPress import',
    'review_annotation_count' => count($annotations),
    'ink_paths' => count($ink['geometry']['paths'] ?? []),
    'ink_selected_ap_state' => $ink['appearance']['normal']['selected_state'] ?? null,
    'ink_selected_ap_object' => $ink['appearance']['normal']['selected']['object'] ?? null,
    'sound_stream_object' => $sound['sound']['stream_object'] ?? null,
    'sound_sample_rate' => $sound['sound']['sample_rate'] ?? null,
    'sound_filters' => $sound['sound']['filters'] ?? [],
    'sound_payload_text_excluded' => !str_contains($visibleText, $soundBytes),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_media' => false,
    'renders_annotation_appearance' => false,
];

echo '<!-- markerpdf-pdf-annotation-ap-ink-sound-review-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $label = sprintf(
        '%s review: %s',
        (string) $annotation['subtype'],
        (string) ($annotation['contents'] ?? 'annotation')
    );

    $attrs = [
        'data-marker-page' => (string) ($page['pnum'] ?? 0),
        'data-marker-annotation-subtype' => (string) $annotation['subtype'],
        'data-marker-review-only' => 'true',
    ];

    if (isset($annotation['appearance']['normal']['selected_state'])) {
        $attrs['data-marker-ap-state'] = (string) $annotation['appearance']['normal']['selected_state'];
    }

    if (isset($annotation['sound']['sample_rate'])) {
        $attrs['data-marker-sound-rate'] = (string) $annotation['sound']['sample_rate'];
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
