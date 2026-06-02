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

$actionsByTrigger = static function (array $actions): array {
    $indexed = [];
    foreach ($actions as $action) {
        $indexed[$action['trigger']] = $action;
    }

    return $indexed;
};

$fieldActionSubmitResetResourcePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible field action submit reset resource body) Tj ET';
    $submitPayload = 'Blocked current-base field action submit payload';
    $richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p>Field action rich text should stay metadata only</p></body>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Body 10 Tf 0 0 0 rg) /DR 50 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.field_action_title) /V (Current field action title) /DV (Default field action title) /RV ({$richText}) /DA (/Body 10 Tf 0.1 0.2 0.3 rg) /AA << /V 30 0 R /F 31 0 R >> /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Widget 9 Tf 0.4 0.5 0.6 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (internal.field_action_secret) /Ff 4 /V (Secret field action value) /DA (/Private 9 Tf 0.7 g) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /S /SubmitForm /F 40 0 R /Fields [6 0 R 10 0 R] /Flags 32 >>\nendobj\n"
        . "31 0 obj\n<< /S /ResetForm /Fields [6 0 R 10 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/fallback-field-action.fdf) /UF (https://example.test/current-field-action.xfdf) /Desc (Field action submit endpoint) /AFRelationship /FormData /EF << /F 41 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fvnd.adobe.xfdf /Params << /Size " . strlen($submitPayload) . " /CheckSum (field-submit-checksum) >> /Length " . strlen($submitPayload) . " >>\nstream\n{$submitPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Font << /Body 51 0 R /Widget 52 0 R /Private 53 0 R >> >>\nendobj\n"
        . "51 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ReviewBody /Encoding /WinAnsiEncoding /FontDescriptor 54 0 R >>\nendobj\n"
        . "52 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /WidgetFace /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "53 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PrivateSans /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "54 0 obj\n<< /Type /FontDescriptor /FontName /ReviewBody /Flags 32 /FontWeight 500 >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $submitPayload, $richText];
};

