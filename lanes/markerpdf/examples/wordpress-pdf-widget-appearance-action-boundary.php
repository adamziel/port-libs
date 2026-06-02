<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageStream = "BT /F1 12 Tf 72 744 Td (Widget page base text) Tj ET";
$targetStream = "BT /F1 12 Tf 72 744 Td (Widget target page text) Tj ET";
$selectedAppearance = "q BT /F1 10 Tf 90 686 Td (Widget selected AP visible) Tj ET Q";
$offAppearance = "q BT /F1 10 Tf 90 686 Td (Stale widget Off AP hidden) Tj ET Q";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 10 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Fields [20 0 R 22 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 672 320 710] /P 3 0 R /F 4 /Parent 20 0 R /AS /Approved /H /P /MK << /BC [0 0 1] /BG [1 1 0] /CA (Approve import) /RC (Approve rollover) /AC (Approve down) /R 90 /TP 1 >> /AP << /N << /Approved 30 0 R /Off 31 0 R >> >> /A 40 0 R /AA << /Fo 41 0 R /Bl << /S /Hide /T [20 0 R] /H true >> >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 620 260 646] /P 3 0 R /F 36 /Parent 22 0 R /AS /On /A << /S /URI /URI (https://example.com/hidden-widget) >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Rect [72 580 260 606] /Parent 22 0 R /AS /On /A << /S /JavaScript /JS (detachedWidget\\(\\)) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($pageStream) . " >>\nstream\n" . $pageStream . "\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($targetStream) . " >>\nstream\n" . $targetStream . "\nendstream\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (review.consent) /V /Approved /DV /Off /Ff 49152 /Kids [6 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /FT /Tx /T (hidden.token) /V (Hidden Value) /Kids [7 0 R 8 0 R] >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 672 320 710] /Matrix [1 0 0 1 0 0] /Resources << /Font << /F1 9 0 R >> >> /Length " . strlen($selectedAppearance) . " >>\nstream\n" . $selectedAppearance . "\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [72 672 320 710] /Resources << /Font << /F1 9 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n" . $offAppearance . "\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /URI /URI (https://example.com/approve) /Next << /S /GoTo /D [4 0 R /FitH 720] >> >>\nendobj\n"
    . "41 0 obj\n<< /S /JavaScript /JS (focusWidget\\(\\)) >>\nendobj\n"
    . "%%EOF";

$page = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf)[0] ?? ['annotations' => []];
$annotations = $page['annotations'] ?? [];
$widgets = array_values(array_filter(
    $annotations,
    static fn (array $annotation): bool => ($annotation['subtype'] ?? null) === 'Widget'
));
$approved = $widgets[0] ?? [];
$approvedWidget = is_array($approved['widget'] ?? null) ? $approved['widget'] : [];
$hidden = $widgets[1] ?? [];
$hiddenWidget = is_array($hidden['widget'] ?? null) ? $hidden['widget'] : [];
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-page-widget-annotation-review',
    'native_boundary' => 'current page Widget annotations keep field value, AP state, MK appearance characteristics, and A/AA actions as review metadata while PDF actions are not executed',
    'review_annotation_count' => count($annotations),
    'widget_review_count' => count($widgets),
    'approved_field_name' => $approvedWidget['field_name'] ?? null,
    'approved_current_value' => $approvedWidget['current_value'] ?? null,
    'selected_appearance_object' => $approvedWidget['selected_appearance_object'] ?? null,
    'mk_caption' => $approvedWidget['appearance_characteristics']['normal_caption'] ?? null,
    'primary_action_safety' => array_column($approved['actions'] ?? [], 'safety'),
    'additional_action_safety' => array_column($approved['additional_actions'] ?? [], 'safety'),
    'hidden_widget_visibility' => $hiddenWidget['annotation_visibility'] ?? null,
    'selected_appearance_text_imported' => str_contains($visibleText, 'Widget selected AP visible'),
    'stale_appearance_excluded' => !str_contains($visibleText, 'Stale widget Off AP hidden'),
    'field_value_excluded_from_visible_text' => !str_contains($visibleText, 'Hidden Value'),
    'action_payloads_excluded_from_visible_text' => !str_contains($visibleText, 'focusWidget') && !str_contains($visibleText, 'detachedWidget') && !str_contains($visibleText, 'https://example.com/hidden-widget'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'renders_annotation_appearance' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-widget-appearance-action-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($widgets as $annotation) {
    $widget = is_array($annotation['widget'] ?? null) ? $annotation['widget'] : [];
    $attrs = [
        'data-marker-page' => (string) ($page['pnum'] ?? 0),
        'data-marker-widget-field' => (string) ($widget['field_name'] ?? ''),
        'data-marker-widget-visible' => !empty($widget['visible']) ? 'true' : 'false',
        'data-marker-widget-actions-review-only' => 'true',
    ];

    if (isset($widget['appearance_state'])) {
        $attrs['data-marker-ap-state'] = (string) $widget['appearance_state'];
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    $label = sprintf(
        'Widget %s: value %s, visibility %s, actions %d',
        (string) ($widget['field_name'] ?? 'unknown'),
        (string) ($widget['current_value'] ?? 'none'),
        (string) ($widget['annotation_visibility'] ?? 'unknown'),
        (int) ($widget['action_count'] ?? 0)
    );

    echo '<li' . $attrText . '>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
