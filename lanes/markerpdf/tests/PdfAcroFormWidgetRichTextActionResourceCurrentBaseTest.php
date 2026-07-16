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

$widgetRichTextActionResourcePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget rich text review body) Tj ET';
    $richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p style="font-weight:bold">Widget <i>rich</i> text stays metadata</p></body>';
    $defaultStyle = 'font: 10pt "ReviewSans"; color:#003366; text-align:left';
    $widgetValidateScript = "event.rc = false; app.alert('validation blocked');";
    $compressedScript = gzcompress($widgetValidateScript);
    if (!is_string($compressedScript)) {
        throw new RuntimeException('Unable to compress widget rich text action fixture.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.rich_widget) /Ff 33554432 /V (Plain widget value) /DV (Draft widget value) /RV ({$richText}) /DS ({$defaultStyle}) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Review 10 Tf 0.2 0.3 0.4 rg) /AA << /V 20 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /S /JavaScript /JS 21 0 R >>\nendobj\n"
        . "21 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Font << /Helv 31 0 R /Review 32 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ReviewSans /Encoding /WinAnsiEncoding /FontDescriptor 33 0 R >>\nendobj\n"
        . "33 0 obj\n<< /Type /FontDescriptor /FontName /ReviewSans /Flags 32 /FontWeight 700 >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $richText, $defaultStyle, $widgetValidateScript];
};

return [
    'reviews widget rich text default style action and resources without importing active payloads' => static function (TestRunner $t) use ($widgetRichTextActionResourcePdf, $fieldsByName): void {
        [$pdf, $richText, $defaultStyle, $widgetValidateScript] = $widgetRichTextActionResourcePdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $field = $fields['article.rich_widget'];
        $widget = $field['widgets'][0];
        $fieldAppearance = $field['default_appearance'];
        $widgetAppearance = $widget['default_appearance'];
        $richReview = $field['rich_text_review'];
        $widgetAction = $widget['actions'][0];
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Plain widget value', $field['value']);
        $t->same('Draft widget value', $field['default_value']);
        $t->same(['rich_text'], $field['flag_names']);
        $t->same('Plain widget value', $field['value_state']['current']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(6, $field['field_hierarchy']['attribute_sources']['DS']['source_object']);
        $t->same(false, $field['field_hierarchy']['attribute_sources']['DS']['inherited']);
        $t->same(['V', 'DV', 'RV', 'DS'], $field['field_hierarchy']['local_value_attributes']);

        $t->same('acroform_rich_text_value_review_boundary', $richReview['source']);
        $t->true($richReview['rich_text_flag']);
        $t->true($richReview['has_rich_text_value']);
        $t->same(hash('sha256', $richText), $richReview['rich_text_sha256']);
        $t->same('Widget rich text stays metadata', $richReview['rich_text_plain_preview']);
        $t->true($richReview['has_default_style']);
        $t->same('field', $richReview['default_style_source']);
        $t->same(6, $richReview['default_style_source_object']);
        $t->same($defaultStyle, $richReview['default_style_preview']);
        $t->same(strlen($defaultStyle), $richReview['default_style_bytes']);
        $t->same(hash('sha256', $defaultStyle), $richReview['default_style_sha256']);
        $t->same(false, $richReview['default_style_used_for_import']);
        $t->same(false, $richReview['default_style_used_for_submit']);
        $t->same(false, $richReview['default_style_exposed_as_css']);
        $t->same(false, $richReview['rich_text_used_for_import']);
        $t->same(false, $richReview['payload_text_exposed']);

        $t->same('Helv', $fieldAppearance['font_resource']);
        $t->same('Helvetica', $fieldAppearance['font_resource_base_font']);
        $t->same('acroform', $fieldAppearance['source']);
        $t->same(null, $fieldAppearance['source_object']);
        $t->same('acroform', $fieldAppearance['default_resource_source']);
        $t->same(30, $fieldAppearance['default_resource_source_object']);

        $t->same('Review', $widgetAppearance['font_resource']);
        $t->same(10.0, $widgetAppearance['font_size']);
        $t->same('ReviewSans', $widgetAppearance['font_resource_base_font']);
        $t->same('WinAnsiEncoding', $widgetAppearance['font_resource_encoding']);
        $t->same(33, $widgetAppearance['font_descriptor_object']);
        $t->same('ReviewSans', $widgetAppearance['font_descriptor_name']);
        $t->same(32, $widgetAppearance['font_descriptor_flags']);
        $t->same(700, $widgetAppearance['font_weight']);
        $t->same('widget', $widgetAppearance['source']);
        $t->same(8, $widgetAppearance['source_object']);
        $t->same('acroform', $widgetAppearance['default_resource_source']);
        $t->same(30, $widgetAppearance['default_resource_source_object']);

        $t->same('JavaScript', $widgetAction['action_type']);
        $t->same('V', $widgetAction['trigger']);
        $t->same('validate', $widgetAction['trigger_label']);
        $t->same('widget', $widgetAction['source']);
        $t->same(8, $widgetAction['source_object']);
        $t->same(20, $widgetAction['action_object']);
        $t->same(21, $widgetAction['script_object']);
        $t->same($widgetValidateScript, $widgetAction['script_preview']);
        $t->same(hash('sha256', $widgetValidateScript), $widgetAction['script_sha256']);
        $t->same(['FlateDecode'], $widgetAction['script_filters']);
        $t->same(false, $widgetAction['executes_javascript']);
        $t->same(false, $widgetAction['executes_action']);
        $t->same(1, $widget['action_review']['action_count']);
        $t->same(false, $widget['action_review']['executes_javascript']);

        $t->same('Visible widget rich text review body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Widget rich text stays metadata'));
        $t->same(false, str_contains($visibleText, 'validation blocked'));
        $t->same(false, str_contains($visibleText, 'ReviewSans'));
    },
];
