<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

$acroFormPdf = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 11 0 R 14 0 R 18 0 R 20 0 R 22 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 13 0 R 17 0 R 21 0 R 22 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 1 rg) /DR << /Font << /Helv 16 0 R >> >> >>\nendobj\n"
        . "6 0 obj\n<< /T (registration) /FT /Tx /Ff 3 /Kids [7 0 R 10 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Parent 6 0 R /T (email) /V (editor@example.com) /DA (/Ti 11 Tf 0.25 g) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 7 0 R /Rect [300 620 72 600] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Parent 6 0 R /T (secret) /Ff 8192 /V (do not leak) /Kids [11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 560 300 580] /P 3 0 R /F 4 >>\nendobj\n"
        . "13 0 obj\n<< /FT /Ch /T (category) /Ff 917504 /V (Plugins) /Opt [(Themes) [(plugin) (Plugins)] (Blocks)] /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 520 220 540] /P 3 0 R /DA (/Helv 10 Tf 1 0 0 rg) >>\nendobj\n"
        . "17 0 obj\n<< /FT /Btn /T (delivery) /Ff 49152 /V /Pickup /Kids [18 0 R 20 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 17 0 R /Rect [72 480 90 498] /P 3 0 R /AS /Pickup /AP << /N << /Pickup 19 0 R /Off 19 0 R >> >> >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Widget /Parent 17 0 R /Rect [110 480 128 498] /P 3 0 R /AS /Off /AP << /N << /Courier 19 0 R /Off 19 0 R >> >> >>\nendobj\n"
        . "21 0 obj\n<< /FT /Tx /T (metadata_only) /Ff 4 /V (Not exported) >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline) /V <FEFF0049006E006C0069006E00650020006600690065006C0064> /DA (/Helv 8 Tf 0 1 0 rg) /Rect [72 440 240 460] >>\nendobj\n"
        . "16 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "19 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "%%EOF";
};

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$signatureDocMdpPdf = static function (string $transformParams): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R /Perms << /DocMDP 30 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /SigFlags 3 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Sig /T (approval.signature) /Ff 1 /V 30 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 300 684] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /Name (Editor Reviewer) /Reason (Approved for import) /Location (Remote) /ContactInfo (editor@example.com) /M (D:20260602032148Z) /ByteRange [0 128 512 64] /Contents <0102030405> /Reference [<< /Type /SigRef /TransformMethod /DocMDP /Data 1 0 R /TransformParams {$transformParams} >>] >>\nendobj\n"
        . "%%EOF";
};

$xfaPacketPdf = static function (): array {
    $templateXml = <<<'XML'
<template xmlns="http://www.xfa.org/schema/xfa-template/3.3/">
  <subform name="registration">
    <field name="registration.email"><caption><value><text>Email</text></value></caption></field>
    <field name="registration.secret"><caption><value><text>Secret</text></value></caption></field>
  </subform>
</template>
XML;
    $datasetsXml = <<<'XML'
<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:data>
    <registration><email>editor@example.com</email><secret>do not render</secret></registration>
  </xfa:data>
</xfa:datasets>
XML;
    $configXml = '<config><present>pdf</present></config>';
    $templateStream = gzcompress($templateXml);
    assert(is_string($templateStream));
    $configHex = strtoupper(bin2hex($configXml));

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /XFA [(template) 30 0 R (datasets) 31 0 R (config) <{$configHex}>] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (fallback.email) /V (fallback@example.com) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($templateStream) . " /Filter /FlateDecode >>\nstream\n"
        . $templateStream
        . "\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($datasetsXml) . " >>\nstream\n"
        . $datasetsXml
        . "\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, hash('sha256', $templateXml), hash('sha256', $datasetsXml), hash('sha256', $configXml)];
};

$submitResetActionPdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 10 0 R 12 0 R 14 0 R 16 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 9 0 R 11 0 R 13 0 R 15 0 R] /NeedAppearances false >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (registration.email) /V (editor@example.com) /DV (pending@example.com) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 320 644] /P 3 0 R /F 4 >>\nendobj\n"
        . "9 0 obj\n<< /FT /Tx /T (registration.notes) /DV (Default reviewer note) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 9 0 R /Rect [72 580 320 604] /P 3 0 R /F 4 >>\nendobj\n"
        . "11 0 obj\n<< /FT /Tx /T (registration.internal) /Ff 4 /V (Do not export) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 11 0 R /Rect [72 540 320 564] /P 3 0 R /F 4 >>\nendobj\n"
        . "13 0 obj\n<< /FT /Btn /T (actions.submit) /Ff 65536 /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 13 0 R /Rect [72 500 180 524] /P 3 0 R /F 4 /A << /S /SubmitForm /F 20 0 R /Fields [6 0 R 9 0 R] /Flags 6 >> >>\nendobj\n"
        . "15 0 obj\n<< /FT /Btn /T (actions.reset) /Ff 65536 /Kids [16 0 R] /AA << /U << /S /ResetForm /Fields [6 0 R] /Flags 1 >> >> >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 15 0 R /Rect [192 500 300 524] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (https://example.test/marker-import) >>\nendobj\n"
        . "%%EOF";
};

