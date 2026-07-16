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

$widgetAppearanceStatePdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible appearance state review body) Tj ET';
    $approvedAppearance = 'BT /FApp 9 Tf 0 0 Td (Approved widget appearance review) Tj ET';
    $yesAppearance = 'BT /FApp 9 Tf 0 0 Td (Yes widget appearance review) Tj ET';
    $onlineAppearance = 'BT /FApp 9 Tf 0 0 Td (Online radio appearance review) Tj ET';
    $pickupAppearance = 'BT /FApp 9 Tf 0 0 Td (Pickup radio appearance review) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
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
        . "34 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length " . strlen($pickupAppearance) . " >>\nstream\n{$pickupAppearance}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps stale AcroForm widget appearance states from becoming current import state' => static function (TestRunner $t) use ($widgetAppearanceStatePdf, $fieldsByName): void {
        $pdf = $widgetAppearanceStatePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $approval = $fields['article.approval'];
        $approvalWidget = $approval['widgets'][0];
        $approvalState = $approvalWidget['current_base_state'];
        $approvalReview = $approval['widget_current_base_review'];
        $optin = $fields['newsletter.optin'];
        $optinWidget = $optin['widgets'][0];
        $optinState = $optinWidget['current_base_state'];
        $delivery = $fields['delivery.method'];
        $onlineWidget = $delivery['widgets'][0];
        $pickupWidget = $delivery['widgets'][1];
        $onlineState = $onlineWidget['current_base_state'];
        $pickupState = $pickupWidget['current_base_state'];
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Approved', $approval['value']);
        $t->same('Approved', $approval['value_state']['effective_current_state']);
        $t->same('field_value', $approval['value_state']['state_source']);
        $t->same(false, $approval['value_state']['changed_from_default']);
        $t->same(false, $approval['value_state']['widget_state_consistent']);
        $t->same(0, $approval['value_state']['checked_widget_count']);
        $t->same(1, $approval['value_state']['stale_widget_appearance_state_count']);

        $t->same('acroform_widget_appearance_state_currentbase', $approvalState['source']);
        $t->same(8, $approvalState['widget_object']);
        $t->same('checkbox', $approvalState['button_kind']);
        $t->same('Draft', $approvalState['appearance_state']);
        $t->same(false, $approvalState['appearance_state_valid']);
        $t->same(['Approved', 'Off'], $approvalState['appearance_states']);
        $t->same(['Approved'], $approvalState['on_states']);
        $t->same('Approved', $approvalState['export_value']);
        $t->same(false, $approvalState['checked_by_widget_appearance']);
        $t->same(true, $approvalState['selected_by_field_value']);
        $t->same(false, $approvalState['state_matches_field_value']);
        $t->same(true, $approvalState['stale_appearance_state']);
        $t->same('state_dictionary', $approvalState['normal_appearance_type']);
        $t->same(null, $approvalState['selected_appearance_state']);
        $t->same(null, $approvalState['selected_appearance_object']);
        $t->same('Approved', $approvalState['current']);
        $t->same('field_value', $approvalState['current_source']);
        $t->same(true, $approvalState['field_value_authoritative']);
        $t->same(false, $approvalState['appearance_value_used_for_import']);
        $t->same(false, $approvalState['executes_appearance_streams']);

        $t->same('acroform_widget_appearance_state_currentbase', $approvalReview['source']);
        $t->same(6, $approvalReview['field_object']);
        $t->same('article.approval', $approvalReview['field_name']);
        $t->same('Approved', $approvalReview['current']);
        $t->same('field_value', $approvalReview['current_source']);
        $t->same('Approved', $approvalReview['default']);
        $t->same(1, $approvalReview['widget_count']);
        $t->same(0, $approvalReview['checked_widget_count']);
        $t->same(false, $approvalReview['state_consistent']);
        $t->same(1, $approvalReview['stale_appearance_state_count']);
        $t->same([8], $approvalReview['stale_appearance_widgets']);
        $t->same(false, $approvalReview['appearance_value_used_for_import']);

        $t->same(null, $optin['value']);
        $t->same(null, $optin['value_state']['effective_current_state']);
        $t->same('missing_or_off', $optin['value_state']['state_source']);
        $t->same(true, $optin['value_state']['changed_from_default']);
        $t->same(0, $optin['value_state']['checked_widget_count']);
        $t->same(false, $optin['value_state']['widget_state_consistent']);
        $t->same('Maybe', $optinState['appearance_state']);
        $t->same(false, $optinState['appearance_state_valid']);
        $t->same(false, $optinState['checked_by_widget_appearance']);
        $t->same(false, $optinState['selected_by_field_value']);
        $t->same(false, $optinState['state_matches_field_value']);
        $t->same(true, $optinState['stale_appearance_state']);
        $t->same(null, $optinState['current']);
        $t->same('missing_or_off', $optinState['current_source']);
        $t->same(false, $optinState['field_value_authoritative']);

        $t->same('Online', $delivery['value_state']['effective_current_state']);
        $t->same('field_value', $delivery['value_state']['state_source']);
        $t->same(true, $delivery['value_state']['changed_from_default']);
        $t->same(true, $delivery['value_state']['widget_state_consistent']);
        $t->same(1, $delivery['value_state']['checked_widget_count']);
        $t->same(0, $delivery['value_state']['stale_widget_appearance_state_count']);
        $t->same('radio', $onlineState['button_kind']);
        $t->same('Online', $onlineState['appearance_state']);
        $t->same(true, $onlineState['appearance_state_valid']);
        $t->same(true, $onlineState['checked_by_widget_appearance']);
        $t->same(true, $onlineState['selected_by_field_value']);
        $t->same(true, $onlineState['state_matches_field_value']);
        $t->same(false, $onlineState['stale_appearance_state']);
        $t->same(33, $onlineState['selected_appearance_object']);
        $t->same('Off', $pickupState['appearance_state']);
        $t->same(true, $pickupState['appearance_state_valid']);
        $t->same(false, $pickupState['checked_by_widget_appearance']);
        $t->same(false, $pickupState['selected_by_field_value']);
        $t->same(true, $pickupState['state_matches_field_value']);
        $t->same(false, $pickupState['stale_appearance_state']);
        $t->same(31, $pickupState['selected_appearance_object']);

        $t->true(str_contains($text, 'Visible appearance state review body'));
        $t->true(!str_contains($text, 'Draft'));
        $t->true(!str_contains($text, 'Maybe'));
    },
];
