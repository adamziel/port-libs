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
];
