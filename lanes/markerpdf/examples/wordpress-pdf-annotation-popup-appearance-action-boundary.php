<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageStream = "BT /F1 12 Tf 72 744 Td (Page visible text) Tj ET";
$targetStream = "BT /F1 12 Tf 72 744 Td (Destination target text) Tj ET";
$selectedAppearance = "q BT /F1 10 Tf 90 684 Td (Current popup AP visible) Tj ET Q";
$offAppearance = "q BT /F1 10 Tf 90 684 Td (Stale Off AP hidden) Tj ET Q";
$freeTextAppearance = "q BT /F1 10 Tf 84 628 Td (FreeText AP visible) Tj ET Q";
$staleAppearance = "q BT /F1 10 Tf 84 560 Td (Detached stale AP hidden) Tj ET Q";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 18 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [6 0 R 9 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 16 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 664 260 720] /Contents (Reviewer note) /T (Import QA) /AS /Review /C [1 0.8 0] /AP << /N << /Review 7 0 R /Off 8 0 R >> >> /Popup 9 0 R /A 10 0 R /AA << /E << /S /URI /URI (https://example.com/hover-review) >> /D << /S /JavaScript /JS (downReview\\(\\)) >> >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 664 260 720] /Matrix [1 0 0 1 0 0] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($selectedAppearance) . " >>\nstream\n" . $selectedAppearance . "\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 664 260 720] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n" . $offAppearance . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [270 650 450 730] /Parent 6 0 R /Open true /Contents (Popup text review only) >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/review) /Next << /S /GoTo /D (annotation-target) >> >>\nendobj\n"
    . "11 0 obj\n[4 0 R /XYZ 42 700 0]\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 608 280 650] /Contents (FreeText local jump) /AP << /N 13 0 R >> /Dest [4 0 R /FitH 720] /AA << /Fo << /S /Launch /F (unsafe-helper.exe) >> >> /Popup << /Type /Annot /Subtype /Popup /Rect [300 600 470 660] /Open false /Contents (Direct popup review only) >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 608 280 650] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($freeTextAppearance) . " >>\nstream\n" . $freeTextAppearance . "\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 540 260 590] /Contents (Detached stale annotation) /AS /On /AP << /N << /On 17 0 R >> >> /A << /S /JavaScript /JS (staleDetached\\(\\)) >> >>\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n" . $pageStream . "\nendstream\nendobj\n"
    . "16 0 obj\n<< /Length " . strlen($targetStream) . " >>\nstream\n" . $targetStream . "\nendstream\nendobj\n"
    . "17 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 540 260 590] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($staleAppearance) . " >>\nstream\n" . $staleAppearance . "\nendstream\nendobj\n"
    . "18 0 obj\n<< /Names [(annotation-target) 11 0 R] >>\nendobj\n"
    . "%%EOF";

$page = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0] ?? ['annotations' => []];
$annotations = $page['annotations'] ?? [];
$note = $annotations[0] ?? [];
$freeText = $annotations[1] ?? [];
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-annotation-review-parser',
    'native_boundary' => 'current page /Annots popup, selected /AP, /A, /AA, and /Dest review metadata before WordPress import',
    'review_annotation_count' => count($annotations),
    'popup_child_nested' => ($note['popup']['object'] ?? null) === 9,
    'selected_appearance_state' => $note['appearance']['normal']['selected_state'] ?? null,
    'selected_appearance_object' => $note['appearance']['normal']['selected']['object'] ?? null,
    'primary_action_safety' => array_column($note['actions'] ?? [], 'safety'),
    'additional_action_safety' => array_column($note['additional_actions'] ?? [], 'safety'),
    'direct_dest_view_mode' => $freeText['actions'][0]['view_mode'] ?? null,
    'launch_action_review_only' => ($freeText['additional_actions'][0]['safety'] ?? null) === 'blocked-launch',
    'popup_text_excluded_from_visible_text' => !str_contains($visibleText, 'Popup text review only') && !str_contains($visibleText, 'Direct popup review only'),
    'stale_appearance_excluded_from_visible_text' => !str_contains($visibleText, 'Stale Off AP hidden') && !str_contains($visibleText, 'Detached stale AP hidden'),
    'action_scripts_excluded_from_visible_text' => !str_contains($visibleText, 'downReview') && !str_contains($visibleText, 'staleDetached'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'renders_annotation_appearance' => false,
];

echo '<!-- markerpdf-pdf-annotation-popup-appearance-action-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $attrs = [
        'data-marker-page' => (string) ($page['pnum'] ?? 0),
        'data-marker-annotation-subtype' => (string) ($annotation['subtype'] ?? ''),
        'data-marker-review-only' => 'true',
        'data-marker-action-count' => (string) count($annotation['actions'] ?? []),
        'data-marker-aa-count' => (string) count($annotation['additional_actions'] ?? []),
    ];

    if (isset($annotation['appearance']['normal']['selected_state'])) {
        $attrs['data-marker-ap-state'] = (string) $annotation['appearance']['normal']['selected_state'];
    }

    if (isset($annotation['popup']['open'])) {
        $attrs['data-marker-popup-open'] = $annotation['popup']['open'] ? 'true' : 'false';
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    $label = sprintf(
        '%s review: %s',
        (string) ($annotation['subtype'] ?? 'Annotation'),
        (string) ($annotation['contents'] ?? 'annotation')
    );
    echo '<li' . $attrText . '>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