return [
    'keeps field-level submit reset action resources review only at current base' => static function (TestRunner $t) use ($fieldActionSubmitResetResourcePdf, $fieldsByName, $actionsByTrigger): void {
        [$pdf, $submitPayload, $richText] = $fieldActionSubmitResetResourcePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $title = $fields['article.field_action_title'];
        $actions = $actionsByTrigger($title['actions']);
        $submit = $actions['V'];
        $reset = $actions['F'];

        $t->same('Current field action title', $title['value']);
        $t->same('Default field action title', $title['default_value']);
        $t->same(hash('sha256', $richText), $title['rich_text_review']['rich_text_sha256']);
        $t->same(false, $title['rich_text_review']['rich_text_used_for_import']);

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('field', $submit['source']);
        $t->same(6, $submit['source_object']);
        $t->same(30, $submit['action_object']);
        $t->same('V', $submit['trigger']);
        $t->same('validate', $submit['trigger_label']);
        $t->same('include', $submit['fields_mode']);
        $t->same([6, 10], $submit['field_objects']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $submit['field_names']);
        $t->same('xfdf', $submit['submit_format']);
        $t->same(['xfdf_format'], $submit['flag_names']);
        $t->same('https://example.test/current-field-action.xfdf', $submit['target']);
        $t->same('https', $submit['target_scheme']);
        $t->same(false, $submit['executes_action']);
        $t->same(false, $submit['submits_pdf_on_import']);

        $submitSpec = $submit['file_spec'];
        $t->same('acroform_action_filespec_review_boundary', $submitSpec['source']);
        $t->same(40, $submitSpec['file_spec_object']);
        $t->same('URL', $submitSpec['file_system']);
        $t->same('https://example.test/current-field-action.xfdf', $submitSpec['filename']);
        $t->same('FormData', $submitSpec['relationship']);
        $t->same(1, $submitSpec['embedded_file_count']);
        $t->same([41], $submitSpec['embedded_file_objects']);
        $t->same(hash('sha256', $submitPayload), $submitSpec['embedded_files'][0]['decoded_sha256']);
        $t->same(false, $submitSpec['embedded_payload_text_exposed']);

        $submitValue = $submit['field_value_review'];
        $t->same(2, $submitValue['candidate_field_count']);
        $t->same(1, $submitValue['included_field_count']);
        $t->same(['article.field_action_title'], $submitValue['submitted_field_names']);
        $t->same(['internal.field_action_secret'], $submitValue['no_export_excluded_field_names']);
        $t->same(['article.field_action_title'], $submitValue['rich_text_field_names']);
        $t->same(false, $submitValue['exports_rich_text_html']);
        $t->same(false, $submitValue['payload_text_exposed']);

        $submitReview = $submit['action_resource_review'];
        $t->same('acroform_field_action_submit_reset_resource_currentbase_review_boundary', $submitReview['source']);
        $t->same('SubmitForm', $submitReview['action_type']);
        $t->same('field', $submitReview['action_source']);
        $t->same(6, $submitReview['source_object']);
        $t->same(30, $submitReview['action_object']);
        $t->same('V', $submitReview['trigger']);
        $t->same('validate', $submitReview['trigger_label']);
        $t->same('include', $submitReview['fields_mode']);
        $t->same(2, $submitReview['selected_field_count']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $submitReview['selected_field_names']);
        $t->same(2, $submitReview['field_resource_count']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $submitReview['field_resource_names']);
        $t->same(['article.field_action_title'], $submitReview['included_field_names']);
        $t->same(['article.field_action_title'], $submitReview['submitted_field_names']);
        $t->same(['internal.field_action_secret'], $submitReview['no_export_excluded_field_names']);
        $t->same(['Body', 'Private'], $submitReview['field_font_resources']);
        $t->same(['ReviewBody', 'PrivateSans'], $submitReview['field_font_resource_base_fonts']);
        $t->same(['Widget', 'Private'], $submitReview['widget_font_resources']);
        $t->same(['WidgetFace', 'PrivateSans'], $submitReview['widget_font_resource_base_fonts']);
        $t->same('https://example.test/current-field-action.xfdf', $submitReview['target']);
        $t->same('https', $submitReview['target_scheme']);
        $t->same(true, $submitReview['has_target_file_spec']);
        $t->same(40, $submitReview['target_file_spec_object']);
        $t->same('FormData', $submitReview['target_file_spec_relationship']);
        $t->same([41], $submitReview['target_embedded_file_objects']);
        $t->same(false, $submitReview['file_spec_payload_text_exposed']);
        $t->same(false, $submitReview['field_value_payload_exposed']);
        $t->same(false, $submitReview['submits_pdf_on_import']);
        $t->same(false, $submitReview['resets_form_values_on_import']);
        $t->same(false, $submitReview['renders_appearances']);
        $t->same(false, $submitReview['executes_appearance_streams']);
        $t->same(false, $submitReview['executes_action']);
        $t->same(false, $submitReview['executes_javascript']);

        $resetValue = $reset['field_value_review'];
        $t->same('ResetForm', $reset['action_type']);
        $t->same('field', $reset['source']);
        $t->same(6, $reset['source_object']);
        $t->same(31, $reset['action_object']);
        $t->same('F', $reset['trigger']);
        $t->same('format', $reset['trigger_label']);
        $t->same(2, $resetValue['reset_field_count']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $resetValue['reset_field_names']);
        $t->same(['article.field_action_title'], $resetValue['default_value_field_names']);
        $t->same(['internal.field_action_secret'], $resetValue['cleared_field_names']);
        $t->same(false, $resetValue['restores_rich_text_html']);
        $t->same(false, $resetValue['payload_text_exposed']);

        $resetReview = $reset['action_resource_review'];
        $t->same('acroform_field_action_submit_reset_resource_currentbase_review_boundary', $resetReview['source']);
        $t->same('ResetForm', $resetReview['action_type']);
        $t->same(2, $resetReview['selected_field_count']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $resetReview['selected_field_names']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $resetReview['included_field_names']);
        $t->same(['article.field_action_title', 'internal.field_action_secret'], $resetReview['reset_field_names']);
        $t->same(['article.field_action_title'], $resetReview['default_value_field_names']);
        $t->same(['internal.field_action_secret'], $resetReview['cleared_field_names']);
        $t->same(['Body', 'Private'], $resetReview['field_font_resources']);
        $t->same(false, $resetReview['has_target_file_spec']);
        $t->same(null, $resetReview['target']);
        $t->same(false, $resetReview['resets_form_values_on_import']);
        $t->same(false, $resetReview['executes_action']);

        $t->same('Visible field action submit reset resource body', $visibleText);
        foreach ([
            'Field action rich text',
            'Secret field action value',
            'ReviewBody',
            'PrivateSans',
            'current-field-action.xfdf',
            $submitPayload,
        ] as $blockedText) {
            $t->same(false, str_contains($visibleText, $blockedText));
        }
    },
];
