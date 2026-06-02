<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageStream = "BT /F1 12 Tf 72 744 Td (Page action boundary text) Tj ET";
$selectedAppearance = "q BT /F1 10 Tf 92 686 Td (Standard action AP visible) Tj ET Q";
$offAppearance = "q BT /F1 10 Tf 92 686 Td (Standard action Off AP hidden) Tj ET Q";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 11 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n" . $pageStream . "\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 660 280 724] /Contents (Standard action review note) /T (Action QA) /AS /On /AP << /N << /On 8 0 R /Off 9 0 R >> >> /Popup 10 0 R /A 7 0 R /AA << /E 13 0 R /D 14 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /S /Named /N /Print /Next [15 0 R 16 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 660 280 724] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($selectedAppearance) . " >>\nstream\n" . $selectedAppearance . "\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 660 280 724] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n" . $offAppearance . "\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [300 650 470 730] /Parent 6 0 R /Open true /Contents (Standard action popup review only) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [320 620 480 690] /Parent 6 0 R /Open false /Contents (Duplicate stale popup hidden) >>\nendobj\n"
    . "12 0 obj\n<< /T (review.widget) >>\nendobj\n"
    . "13 0 obj\n<< /S /Hide /T [(review.name) 12 0 R] /H false >>\nendobj\n"
    . "14 0 obj\n<< /S /ResetForm /Fields [(review.name)] /Flags 1 >>\nendobj\n"
    . "15 0 obj\n<< /S /ImportData /F << /F (review-data.fdf) >> >>\nendobj\n"
    . "16 0 obj\n<< /S /SubmitForm /F (https://example.com/submit) /Fields [(review.name) 12 0 R] /Flags 6 >>\nendobj\n"
    . "%%EOF";

$page = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0] ?? ['annotations' => []];
$annotations = $page['annotations'] ?? [];
$note = $annotations[0] ?? [];
$actions = $note['actions'] ?? [];
$additionalActions = $note['additional_actions'] ?? [];
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-annotation-action-review-parser',
    'native_boundary' => 'current page annotation /A, /AA, selected /AP, and nested Popup review metadata before WordPress import',
    'review_annotation_count' => count($annotations),
    'popup_child_nested' => ($note['popup']['object'] ?? null) === 10,
    'selected_appearance_state' => $note['appearance']['normal']['selected_state'] ?? null,
    'primary_action_types' => array_column($actions, 'action_type'),
    'primary_action_safety' => array_column($actions, 'safety'),
    'additional_action_types' => array_column($additionalActions, 'action_type'),
    'additional_action_safety' => array_column($additionalActions, 'safety'),
    'named_action' => $actions[0]['named_action'] ?? null,
    'import_file' => $actions[1]['file'] ?? null,
    'submit_target' => $actions[2]['file'] ?? null,
    'submit_field_names' => $actions[2]['field_names'] ?? [],
    'hide_operation' => $additionalActions[0]['operation'] ?? null,
    'reset_fields_mode' => $additionalActions[1]['fields_mode'] ?? null,
    'selected_appearance_visible' => str_contains($visibleText, 'Standard action AP visible'),
    'off_appearance_excluded_from_visible_text' => !str_contains($visibleText, 'Standard action Off AP hidden'),
    'popup_text_excluded_from_visible_text' => !str_contains($visibleText, 'Standard action popup review only')
        && !str_contains($visibleText, 'Duplicate stale popup hidden'),
    'action_targets_excluded_from_visible_text' => !str_contains($visibleText, 'review-data.fdf')
        && !str_contains($visibleText, 'https://example.com/submit'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-annotation-action-appearance-popup-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

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

    echo '<li' . $attrText . '>' . htmlspecialchars((string) ($annotation['contents'] ?? 'annotation review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
