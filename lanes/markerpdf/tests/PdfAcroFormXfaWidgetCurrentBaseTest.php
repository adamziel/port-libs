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

$xfaWidgetCurrentBasePdf = static function (): array {
    $xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="article">
      <xfa:field name="article.title"><xfa:caption><xfa:value><xfa:text>Article title</xfa:text></xfa:value></xfa:caption></xfa:field>
      <xfa:field name="consent.email"><xfa:caption><xfa:value><xfa:text>Email consent</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><title>XFA dynamic title must not import</title></article>
      <consent><email>XFA checked value stays metadata</email></consent>
    </xfa:data>
  </xfa:datasets>
</xdp:xdp>
XML;

    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible XFA widget current-base body) Tj ET';
    $titleAppearance = 'BT /F1 10 Tf 0 0 Td (Title widget appearance review only) Tj ET';
    $checkedAppearance = 'BT /F1 10 Tf 0 0 Td (Checked widget appearance review only) Tj ET';
    $offAppearance = 'BT /F1 10 Tf 0 0 Td (Off widget appearance review only) Tj ET';

    $compressedXfa = gzcompress($xdpXml);
    $compressedTitle = gzcompress($titleAppearance);
    $compressedChecked = gzcompress($checkedAppearance);
    $compressedOff = gzcompress($offAppearance);
    if (!is_string($compressedXfa) || !is_string($compressedTitle) || !is_string($compressedChecked) || !is_string($compressedOff)) {
        throw new RuntimeException('Unable to compress AcroForm XFA widget current-base fixture.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R] /NeedAppearances true /XFA 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Static AcroForm title) /DV (Draft AcroForm title) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 360 664] /P 3 0 R /F 4 /AS /Current /AP << /N 20 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /FT /Btn /T (consent.email) /V /Yes /DV /Off /Kids [11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 600 120 624] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 21 0 R /Off 22 0 R >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedTitle) . " /Filter /FlateDecode >>\nstream\n{$compressedTitle}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedChecked) . " /Filter /FlateDecode >>\nstream\n{$compressedChecked}\nendstream\nendobj\n"
        . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($compressedOff) . " /Filter /FlateDecode >>\nstream\n{$compressedOff}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($compressedXfa) . " /Filter /FlateDecode >>\nstream\n{$compressedXfa}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $xdpXml, $pageText, $titleAppearance, $checkedAppearance];
};

return [
    'keeps AcroForm widget current values authoritative while XFA datasets stay review-only' => static function (TestRunner $t) use ($xfaWidgetCurrentBasePdf, $fieldsByName): void {
        [$pdf, $xdpXml, $pageText, $titleAppearance, $checkedAppearance] = $xfaWidgetCurrentBasePdf();

        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $packet = $form['xfa_packets'][0];
        $title = $fields['article.title'];
        $titleWidget = $title['widgets'][0];
        $titleReview = $title['xfa_widget_review'];
        $checkbox = $fields['consent.email'];
        $checkboxWidget = $checkbox['widgets'][0];
        $checkboxReview = $checkbox['xfa_widget_review'];
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->true($form['xfa_overrides_page_content']);
        $t->same(hash('sha256', trim($xdpXml)), $packet['xml_sha256']);
        $t->same(['article.title', 'consent.email'], $packet['field_names']);
        $t->same(['article.title', 'consent.email'], $packet['data_paths']);
        $t->same('XFA dynamic title must not import', $packet['data_path_values'][0]['value_preview']);
        $t->same(hash('sha256', 'XFA checked value stays metadata'), $packet['data_path_values'][1]['value_sha256']);
        $t->same(false, $packet['data_path_values'][0]['value_used_for_import']);

        $t->same('Static AcroForm title', $title['value']);
        $t->same('Draft AcroForm title', $title['default_value']);
        $t->same('acroform_xfa_widget_currentbase_review_boundary', $titleReview['source']);
        $t->true($titleReview['referenced_by_xfa']);
        $t->same(['article.title'], $titleReview['matched_data_paths']);
        $t->same(['XFA dynamic title must not import'], $titleReview['matched_data_value_previews']);
        $t->same(['field', 'field'], [$titleReview['current_source'], $titleReview['default_source']]);
        $t->same('Static AcroForm title', $titleReview['current']);
        $t->same('Draft AcroForm title', $titleReview['default']);
        $t->same(true, $titleReview['acroform_current_value_authoritative']);
        $t->same(false, $titleReview['xfa_value_used_for_current_value']);
        $t->same(false, $titleReview['xfa_value_used_for_import']);
        $t->same(false, $titleReview['xfa_payload_text_exposed']);
        $t->same(1, $titleReview['widget_count']);
        $t->same(8, $titleReview['primary_widget_object']);
        $t->same('Current', $titleReview['primary_widget_appearance_state']);
        $t->same('direct_stream', $titleReview['primary_widget_normal_appearance_type']);
        $t->same(20, $titleReview['selected_appearance_object']);
        $t->same(hash('sha256', $titleAppearance), $titleReview['selected_appearance_decoded_sha256']);
        $t->same(false, $titleReview['appearance_value_used_for_import']);
        $t->same(false, $titleReview['executes_appearance_streams']);
        $t->same(false, $titleReview['renders_appearances']);

        $t->same('Yes', $checkbox['value']);
        $t->same('Off', $checkbox['default_value']);
        $t->same('Yes', $checkbox['value_state']['effective_current_state']);
        $t->same('field_value', $checkbox['value_state']['state_source']);
        $t->same(1, $checkbox['value_state']['checked_widget_count']);
        $t->true($checkbox['value_state']['widget_state_consistent']);
        $t->same('Yes', $checkboxWidget['appearance_state']);
        $t->same(['Yes', 'Off'], $checkboxWidget['appearance_states']);
        $t->same(21, $checkboxWidget['normal_appearance']['selected_appearance']['object']);

        $t->same('acroform_xfa_widget_currentbase_review_boundary', $checkboxReview['source']);
        $t->same('Btn', $checkboxReview['field_type']);
        $t->same(['consent.email'], $checkboxReview['matched_data_paths']);
        $t->same(['XFA checked value stays metadata'], $checkboxReview['matched_data_value_previews']);
        $t->same(['Yes', 'Off'], $checkboxReview['widget_appearance_states']);
        $t->same(['Yes'], $checkboxReview['checked_widget_export_values']);
        $t->same(21, $checkboxReview['selected_appearance_object']);
        $t->same(hash('sha256', $checkedAppearance), $checkboxReview['selected_appearance_decoded_sha256']);
        $t->same(true, $checkboxReview['acroform_current_value_authoritative']);
        $t->same(false, $checkboxReview['xfa_value_used_for_widget_state']);
        $t->same(false, $checkboxReview['appearance_payload_text_exposed']);
        $t->same(false, $checkboxReview['executes_xfa_javascript']);
        $t->same(false, $checkboxReview['executes_form_actions']);

        $t->contains('Visible XFA widget current-base body', $visibleText);
        $t->same(false, str_contains($visibleText, 'XFA dynamic title must not import'));
        $t->same(false, str_contains($visibleText, 'XFA checked value stays metadata'));
        $t->same(false, str_contains($visibleText, 'Article title'));
        $t->same(false, str_contains($visibleText, 'Email consent'));
        $t->same(false, str_contains($visibleText, trim($xdpXml)));
        $t->contains('Title widget appearance review only', $visibleText);
        $t->contains('Checked widget appearance review only', $visibleText);
    },
];