$calculationFormatActionPdf = static function (): array {
    $calculateScript = "event.value = this.getField('invoice.amount').value * 1.0825;";
    $compressedCalculateScript = gzcompress($calculateScript);
    if (!is_string($compressedCalculateScript)) {
        throw new RuntimeException('Unable to compress calculation script fixture.');
    }

    $keystrokeScript = 'AFNumber_Keystroke(2, 0, 0, 0, "", true);';
    $formatScript = 'AFNumber_Format(2, 0, 0, 0, "", true);';
    $validateScript = 'if (event.value < 0) event.rc = false;';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 11 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /CO [10 0 R 6 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (invoice.amount) /V (25.00) /Kids [8 0 R] /AA << /K 20 0 R /F << /S /JavaScript /JS (AFNumber_Format\\(2, 0, 0, 0, \"\", true\\);) >> /V << /S /JavaScript /JS (if \\(event.value < 0\\) event.rc = false;) >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 240 644] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (invoice.total) /V (27.06) /Kids [11 0 R] /AA << /C << /S /JavaScript /JS 30 0 R >> >> >>\nendobj\n"
        . "11 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 580 240 604] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /S /JavaScript /JS (AFNumber_Keystroke\\(2, 0, 0, 0, \"\", true\\);) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($compressedCalculateScript) . " /Filter /FlateDecode >>\nstream\n"
        . $compressedCalculateScript
        . "\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $keystrokeScript, $formatScript, $validateScript, $calculateScript];
};

$currentValueStatePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [8 0 R 12 0 R 16 0 R 18 0 R 22 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 20 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Final import title) /DV (Draft import title) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (article.topics) /Ff 2097152 /V [(plugin) (themes)] /DV (blocks) /I [1 0] /Opt [[(themes) (Themes)] [(plugin) (Plugins)] [(blocks) (Blocks)]] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Btn /T (delivery.method) /Ff 49152 /V /Online /DV /Pickup /Kids [16 0 R 18 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 90 578] /P 3 0 R /F 4 /AS /Online /AP << /N << /Online 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [108 560 126 578] /P 3 0 R /F 4 /AS /Off /AP << /N << /Pickup 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
        . "20 0 obj\n<< /FT /Btn /T (review.consent) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 520 90 538] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 30 0 R /Off 30 0 R >> >> >>\nendobj\n"
        . "30 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'extracts inherited field flags and field default appearance strings' => static function (TestRunner $t) use ($acroFormPdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($acroFormPdf());
        $fields = $fieldsByName($form['fields']);

        $t->true($form['need_appearances']);
        $t->same(6, count($fields));

        $email = $fields['registration.email'];
        $t->same(7, $email['object']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.com', $email['value']);
        $t->same(3, $email['flags']);
        $t->same(['read_only', 'required'], $email['flag_names']);
        $t->same('/Ti 11 Tf 0.25 g', $email['default_appearance']['raw']);
        $t->same('field', $email['default_appearance']['source']);
        $t->same('Ti', $email['default_appearance']['font_resource']);
        $t->same(11.0, $email['default_appearance']['font_size']);
        $t->same(['space' => 'DeviceGray', 'components' => [0.25]], $email['default_appearance']['text_color']);
        $t->same(0, $email['widgets'][0]['page_index']);
        $t->same(3, $email['widgets'][0]['page_object']);
        $t->same([72.0, 600.0, 300.0, 620.0], $email['widgets'][0]['rect']);
    },
    'redacts password values while preserving inherited AcroForm appearance metadata' => static function (TestRunner $t) use ($acroFormPdf, $fieldsByName): void {
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($acroFormPdf()));
        $secret = $fields['registration.secret'];

        $t->same(['password'], $secret['flag_names']);
        $t->true($secret['value_redacted']);
        $t->same(null, $secret['value']);
        $t->same('/Helv 9 Tf 0 0 1 rg', $secret['default_appearance']['raw']);
        $t->same('acroform', $secret['default_appearance']['source']);
        $t->same('Helv', $secret['default_appearance']['font_resource']);
        $t->same(9.0, $secret['default_appearance']['font_size']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.0, 0.0, 1.0]], $secret['default_appearance']['text_color']);
    },
    'decodes choice and button field flags with widget-local appearance overrides' => static function (TestRunner $t) use ($acroFormPdf, $fieldsByName): void {
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($acroFormPdf()));

        $choice = $fields['category'];
        $t->same('choice', $choice['field_type_label']);
        $t->same('Plugins', $choice['value']);
        $t->same(['combo', 'edit', 'sort'], $choice['flag_names']);
        $t->same([
            ['export' => 'Themes', 'label' => 'Themes'],
            ['export' => 'plugin', 'label' => 'Plugins'],
            ['export' => 'Blocks', 'label' => 'Blocks'],
        ], $choice['options']);
        $t->same('widget', $choice['widgets'][0]['default_appearance']['source']);
        $t->same('DeviceRGB', $choice['widgets'][0]['default_appearance']['text_color']['space']);
        $t->same([1.0, 0.0, 0.0], $choice['widgets'][0]['default_appearance']['text_color']['components']);

        $button = $fields['delivery'];
        $t->same('button', $button['field_type_label']);
        $t->same('Pickup', $button['value']);
        $t->same(['no_toggle_to_off', 'radio'], $button['flag_names']);
        $t->same(['Pickup', 'Off'], $button['widgets'][0]['appearance_states']);
        $t->same('Pickup', $button['widgets'][0]['appearance_state']);
        $t->same('Off', $button['widgets'][1]['appearance_state']);
    },
    'keeps metadata-only and fused widget fields page-scoped without leaking hidden values' => static function (TestRunner $t) use ($acroFormPdf, $fieldsByName): void {
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($acroFormPdf()));

        $metadataOnly = $fields['metadata_only'];
        $t->same(['no_export'], $metadataOnly['flag_names']);
        $t->same([], $metadataOnly['widgets']);
        $t->same('Not exported', $metadataOnly['value']);

        $inline = $fields['inline'];
        $t->same('Inline field', $inline['value']);
        $t->same(0, $inline['widgets'][0]['page_index']);
        $t->same('field', $inline['default_appearance']['source']);
        $t->same('Helv', $inline['default_appearance']['font_resource']);
        $t->same(['space' => 'DeviceRGB', 'components' => [0.0, 1.0, 0.0]], $inline['default_appearance']['text_color']);
    },
    'extracts signature field metadata and catalog DocMDP annotation permissions' => static function (TestRunner $t) use ($signatureDocMdpPdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($signatureDocMdpPdf('<< /Type /TransformParams /P 3 /V /1.2 >>'));
        $permissions = $form['permissions']['doc_mdp'];
        $fields = $fieldsByName($form['fields']);
        $field = $fields['approval.signature'];
        $signature = $field['signature'];
        $docMdp = $signature['doc_mdp'];

        $t->same('Sig', $field['field_type']);
        $t->same('signature', $field['field_type_label']);
        $t->same(['read_only'], $field['flag_names']);
        $t->true($field['certifying_signature']);
        $t->same(null, $field['value']);
        $t->same(0, $field['widgets'][0]['page_index']);
        $t->same([72.0, 640.0, 300.0, 684.0], $field['widgets'][0]['rect']);

        $t->same(30, $signature['object']);
        $t->same('Adobe.PPKLite', $signature['filter']);
        $t->same('adbe.pkcs7.detached', $signature['subfilter']);
        $t->same('Editor Reviewer', $signature['name']);
        $t->same('Approved for import', $signature['reason']);
        $t->same('Remote', $signature['location']);
        $t->same('editor@example.com', $signature['contact_info']);
        $t->same('D:20260602032148Z', $signature['signed_at']);
        $t->same([0, 128, 512, 64], $signature['byte_range']);
        $t->true($signature['contents_present']);
        $t->same(5, $signature['contents_length_bytes']);
        $t->true($signature['certifying_signature']);

        $t->same(1, count($signature['reference_transforms']));
        $t->same('DocMDP', $docMdp['transform_method']);
        $t->same(1, $docMdp['data_object']);
        $t->same('TransformParams', $docMdp['transform_params_type']);
        $t->same('1.2', $docMdp['transform_params_version']);
        $t->same(3, $docMdp['permission_level']);
        $t->true($docMdp['permission_valid']);
        $t->same('form_fill_templates_signatures_annotations', $docMdp['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'], $docMdp['allowed_changes']);

        $t->same(30, $permissions['signature_object']);
        $t->same('Editor Reviewer', $permissions['signature_name']);
        $t->same('D:20260602032148Z', $permissions['signed_at']);
        $t->same(3, $permissions['permission_level']);
        $t->same('form_fill_templates_signatures_annotations', $permissions['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'], $permissions['allowed_changes']);
        $t->same('1.2', $permissions['transform_params_version']);
        $t->same('catalog_perms_doc_mdp', $permissions['source']);
    },
    'defaults DocMDP permissions to form fill and signing when transform P is absent' => static function (TestRunner $t) use ($signatureDocMdpPdf, $fieldsByName): void {
        $form = (new PdfAcroFormExtractor())->extractForm($signatureDocMdpPdf('<< /Type /TransformParams /V /1.2 >>'));
        $field = $fieldsByName($form['fields'])['approval.signature'];
        $docMdp = $field['signature']['doc_mdp'];

        $t->same(2, $form['permissions']['doc_mdp']['permission_level']);
        $t->same('form_fill_templates_signatures', $form['permissions']['doc_mdp']['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign'], $form['permissions']['doc_mdp']['allowed_changes']);
        $t->same(2, $docMdp['permission_level']);
        $t->true($docMdp['permission_valid']);
        $t->same('form_fill_templates_signatures', $docMdp['permission_label']);
        $t->same(['fill_forms', 'instantiate_page_templates', 'sign'], $docMdp['allowed_changes']);
    },
    'extracts XFA packet array metadata without merging dynamic XML into AcroForm fields' => static function (TestRunner $t) use ($xfaPacketPdf, $fieldsByName): void {
        [$pdf, $templateHash, $datasetsHash, $configHash] = $xfaPacketPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $packets = $form['xfa_packets'];

        $t->true($form['xfa_overrides_page_content']);
        $t->same(1, count($fields));
        $t->same('fallback@example.com', $fields['fallback.email']['value']);
        $t->same(3, count($packets));

        $template = $packets[0];
        $t->same(0, $template['index']);
        $t->same('template', $template['name']);
        $t->same(30, $template['object']);
        $t->same('acroform_xfa_array', $template['source']);
        $t->same(['FlateDecode'], $template['filters']);
        $t->same('template', $template['xml_root']);
        $t->same($templateHash, $template['xml_sha256']);
        $t->same(['registration.email', 'registration.secret'], $template['field_names']);
        $t->same([], $template['data_node_names']);
        $t->true($template['has_template']);
        $t->same(false, $template['has_datasets']);
        $t->true(str_contains($template['text_preview'], 'Email'));

        $datasets = $packets[1];
        $t->same('datasets', $datasets['name']);
        $t->same(31, $datasets['object']);
        $t->same([], $datasets['filters']);
        $t->same('xfa:datasets', $datasets['xml_root']);
        $t->same($datasetsHash, $datasets['xml_sha256']);
        $t->same([], $datasets['field_names']);
        $t->same(['registration', 'email', 'secret'], $datasets['data_node_names']);
        $t->same(false, $datasets['has_template']);
        $t->true($datasets['has_datasets']);
        $t->true(str_contains($datasets['text_preview'], 'editor@example.com'));

        $config = $packets[2];
        $t->same('config', $config['name']);
        $t->same(null, $config['object']);
        $t->same('config', $config['xml_root']);
        $t->same($configHash, $config['xml_sha256']);
        $t->same([], $config['field_names']);
        $t->same([], $config['data_node_names']);
        $t->same('pdf', $config['text_preview']);
    },
    'extracts SubmitForm and ResetForm action review metadata without executing actions' => static function (TestRunner $t) use ($submitResetActionPdf, $fieldsByName): void {
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($submitResetActionPdf()));
        $submitWidget = $fields['actions.submit']['widgets'][0];
        $submit = $submitWidget['actions'][0];
        $reset = $fields['actions.reset']['actions'][0];

        $t->same('SubmitForm', $submit['action_type']);
        $t->same('activation', $submit['trigger']);
        $t->same('widget', $submit['source']);
        $t->same(14, $submit['source_object']);
        $t->same('https://example.test/marker-import', $submit['target']);
        $t->same('https', $submit['target_scheme']);
        $t->same(6, $submit['flags']);
        $t->same(['include_no_value_fields', 'html_format'], $submit['flag_names']);
        $t->same('include', $submit['fields_mode']);
        $t->same([6, 9], $submit['field_objects']);
        $t->same(['registration.email', 'registration.notes'], $submit['field_names']);
        $t->same('html', $submit['submit_format']);
        $t->true($submit['include_no_value_fields']);
        $t->true($submit['default_excludes_no_export']);
        $t->same(false, $submit['executes_action']);

        $t->same('ResetForm', $reset['action_type']);
        $t->same('U', $reset['trigger']);
        $t->same('mouse_up', $reset['trigger_label']);
        $t->same('field', $reset['source']);
        $t->same(15, $reset['source_object']);
        $t->same(1, $reset['flags']);
        $t->same(['exclude_list'], $reset['flag_names']);
        $t->same('exclude', $reset['fields_mode']);
        $t->same([6], $reset['field_objects']);
        $t->same(['registration.email'], $reset['field_names']);
        $t->true($reset['reset_to_default']);
        $t->same(false, $reset['executes_action']);
    },
    'extracts AcroForm current value state from V DV I Opt and widget appearance states' => static function (TestRunner $t) use ($currentValueStatePdf, $fieldsByName): void {
        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($currentValueStatePdf()));

        $titleState = $fields['article.title']['value_state'];
        $t->same('acroform_current_value_state', $titleState['source']);
        $t->same('Final import title', $titleState['current']);
        $t->same('Draft import title', $titleState['default']);
        $t->same('Final import title', $titleState['display_value']);
        $t->true($titleState['has_current_value']);
        $t->true($titleState['has_default_value']);
        $t->true($titleState['changed_from_default']);
        $t->same('field', $titleState['current_source']);
        $t->same(6, $titleState['current_source_object']);

        $topicState = $fields['article.topics']['value_state'];
        $t->same(['plugin', 'themes'], $topicState['choice_values']);
        $t->same(['blocks'], $topicState['default_choice_values']);
        $t->same([1, 0], $topicState['selected_indices']);
        $t->same('field', $topicState['selected_indices_source']);
        $t->same([
            ['index' => 1, 'export' => 'plugin', 'label' => 'Plugins'],
            ['index' => 0, 'export' => 'themes', 'label' => 'Themes'],
        ], $topicState['selected_options']);
        $t->same([], $topicState['unmatched_values']);
        $t->true($topicState['changed_from_default']);

        $delivery = $fields['delivery.method'];
        $deliveryState = $delivery['value_state'];
        $t->same('radio', $deliveryState['button_kind']);
        $t->same('Online', $deliveryState['current_state']);
        $t->same('Pickup', $deliveryState['default_state']);
        $t->same('Online', $deliveryState['effective_current_state']);
        $t->same('field_value', $deliveryState['state_source']);
        $t->same(['Online', 'Pickup'], $deliveryState['on_values']);
        $t->same(1, $deliveryState['checked_widget_count']);
        $t->true($deliveryState['widget_state_consistent']);
        $t->true($delivery['widgets'][0]['checked']);
        $t->same('Online', $delivery['widgets'][0]['export_value']);
        $t->true($delivery['widgets'][0]['selected_by_field_value']);
        $t->true($delivery['widgets'][0]['state_matches_field_value']);
        $t->same(false, $delivery['widgets'][1]['checked']);
        $t->same('Pickup', $delivery['widgets'][1]['export_value']);
        $t->same(false, $delivery['widgets'][1]['selected_by_field_value']);
        $t->true($delivery['widgets'][1]['state_matches_field_value']);

        $consent = $fields['review.consent'];
        $consentState = $consent['value_state'];
        $t->same('checkbox', $consentState['button_kind']);
        $t->same(null, $consentState['current_state']);
        $t->same('Yes', $consentState['effective_current_state']);
        $t->same('widget_appearance_state', $consentState['state_source']);
        $t->same(['Yes'], $consentState['on_values']);
        $t->same(1, $consentState['checked_widget_count']);
        $t->same(null, $consentState['widget_state_consistent']);
        $t->true($consent['widgets'][0]['checked']);
        $t->same('Yes', $consent['widgets'][0]['export_value']);
        $t->same(null, $consent['widgets'][0]['state_matches_field_value']);
    },
    'extracts calculation format keystroke and validation action review metadata without executing scripts' => static function (TestRunner $t) use ($calculationFormatActionPdf, $fieldsByName): void {
        [$pdf, $keystrokeScript, $formatScript, $validateScript, $calculateScript] = $calculationFormatActionPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);

        $t->same([
            ['object' => 10, 'field_name' => 'invoice.total'],
            ['object' => 6, 'field_name' => 'invoice.amount'],
        ], $form['calculation_order']);

        $amountActions = $fields['invoice.amount']['actions'];
        $totalActions = $fields['invoice.total']['actions'];

        $t->same(['K', 'F', 'V'], array_column($amountActions, 'trigger'));
        $t->same(['keystroke', 'format', 'validate'], array_column($amountActions, 'trigger_label'));
        $t->same(['JavaScript', 'JavaScript', 'JavaScript'], array_column($amountActions, 'action_type'));
        $t->same([$keystrokeScript, $formatScript, $validateScript], array_column($amountActions, 'script_preview'));
        $t->same([
            hash('sha256', $keystrokeScript),
            hash('sha256', $formatScript),
            hash('sha256', $validateScript),
        ], array_column($amountActions, 'script_sha256'));
        $t->same([strlen($keystrokeScript), strlen($formatScript), strlen($validateScript)], array_column($amountActions, 'script_bytes'));
        $t->same([false, false, false], array_column($amountActions, 'script_truncated'));
        $t->same([false, false, false], array_column($amountActions, 'executes_javascript'));
        $t->same([false, false, false], array_column($amountActions, 'executes_action'));

        $calculate = $totalActions[0];
        $t->same('JavaScript', $calculate['action_type']);
        $t->same('C', $calculate['trigger']);
        $t->same('calculate', $calculate['trigger_label']);
        $t->same('field', $calculate['source']);
        $t->same(10, $calculate['source_object']);
        $t->same($calculateScript, $calculate['script_preview']);
        $t->same(hash('sha256', $calculateScript), $calculate['script_sha256']);
        $t->same(strlen($calculateScript), $calculate['script_bytes']);
        $t->same(30, $calculate['script_object']);
        $t->same(['FlateDecode'], $calculate['script_filters']);
        $t->same(false, $calculate['executes_javascript']);
        $t->same(false, $calculate['executes_action']);
        $t->same('27.06', $fields['invoice.total']['value']);
    },
];
