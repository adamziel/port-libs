<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible calculation order review body) Tj ET';
$selectedAppearance = 'BT /FApp 10 Tf 0 0 Td (Selected widget appearance review) Tj ET';
$offAppearance = 'BT /FApp 10 Tf 0 0 Td (Off widget appearance review) Tj ET';
$widgetCalculateScript = "event.value = this.getField('invoice.amount').value + ' widget';";
$fieldCalculateScript = "event.value = Number(event.value || 0).toFixed(2);";
$compressedWidgetScript = gzcompress($widgetCalculateScript);
$compressedFieldScript = gzcompress($fieldCalculateScript);
if (!is_string($compressedWidgetScript) || !is_string($compressedFieldScript)) {
    throw new RuntimeException('Unable to compress AcroForm calculation-order scripts.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /CO [8 0 R 10 0 R 99 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /DV (0.00) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 260 664] /P 3 0 R /F 4 /AS /Reviewed /AP << /N << /Reviewed 30 0 R /Off 31 0 R >> >> /AA << /C 40 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [12 0 R] /AA << /C 41 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T (internal.note) /V (static note) >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 188 24] /Length " . strlen($selectedAppearance) . " >>\nstream\n{$selectedAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 188 24] /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /JavaScript /JS 50 0 R >>\nendobj\n"
    . "41 0 obj\n<< /S /JavaScript /JS 51 0 R >>\nendobj\n"
    . "50 0 obj\n<< /Length " . strlen($compressedWidgetScript) . " /Filter /FlateDecode >>\nstream\n{$compressedWidgetScript}\nendstream\nendobj\n"
    . "51 0 obj\n<< /Length " . strlen($compressedFieldScript) . " /Filter /FlateDecode >>\nstream\n{$compressedFieldScript}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fields = $form['fields'];
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldsByName[(string) $field['name']] = $field;
}

$orderReview = $form['calculation_order_review'];
$widgetOrder = $orderReview[0] ?? [];
$totalState = is_array($fieldsByName['invoice.total']['calculation_state'] ?? null)
    ? $fieldsByName['invoice.total']['calculation_state']
    : [];
$text = (new PdfTextExtractor())->extractPlainText($pdf);

if (($widgetOrder['target_kind'] ?? null) !== 'widget' || ($widgetOrder['selected_appearance_object'] ?? null) !== 30) {
    throw new RuntimeException('Expected calculation order widget appearance review metadata.');
}
if (($totalState['executes_javascript'] ?? true) !== false || ($totalState['executes_action'] ?? true) !== false) {
    throw new RuntimeException('Calculation review unexpectedly executed a PDF action.');
}
if (str_contains($text, 'this.getField') || str_contains($text, 'toFixed')) {
    throw new RuntimeException('Calculation JavaScript leaked into visible WordPress text.');
}

echo '<!-- markerpdf:pdf-acroform-widget-appearance-calcorder-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-widget-appearance-calcorder-review',
    'native_boundary' => 'AcroForm /CO entries can reference widget annotations; selected /AP /N appearance and /AA /C scripts are review metadata only, and field /V remains authoritative for WordPress import',
    'calculation_order_objects' => array_column($form['calculation_order'], 'object'),
    'calculation_order_fields' => array_column($form['calculation_order'], 'field_name'),
    'calculation_order_target_kinds' => array_column($orderReview, 'target_kind'),
    'widget_order_field' => $widgetOrder['field_name'] ?? null,
    'widget_order_object' => $widgetOrder['widget_object'] ?? null,
    'widget_appearance_state' => $widgetOrder['appearance_state'] ?? null,
    'selected_appearance_object' => $widgetOrder['selected_appearance_object'] ?? null,
    'selected_appearance_sha256' => $widgetOrder['selected_appearance_decoded_sha256'] ?? null,
    'unresolved_order_object' => $orderReview[2]['object'] ?? null,
    'field_values' => [
        'invoice.total' => $fieldsByName['invoice.total']['value'] ?? null,
        'invoice.amount' => $fieldsByName['invoice.amount']['value'] ?? null,
    ],
    'widget_calculate_script_sha256' => hash('sha256', $widgetCalculateScript),
    'appearance_value_used_for_calculation' => false,
    'appearance_value_used_for_import' => false,
    'executes_calculations' => false,
    'executes_javascript' => false,
    'executes_form_actions' => false,
    'executes_appearance_streams' => false,
    'renders_appearances' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($fields as $field) {
    $calculation = is_array($field['calculation_state'] ?? null) ? $field['calculation_state'] : [];
    $parts = [
        (string) $field['field_type_label'],
        'value ' . (string) ($field['value'] ?? 'review-only'),
    ];
    if (($calculation['in_calculation_order'] ?? false) === true) {
        $parts[] = 'calculation order #' . ((int) ($calculation['calculation_order_index'] ?? -1) + 1);
    }
    if (($calculation['calculation_order_widget_object'] ?? null) !== null) {
        $parts[] = 'widget appearance ' . (string) ($calculation['calculation_order_appearance_state'] ?? 'none') . ' reviewed';
    }
    if (($calculation['has_calculate_action'] ?? false) === true) {
        $parts[] = 'calculate action reviewed, not executed';
    }

    echo '<li>' . htmlspecialchars((string) $field['name'] . ': ' . implode('; ', $parts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
