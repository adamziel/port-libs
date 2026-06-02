<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$appearance = 'BT /FApp 9 Tf 0 0 Td (Ready appearance review only) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.url) /V (https://example.test/final) /DV (https://example.test/draft) /Kids [8 0 R] /AA << /V 20 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 340 664] /P 3 0 R /F 4 /AS /Ready /AP << /N << /Ready 30 0 R /Off 31 0 R >> >> /A 24 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.test/review) /Next [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /S /Launch /F (cmd.exe) /Next 23 0 R >>\nendobj\n"
    . "22 0 obj\n<< /S /JavaScript /JS (app.alert\\('cycled nested action blocked'\\)) /Next 20 0 R >>\nendobj\n"
    . "23 0 obj\n<< /S /Hide /T [8 0 R] /H false >>\nendobj\n"
    . "24 0 obj\n<< /S /Named /N /Print /Next << /S /GoTo /D [3 0 R /Fit] >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$fields = (new PdfAcroFormExtractor())->extractFields($pdf);
$field = $fields[0] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Expected one AcroForm field.');
}

$widget = $field['widgets'][0] ?? [];
$appearanceReview = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : [];
$selectedAppearance = is_array($appearanceReview['selected_appearance'] ?? null) ? $appearanceReview['selected_appearance'] : [];
$fieldActions = is_array($field['actions'] ?? null) ? $field['actions'] : [];
$widgetActions = is_array($widget['actions'] ?? null) ? $widget['actions'] : [];

$fieldActionTypes = array_column($fieldActions, 'action_type');
$widgetActionTypes = array_column($widgetActions, 'action_type');
if ($fieldActionTypes !== ['URI', 'Launch', 'Hide', 'JavaScript'] || $widgetActionTypes !== ['Named', 'GoTo']) {
    throw new RuntimeException('Expected nested AcroForm action chain review rows.');
}

$executionFlags = array_merge(
    array_column($fieldActions, 'executes_action'),
    array_column($widgetActions, 'executes_action')
);
if (array_filter($executionFlags, static fn (mixed $flag): bool => $flag !== false) !== []) {
    throw new RuntimeException('AcroForm actions must remain review-only.');
}

echo '<!-- markerpdf:pdf-acroform-nested-action-value-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-field-action-next',
    'native_boundary' => 'AcroForm /A and /AA /Next chains are review metadata; field /V remains authoritative and widget /AP appearance streams are not executed or imported as text',
    'field_name' => $field['name'] ?? null,
    'field_value' => $field['value'] ?? null,
    'default_value' => $field['default_value'] ?? null,
    'changed_from_default' => $field['value_state']['changed_from_default'] ?? null,
    'appearance_state' => $widget['appearance_state'] ?? null,
    'selected_appearance_object' => $selectedAppearance['object'] ?? null,
    'selected_appearance_imports_visible_text' => $selectedAppearance['imports_visible_text'] ?? null,
    'field_action_types' => $fieldActionTypes,
    'widget_action_types' => $widgetActionTypes,
    'chained_field_action_count' => count(array_filter(
        $fieldActions,
        static fn (array $action): bool => ($action['chained'] ?? false) === true
    )),
    'cycle_blocked' => count(array_filter(
        $fieldActions,
        static fn (array $action): bool => ($action['action_type'] ?? null) === 'URI'
    )) === 1,
    'hide_target_names' => $fieldActions[2]['field_names'] ?? [],
    'appearance_value_used_for_import' => false,
    'appearance_stream_text_exposed' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars((string) $field['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ': ' . htmlspecialchars((string) $field['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '; field actions ' . htmlspecialchars(implode(' -> ', $fieldActionTypes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '; widget actions ' . htmlspecialchars(implode(' -> ', $widgetActionTypes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
