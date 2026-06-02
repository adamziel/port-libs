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

$rowsByFieldName = static function (array $rows): array {
    $indexed = [];
    foreach ($rows as $row) {
        $indexed[$row['field_name']] = $row;
    }

    return $indexed;
};

$widgetAppearanceExportPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible button export review body) Tj ET';
    $groundAppearance = 'BT /FApp 9 Tf 0 0 Td (Ground appearance stream text) Tj ET';
    $fastAppearance = 'BT /FApp 9 Tf 0 0 Td (Fast appearance stream text) Tj ET';
    $yesAppearance = 'BT /FApp 9 Tf 0 0 Td (Consent appearance stream text) Tj ET';
    $offAppearance = 'q Q';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 10 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 14 0 R 18 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Btn /T (shipping.speed) /Ff 49152 /V /Fast /DV /Ground /Opt [(Ground delivery export) (Express delivery export)] /Kids [8 0 R 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 96 664] /P 3 0 R /F 4 /AS /Off /AP << /N << /Ground 30 0 R /Off 33 0 R >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [108 640 132 664] /P 3 0 R /F 4 /AS /Fast /AP << /N << /Fast 31 0 R /Off 33 0 R >> >> >>\nendobj\n"
        . "14 0 obj\n<< /FT /Btn /T (newsletter.consent) /DV /Yes /Opt [(Newsletter consent export)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 600 96 624] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 32 0 R /Off 33 0 R >> >> >>\nendobj\n"
        . "18 0 obj\n<< /FT /Btn /T (actions.export) /Ff 65536 /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 560 210 584] /P 3 0 R /F 4 /A << /S /SubmitForm /F (https://example.test/form-export) /Fields [6 0 R 14 0 R] /Flags 4 >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($groundAppearance) . " >>\nstream\n{$groundAppearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($fastAppearance) . " >>\nstream\n{$fastAppearance}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /FApp 40 0 R >> >> /Length " . strlen($yesAppearance) . " >>\nstream\n{$yesAppearance}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'maps AcroForm button /Opt export values to current widget appearance states at current base' => static function (TestRunner $t) use ($widgetAppearanceExportPdf, $fieldsByName, $rowsByFieldName): void {
        $pdf = $widgetAppearanceExportPdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $shipping = $fields['shipping.speed'];
        $groundWidget = $shipping['widgets'][0];
        $fastWidget = $shipping['widgets'][1];
        $shippingState = $shipping['value_state'];
        $shippingReview = $shipping['button_export_review'];
        $consent = $fields['newsletter.consent'];
        $consentState = $consent['value_state'];
        $consentReview = $consent['button_export_review'];
        $submit = $fields['actions.export']['widgets'][0]['actions'][0];
        $submitRows = $rowsByFieldName($submit['field_value_review']['field_rows']);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Fast', $shipping['value']);
        $t->same('Fast', $shippingState['effective_current_state']);
        $t->same('field_value', $shippingState['state_source']);
        $t->same('Express delivery export', $shippingState['effective_export_value']);
        $t->same('button_opt', $shippingState['export_value_source']);
        $t->same(['Ground delivery export', 'Express delivery export'], $shippingState['export_values']);
        $t->same(['Express delivery export'], $shippingState['selected_export_values']);
        $t->same(['Ground', 'Fast'], $shippingState['on_values']);
        $t->same(true, $shippingState['widget_state_consistent']);
        $t->same(1, $shippingState['checked_widget_count']);

        $t->same('Ground delivery export', $groundWidget['export_value']);
        $t->same('button_opt', $groundWidget['export_value_source']);
        $t->same(0, $groundWidget['export_option_index']);
        $t->same('Ground delivery export', $groundWidget['export_option_label']);
        $t->same(false, $groundWidget['selected_by_field_value']);
        $t->same(true, $groundWidget['state_matches_field_value']);
        $t->same('Express delivery export', $fastWidget['export_value']);
        $t->same('button_opt', $fastWidget['export_value_source']);
        $t->same(1, $fastWidget['export_option_index']);
        $t->same(true, $fastWidget['selected_by_field_value']);
        $t->same(true, $fastWidget['state_matches_field_value']);
        $t->same('Fast', $fastWidget['current_base_state']['appearance_state']);
        $t->same('Express delivery export', $fastWidget['current_base_state']['export_value']);
        $t->same('button_opt', $fastWidget['current_base_state']['export_value_source']);
        $t->same(true, $fastWidget['current_base_state']['field_value_matches_appearance_state']);
        $t->same(false, $fastWidget['current_base_state']['field_value_matches_export_value']);
        $t->same(false, $fastWidget['current_base_state']['appearance_value_used_for_import']);

        $t->same('acroform_widget_appearance_export_currentbase', $shippingReview['source']);
        $t->same(6, $shippingReview['field_object']);
        $t->same('shipping.speed', $shippingReview['field_name']);
        $t->same('radio', $shippingReview['button_kind']);
        $t->same('Fast', $shippingReview['effective_current_state']);
        $t->same('Express delivery export', $shippingReview['effective_export_value']);
        $t->same('button_opt', $shippingReview['export_value_source']);
        $t->same(['Ground delivery export', 'Express delivery export'], $shippingReview['option_export_values']);
        $t->same(['Ground delivery export', 'Express delivery export'], $shippingReview['widget_export_values']);
        $t->same(['Express delivery export'], $shippingReview['selected_export_values']);
        $t->same(['Ground', 'Fast'], $shippingReview['appearance_on_values']);
        $t->same(true, $shippingReview['field_value_authoritative']);
        $t->same(false, $shippingReview['appearance_value_used_for_import']);
        $t->same(false, $shippingReview['export_value_used_for_import']);
        $t->same(false, $shippingReview['executes_appearance_streams']);
        $t->same(false, $shippingReview['renders_appearances']);

        $t->same(null, $consent['value']);
        $t->same('Newsletter consent export', $consentState['effective_current_state']);
        $t->same('widget_appearance_state', $consentState['state_source']);
        $t->same('Newsletter consent export', $consentState['effective_export_value']);
        $t->same('button_opt', $consentState['export_value_source']);
        $t->same(['Newsletter consent export'], $consentState['selected_export_values']);
        $t->same('Newsletter consent export', $consentReview['effective_export_value']);
        $t->same(false, $consentReview['field_value_authoritative']);
        $t->same(1, $consentReview['checked_widget_count']);

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('Express delivery export', $submitRows['shipping.speed']['submit_value']);
        $t->same('button_opt', $submitRows['shipping.speed']['submit_value_source']);
        $t->same('Fast', $submitRows['shipping.speed']['current']);
        $t->same('Express delivery export', $submitRows['shipping.speed']['effective_export_value']);
        $t->same('Newsletter consent export', $submitRows['newsletter.consent']['submit_value']);
        $t->same('button_opt', $submitRows['newsletter.consent']['submit_value_source']);
        $t->same('Newsletter consent export', $submitRows['newsletter.consent']['effective_export_value']);
        $t->same(false, $submit['field_value_review']['payload_text_exposed']);
        $t->same(false, $submit['executes_action']);

        $t->true(str_contains($text, 'Visible button export review body'));
        $t->true(str_contains($text, 'Fast appearance stream text'));
        $t->true(str_contains($text, 'Consent appearance stream text'));
        $t->same(false, str_contains($text, 'Express delivery export'));
        $t->same(false, str_contains($text, 'Newsletter consent export'));
        $t->same(false, str_contains($text, 'form-export'));
    },
];
