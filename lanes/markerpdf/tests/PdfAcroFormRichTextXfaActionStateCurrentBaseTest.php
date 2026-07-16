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

$richTextXfaActionStatePdf = static function (): array {
    $xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="article">
      <xfa:field name="article.summary"><xfa:caption><xfa:value><xfa:text>Article summary</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><summary>XFA rich summary must stay metadata</summary></article>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible rich text XFA action state body) Tj ET';
    $richText = '<body xmlns="http://www.w3.org/1999/xhtml"><p><b>XFA styled summary must not import</b></p></body>';
    $defaultStyle = 'font: 11pt "ReviewSerif"; color:#102030';
    $formatScript = "event.value = event.value; app.alert('format blocked');";
    $appearanceText = 'BT /F1 10 Tf 0 0 Td (Widget appearance review only) Tj ET';

    $compressedXfa = gzcompress($xdpXml);
    $compressedScript = gzcompress($formatScript);
    $compressedAppearance = gzcompress($appearanceText);
    if (!is_string($compressedXfa) || !is_string($compressedScript) || !is_string($compressedAppearance)) {
        throw new RuntimeException('Unable to compress rich text XFA action state fixture streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /XFA 40 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.summary) /Ff 33554432 /V (Plain AcroForm summary) /DV (Draft AcroForm summary) /RV ({$richText}) /DS ({$defaultStyle}) /Kids [8 0 R] /AA << /F 20 0 R /V 22 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /AS /Current /AP << /N << /Current 24 0 R /Off 25 0 R >> >> /A 23 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /JavaScript /JS 21 0 R >>\nendobj\n"
        . "21 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
        . "22 0 obj\n<< /S /SubmitForm /F 30 0 R /Fields [(article.summary)] /Flags 4 >>\nendobj\n"
        . "23 0 obj\n<< /S /ResetForm /Fields [(article.summary)] >>\nendobj\n"
        . "24 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 50 0 R >> >> /Length " . strlen($compressedAppearance) . " /Filter /FlateDecode >>\nstream\n{$compressedAppearance}\nendstream\nendobj\n"
        . "25 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 50 0 R >> >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (https://example.test/rich-submit) >>\nendobj\n"
        . "40 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $xdpXml, $richText, $defaultStyle, $formatScript, $appearanceText];
};

return [
    'summarizes rich text XFA and AcroForm action state at current base without importing active payloads' => static function (TestRunner $t) use ($richTextXfaActionStatePdf, $fieldsByName): void {
        [$pdf, $xdpXml, $richText, $defaultStyle, $formatScript, $appearanceText] = $richTextXfaActionStatePdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $field = $fields['article.summary'];
        $review = $field['rich_text_xfa_action_state_review'];
        $submit = $field['actions'][1];
        $reset = $field['widgets'][0]['actions'][0];
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($form['xfa_overrides_page_content']);
        $t->same(hash('sha256', trim($xdpXml)), $form['xfa_packets'][0]['xml_sha256']);
        $t->same('Plain AcroForm summary', $field['value']);
        $t->same('Draft AcroForm summary', $field['default_value']);

        $t->same('acroform_richtext_xfa_action_state_currentbase_review_boundary', $review['source']);
        $t->same('article.summary', $review['field_name']);
        $t->same(6, $review['field_object']);
        $t->true($review['has_current_value']);
        $t->true($review['has_default_value']);
        $t->same('Plain AcroForm summary', $review['current']);
        $t->same('Draft AcroForm summary', $review['default']);
        $t->same(true, $review['acroform_current_value_authoritative']);
        $t->same(true, $review['acroform_default_value_authoritative_for_reset']);

        $t->true($review['rich_text_flag']);
        $t->true($review['has_rich_text_value']);
        $t->same(hash('sha256', $richText), $review['rich_text_sha256']);
        $t->same('XFA styled summary must not import', $review['rich_text_plain_preview']);
        $t->same(hash('sha256', $defaultStyle), $review['default_style_sha256']);
        $t->same($defaultStyle, $review['default_style_preview']);
        $t->same(false, $review['rich_text_used_for_import']);
        $t->same(false, $review['rich_text_used_for_submit']);
        $t->same(false, $review['default_style_exposed_as_css']);

        $t->true($review['referenced_by_xfa']);
        $t->same(['article.summary'], $review['xfa_matched_field_names']);
        $t->same(['article.summary'], $review['xfa_matched_data_paths']);
        $t->same(['XFA rich summary must stay metadata'], $review['xfa_matched_data_value_previews']);
        $t->same([hash('sha256', 'XFA rich summary must stay metadata')], $review['xfa_matched_data_value_sha256']);
        $t->same(false, $review['xfa_value_used_for_current_value']);
        $t->same(false, $review['xfa_value_used_for_submit']);
        $t->same(false, $review['xfa_payload_text_exposed']);

        $t->same(3, $review['action_count']);
        $t->same(3, $review['unique_action_count']);
        $t->same(2, $review['field_action_count']);
        $t->same(1, $review['widget_action_count']);
        $t->same(['JavaScript', 'SubmitForm', 'ResetForm'], $review['action_types']);
        $t->same(['F', 'V', 'activation'], $review['action_triggers']);
        $t->same(['format', 'validate', 'activation'], $review['action_trigger_labels']);
        $t->same(['blocked-javascript', 'submit-form-action-review', 'reset-form-action-review'], $review['action_safety_labels']);
        $t->same([20, 22, 23], $review['action_objects']);
        $t->same(['https://example.test/rich-submit'], $review['submit_targets']);
        $t->same(['article.summary'], $review['action_field_names']);
        $t->same(['article.summary'], $review['submit_action_field_names']);
        $t->same(['article.summary'], $review['reset_action_field_names']);
        $t->same(1, $review['javascript_action_count']);
        $t->same(1, $review['submit_form_action_count']);
        $t->same(1, $review['reset_form_action_count']);
        $t->same(2, $review['field_value_review_action_count']);
        $t->same(false, $review['executes_action']);
        $t->same(false, $review['executes_javascript']);
        $t->same(false, $review['imports_xfa_payload']);

        $t->same(1, $review['widget_count']);
        $t->same(1, $review['page_referenced_widget_count']);
        $t->same([8], $review['widget_objects']);
        $t->same(['Current'], $review['appearance_states']);
        $t->same([24], $review['selected_appearance_objects']);
        $t->same(0, $review['stale_appearance_state_count']);
        $t->same(hash('sha256', $appearanceText), $field['widgets'][0]['normal_appearance']['selected_appearance']['decoded_sha256']);

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('Plain AcroForm summary', $submit['field_value_review']['field_rows'][0]['submit_value']);
        $t->same(false, $submit['field_value_review']['field_rows'][0]['rich_text_included']);
        $t->same('ResetForm', $reset['action_type']);
        $t->same('Draft AcroForm summary', $reset['field_value_review']['field_rows'][0]['reset_value']);
        $t->same(false, $reset['field_value_review']['field_rows'][0]['rich_text_restored']);

        $t->same($formatScript, $field['actions'][0]['script_preview']);
        $t->same(hash('sha256', $formatScript), $field['actions'][0]['script_sha256']);

        $t->contains('Visible rich text XFA action state body', $visibleText);
        $t->contains('Widget appearance review only', $visibleText);
        $t->same(false, str_contains($visibleText, 'XFA styled summary must not import'));
        $t->same(false, str_contains($visibleText, 'XFA rich summary must stay metadata'));
        $t->same(false, str_contains($visibleText, 'format blocked'));
        $t->same(false, str_contains($visibleText, 'rich-submit'));
    },
];
