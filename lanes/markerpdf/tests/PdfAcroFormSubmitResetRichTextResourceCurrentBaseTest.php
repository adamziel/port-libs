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

$submitResetRichTextResourcePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible submit reset resource body) Tj ET';
    $richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>Styled review value</b> stays metadata</p></body>';
    $defaultStyle = 'font: 11pt ReviewSerif; color:#003366';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R 20 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /DR 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.rich_resource) /Ff 33554432 /V (Plain resource value) /DV (Draft resource value) /RV ({$richText}) /DS ({$defaultStyle}) /DA (/Body 11 Tf 0.1 0.2 0.3 rg) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /DA (/Widget 10 Tf 0.4 0.5 0.6 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (internal.resource_secret) /Ff 4 /V (Private resource payload) /DA (/Private 9 Tf 0.7 g) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 360 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Btn /T (actions.submit_pdf) /Ff 65536 /Kids [16 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 180 584] /P 3 0 R /F 4 /A << /S /SubmitForm /F 40 0 R /Fields [6 0 R 10 0 R] /Flags 11138 >> >>\nendobj\n"
        . "18 0 obj\n<< /FT /Btn /T (actions.reset_resource) /Ff 65536 /Kids [20 0 R] /AA << /U << /S /ResetForm /Fields [6 0 R 10 0 R] >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [200 560 310 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Font << /Helv 31 0 R /Body 32 0 R /Widget 34 0 R /Private 35 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "32 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ReviewSerif /Encoding /WinAnsiEncoding /FontDescriptor 33 0 R >>\nendobj\n"
        . "33 0 obj\n<< /Type /FontDescriptor /FontName /ReviewSerif /Flags 32 /FontWeight 600 >>\nendobj\n"
        . "34 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ReviewWidget /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "35 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PrivateSans /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (https://example.test/pdf-submit) >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $richText, $defaultStyle];
};

return [
    'keeps submit reset rich text resources review only at current base' => static function (TestRunner $t) use ($submitResetRichTextResourcePdf, $fieldsByName): void {
        [$pdf, $richText, $defaultStyle] = $submitResetRichTextResourcePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $rich = $fields['article.rich_resource'];
        $richReview = $rich['rich_text_review'];
        $submit = $fields['actions.submit_pdf']['widgets'][0]['actions'][0];
        $reset = $fields['actions.reset_resource']['actions'][0];
        $submitRows = [];
        foreach ($submit['field_value_review']['field_rows'] as $row) {
            $submitRows[$row['field_name']] = $row;
        }
        $resetRows = [];
        foreach ($reset['field_value_review']['field_rows'] as $row) {
            $resetRows[$row['field_name']] = $row;
        }

        $t->same('Plain resource value', $rich['value']);
        $t->same('Draft resource value', $rich['default_value']);
        $t->same(hash('sha256', $richText), $richReview['rich_text_sha256']);
        $t->same(hash('sha256', $defaultStyle), $richReview['default_style_sha256']);
        $t->same(false, $richReview['rich_text_used_for_import']);
        $t->same(false, $richReview['default_style_exposed_as_css']);

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('pdf', $submit['submit_format']);
        $t->same('pdf', $submit['requested_submit_format']);
        $t->same([
            'include_no_value_fields',
            'include_annotations',
            'submit_pdf',
            'canonical_format',
            'exclude_f_key',
            'embed_form',
        ], $submit['flag_names']);
        $t->true($submit['submit_pdf_requested']);
        $t->true($submit['include_annotations_requested']);
        $t->true($submit['canonical_format_requested']);
        $t->true($submit['exclude_f_key_requested']);
        $t->true($submit['embed_form_requested']);
        $t->same(false, $submit['submits_pdf_on_import']);
        $t->same(false, $submit['embeds_form_on_import']);
        $t->same(false, $submit['includes_annotations_on_import']);

        $submitReview = $submit['field_value_review'];
        $t->same('pdf', $submitReview['requested_submit_format']);
        $t->same(2, $submitReview['candidate_field_count']);
        $t->same(1, $submitReview['included_field_count']);
        $t->same(['article.rich_resource'], $submitReview['submitted_field_names']);
        $t->same(['internal.resource_secret'], $submitReview['no_export_excluded_field_names']);
        $t->same(['article.rich_resource'], $submitReview['rich_text_field_names']);
        $t->same(false, $submitReview['submits_pdf_on_import']);
        $t->same(false, $submitReview['embeds_form_on_import']);
        $t->same(false, $submitReview['includes_annotations_on_import']);
        $t->same(false, $submitReview['exports_rich_text_html']);

        $submitResource = $submitRows['article.rich_resource']['appearance_resource_review'];
        $t->same('acroform_submit_reset_resource_review_boundary', $submitResource['source']);
        $t->same('field', $submitResource['field_appearance_source']);
        $t->same(6, $submitResource['field_appearance_source_object']);
        $t->same('Body', $submitResource['font_resource']);
        $t->same(true, $submitResource['font_resource_resolved']);
        $t->same(32, $submitResource['font_resource_object']);
        $t->same('ReviewSerif', $submitResource['font_resource_base_font']);
        $t->same('WinAnsiEncoding', $submitResource['font_resource_encoding']);
        $t->same(33, $submitResource['font_descriptor_object']);
        $t->same('ReviewSerif', $submitResource['font_descriptor_name']);
        $t->same(600, $submitResource['font_weight']);
        $t->same('acroform', $submitResource['default_resource_source']);
        $t->same(30, $submitResource['default_resource_source_object']);
        $t->same(1, $submitResource['widget_appearance_count']);
        $t->same('Widget', $submitResource['widget_appearances'][0]['font_resource']);
        $t->same('ReviewWidget', $submitResource['widget_appearances'][0]['font_resource_base_font']);
        $t->same(false, $submitResource['uses_default_resources_for_submit']);
        $t->same(false, $submitResource['renders_appearances']);
        $t->same(false, $submitResource['executes_appearance_streams']);
        $t->same('Plain resource value', $submitRows['article.rich_resource']['submit_value']);
        $t->same(false, $submitRows['article.rich_resource']['rich_text_included']);
        $t->same(false, $submitRows['internal.resource_secret']['submit_included']);
        $t->same('no_export', $submitRows['internal.resource_secret']['omit_reason']);

        $resetReview = $reset['field_value_review'];
        $t->same('ResetForm', $reset['action_type']);
        $t->same(2, $resetReview['reset_field_count']);
        $t->same(['article.rich_resource', 'internal.resource_secret'], $resetReview['reset_field_names']);
        $t->same(['article.rich_resource'], $resetReview['default_value_field_names']);
        $t->same(['internal.resource_secret'], $resetReview['cleared_field_names']);
        $t->same(false, $resetReview['restores_rich_text_html']);
        $t->same(false, $resetReview['resets_resources_on_import']);
        $t->same(false, $resetReview['renders_default_resources_on_import']);
        $t->same('Draft resource value', $resetRows['article.rich_resource']['reset_value']);
        $t->same(false, $resetRows['article.rich_resource']['rich_text_restored']);
        $t->same('ReviewSerif', $resetRows['article.rich_resource']['appearance_resource_review']['font_resource_base_font']);
        $t->same(false, $resetRows['article.rich_resource']['appearance_resource_review']['uses_default_resources_for_reset']);

        $t->same('Visible submit reset resource body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Styled review value'));
        $t->same(false, str_contains($visibleText, 'ReviewSerif'));
        $t->same(false, str_contains($visibleText, 'Private resource payload'));
        $t->same(false, str_contains($visibleText, 'pdf-submit'));
    },
];
