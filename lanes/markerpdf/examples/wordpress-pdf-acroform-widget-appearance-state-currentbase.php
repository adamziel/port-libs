<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$approvedAppearance = 'BT /FApp 9 Tf 0 0 Td (Approved widget appearance review) Tj ET';
$yesAppearance = 'BT /FApp 9 Tf 0 0 Td (Yes widget appearance review) Tj ET';
$onlineAppearance = 'BT /FApp 9 Tf 0 0 Td (Online radio appearance review) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R 16 0 R 18 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Btn /T (article.approval) /V /Approved /DV /Approved /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 96 664] /P 3 0 R /F 4 /AS /Draft /AP << /N << /Approved 30 0 R /Off 31 0 R >> >> >>\nendobj\n"
    . "10 0 obj\n<< /FT /Btn /T (newsletter.optin) /DV /Yes /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 96 624] /P 3 0 R /F 4 /AS /Maybe /AP << /N << /Yes 32 0 R /Off 31 0 R >> >> >>\nendobj\n"
    . "14 0 obj\n<< /FT /Btn /T (delivery.method) /Ff 49152 /V /Online /DV /Pickup /Kids [16 0 R 18 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 96 584] /P 3 0 R /F 4 /AS /Online /AP << /N << /Online 33 0 R /Off 31 0 R >> >> >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [108 560 132 584] /P 3 0 R /F 4 /AS /Off /AP << /N << /Pickup 34 0 R /Off 31 0 R >> >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($approvedAppearance) . " >>\nstream\n{$approvedAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($yesAppearance) . " >>\nstream\n{$yesAppearance}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($onlineAppearance) . " >>\nstream\n{$onlineAppearance}\nendstream\nendobj\n"
    . "34 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$rows = [];
foreach ($fields as $field) {
    $review = is_array($field['widget_current_base_review'] ?? null) ? $field['widget_current_base_review'] : [];
    $states = [];
    foreach ($field['widgets'] ?? [] as $widget) {
        if (is_array($widget['current_base_state'] ?? null)) {
            $states[] = $widget['current_base_state'];
        }
    }

    $rows[] = [
        'field' => $field['name'] ?? null,
        'current' => $review['current'] ?? null,
        'current_source' => $review['current_source'] ?? null,
        'default' => $review['default'] ?? null,
        'changed_from_default' => $review['changed_from_default'] ?? null,
        'state_consistent' => $review['state_consistent'] ?? null,
        'stale_widgets' => $review['stale_appearance_widgets'] ?? [],
        'widget_states' => array_map(
            static fn (array $state): array => [
                'widget' => $state['widget_object'] ?? null,
                'appearance_state' => $state['appearance_state'] ?? null,
                'valid' => $state['appearance_state_valid'] ?? null,
                'checked_by_widget' => $state['checked_by_widget_appearance'] ?? null,
                'selected_by_field' => $state['selected_by_field_value'] ?? null,
            ],
            $states
        ),
    ];
}

$approval = $rows[0] ?? [];
$optin = $rows[1] ?? [];
$delivery = $rows[2] ?? [];

if (($approval['current'] ?? null) !== 'Approved' || ($approval['state_consistent'] ?? null) !== false) {
    throw new RuntimeException('Expected field /V to remain authoritative over stale approval widget /AS.');
}
$optinCurrent = array_key_exists('current', $optin) ? $optin['current'] : 'unexpected';
if ($optinCurrent !== null || ($optin['current_source'] ?? null) !== 'missing_or_off') {
    throw new RuntimeException('Expected stale opt-in widget /AS not to synthesize a checked value.');
}
if (($delivery['current'] ?? null) !== 'Online' || ($delivery['state_consistent'] ?? null) !== true) {
    throw new RuntimeException('Expected valid radio widget /AS states to remain consistent with field /V.');
}

echo '<!-- markerpdf:pdf-acroform-widget-appearance-state-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-appearance-state-currentbase',
    'native_boundary' => 'AcroForm widget /AS states are checked against /AP /N names before WordPress import; field /V remains authoritative and stale appearances are review metadata only',
    'field_count' => count($rows),
    'stale_widget_objects' => array_values(array_merge(
        $approval['stale_widgets'] ?? [],
        $optin['stale_widgets'] ?? []
    )),
    'approval_current' => $approval['current'] ?? null,
    'approval_current_source' => $approval['current_source'] ?? null,
    'approval_state_consistent' => $approval['state_consistent'] ?? null,
    'optin_current' => $optin['current'] ?? null,
    'optin_current_source' => $optin['current_source'] ?? null,
    'delivery_current' => $delivery['current'] ?? null,
    'delivery_state_consistent' => $delivery['state_consistent'] ?? null,
    'appearance_value_used_for_import' => false,
    'appearance_payload_text_exposed' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($rows as $row) {
    echo '<li>' . htmlspecialchars(
        (string) $row['field'] . ': current=' . (is_scalar($row['current']) ? (string) $row['current'] : 'none')
        . '; source=' . (string) ($row['current_source'] ?? 'none')
        . '; stale_widgets=' . implode(',', array_map('strval', $row['stale_widgets'] ?? [])),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
