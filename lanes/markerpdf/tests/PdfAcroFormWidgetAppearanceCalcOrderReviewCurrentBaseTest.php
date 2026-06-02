<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$widgetAppearanceCalcOrderPdf = static function (): array {
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

    return [$pdf, $selectedAppearance, $widgetCalculateScript, $fieldCalculateScript];
};

return [
    'reviews widget appearance metadata for AcroForm calculation order entries without executing calculations' => static function (TestRunner $t) use ($widgetAppearanceCalcOrderPdf, $fieldsByName): void {
        [$pdf, $selectedAppearance, $widgetCalculateScript, $fieldCalculateScript] = $widgetAppearanceCalcOrderPdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $orderReview = $form['calculation_order_review'];
        $total = $fields['invoice.total'];
        $amount = $fields['invoice.amount'];
        $note = $fields['internal.note'];
        $totalState = $total['calculation_state'];
        $amountState = $amount['calculation_state'];
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same([
            ['object' => 8, 'field_name' => 'invoice.total'],
            ['object' => 10, 'field_name' => 'invoice.amount'],
            ['object' => 99, 'field_name' => null],
        ], $form['calculation_order']);

        $t->same(['widget', 'field', 'unresolved'], array_column($orderReview, 'target_kind'));
        $t->same([8, null, null], array_column($orderReview, 'widget_object'));
        $t->same([6, 10, null], array_column($orderReview, 'field_object'));
        $t->same(['invoice.total', 'invoice.amount', null], array_column($orderReview, 'field_name'));
        $t->same([true, false, false], array_column($orderReview, 'resolved_from_widget'));
        $t->same([false, false, true], array_column($orderReview, 'unresolved'));

        $widgetReview = $orderReview[0];
        $t->same('acroform_calculation_order_review_boundary', $widgetReview['source']);
        $t->same('Reviewed', $widgetReview['appearance_state']);
        $t->same(['Reviewed', 'Off'], $widgetReview['appearance_states']);
        $t->same('state_dictionary', $widgetReview['normal_appearance_type']);
        $t->same('Reviewed', $widgetReview['selected_appearance_state']);
        $t->same(30, $widgetReview['selected_appearance_object']);
        $t->same(hash('sha256', $selectedAppearance), $widgetReview['selected_appearance_decoded_sha256']);
        $t->true($widgetReview['state_matches_appearance']);
        $t->same(false, $widgetReview['stale_appearance_state']);
        $t->same(false, $widgetReview['appearance_value_used_for_calculation']);
        $t->same(false, $widgetReview['appearance_value_used_for_import']);
        $t->same(false, $widgetReview['executes_calculation']);
        $t->same(false, $widgetReview['executes_javascript']);
        $t->same(false, $widgetReview['executes_action']);
        $t->same(false, $widgetReview['executes_appearance_streams']);
        $t->same(false, $widgetReview['renders_appearances']);

        $directReview = $orderReview[1];
        $t->same('field', $directReview['target_kind']);
        $t->same(10, $directReview['field_object']);
        $t->same(null, $directReview['widget_object']);
        $t->same([], $directReview['appearance_states']);
        $t->same(null, $directReview['selected_appearance_object']);

        $unresolvedReview = $orderReview[2];
        $t->same(99, $unresolvedReview['object']);
        $t->same('unresolved', $unresolvedReview['target_kind']);
        $t->true($unresolvedReview['unresolved']);
        $t->same(false, $unresolvedReview['executes_calculation']);

        $t->same('27.06', $total['value']);
        $t->true($totalState['in_calculation_order']);
        $t->same(0, $totalState['calculation_order_index']);
        $t->same(8, $totalState['calculation_order_object']);
        $t->same('widget', $totalState['calculation_order_target_kind']);
        $t->same(6, $totalState['calculation_order_field_object']);
        $t->same(8, $totalState['calculation_order_widget_object']);
        $t->true($totalState['calculation_order_resolved_from_widget']);
        $t->same('Reviewed', $totalState['calculation_order_appearance_state']);
        $t->same(['Reviewed', 'Off'], $totalState['calculation_order_appearance_states']);
        $t->same(30, $totalState['calculation_order_selected_appearance_object']);
        $t->same(false, $totalState['calculation_order_stale_appearance_state']);
        $t->true($totalState['has_calculate_action']);
        $t->same('widget', $totalState['calculate_actions'][0]['source']);
        $t->same(8, $totalState['calculate_actions'][0]['source_object']);
        $t->same(40, $totalState['calculate_actions'][0]['action_object']);
        $t->same(50, $totalState['calculate_actions'][0]['script_object']);
        $t->same(hash('sha256', $widgetCalculateScript), $totalState['calculate_actions'][0]['script_sha256']);
        $t->same(['FlateDecode'], $totalState['calculate_actions'][0]['script_filters']);
        $t->same(false, $totalState['appearance_value_used_for_calculation']);
        $t->same(false, $totalState['executes_javascript']);
        $t->same(false, $totalState['executes_action']);
        $t->same(false, $totalState['executes_appearance_streams']);

        $t->true($amountState['in_calculation_order']);
        $t->same(1, $amountState['calculation_order_index']);
        $t->same(10, $amountState['calculation_order_object']);
        $t->same('field', $amountState['calculation_order_target_kind']);
        $t->same(10, $amountState['calculation_order_field_object']);
        $t->same(null, $amountState['calculation_order_widget_object']);
        $t->same(false, $amountState['calculation_order_resolved_from_widget']);
        $t->same(hash('sha256', $fieldCalculateScript), $amountState['calculate_actions'][0]['script_sha256']);
        $t->same(51, $amountState['calculate_actions'][0]['script_object']);
        $t->same(false, $amountState['executes_javascript']);

        $t->same(false, $note['calculation_state']['in_calculation_order']);
        $t->same(null, $note['calculation_state']['calculation_order_target_kind']);
        $t->same(false, $note['calculation_state']['has_calculate_action']);

        $t->true(str_contains($text, 'Visible calculation order review body'));
        $t->true(!str_contains($text, 'this.getField'));
        $t->true(!str_contains($text, 'toFixed'));
    },
];
