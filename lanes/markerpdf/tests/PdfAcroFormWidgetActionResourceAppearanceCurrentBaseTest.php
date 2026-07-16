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

$widgetActionResourceAppearancePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible widget action resource appearance body) Tj ET';
    $appearance = 'q /Icon Do /Mask Do Q BT /FApp 9 Tf 0 0 Td (Selected widget appearance review) Tj ET';
    $script = "app.alert('appearance resource action blocked');";
    $compressedScript = gzcompress($script);
    if (!is_string($compressedScript)) {
        throw new RuntimeException('Unable to compress appearance resource script.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /DA (/FApp 9 Tf 0 0 0 rg) /DR << /Font << /FApp 32 0 R >> >> >>\nendobj\n"
        . "6 0 obj\n<< /FT /Btn /T (article.appearance_resource) /V /Yes /DV /Off /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 96 664] /P 3 0 R /F 4 /AS /Yes /AP << /N << /Yes 30 0 R /Off 31 0 R >> >> /A 20 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.test/widget-activation) >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Resources << /Font << /FApp 32 0 R >> /XObject << /Icon 35 0 R /Mask 36 0 R >> >> /Length " . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 24 24] /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "35 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /A 50 0 R /AA << /D 53 0 R >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "36 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\nx\nendstream\nendobj\n"
        . "50 0 obj\n<< /S /JavaScript /JS 52 0 R /Next 51 0 R >>\nendobj\n"
        . "51 0 obj\n<< /S /URI /URI (javascript:appearanceReview()) >>\nendobj\n"
        . "52 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
        . "53 0 obj\n<< /S /Hide /T [8 0 R] /H true >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $appearance, $script];
};

return [
    'reviews widget appearance resource xobject actions without executing them' => static function (TestRunner $t) use ($widgetActionResourceAppearancePdf, $fieldsByName): void {
        [$pdf, $appearance, $script] = $widgetActionResourceAppearancePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $field = $fields['article.appearance_resource'];
        $widget = $field['widgets'][0];
        $selected = $widget['normal_appearance']['selected_appearance'];
        $resourceReviews = $selected['resource_xobject_reviews'];
        $iconReview = $resourceReviews[0];
        $imageReview = $resourceReviews[1];
        $iconActions = $iconReview['actions'];
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Yes', $field['value']);
        $t->same('Yes', $field['value_state']['effective_current_state']);
        $t->same('field_value', $field['value_state']['state_source']);
        $t->same('Yes', $widget['appearance_state']);
        $t->same(30, $selected['object']);
        $t->same(hash('sha256', $appearance), $selected['decoded_sha256']);
        $t->same(['FApp'], $selected['resource_font_names']);
        $t->same(['Icon', 'Mask'], $selected['resource_xobject_names']);

        $t->same(2, count($resourceReviews));
        $t->same(3, $selected['resource_xobject_action_count']);
        $t->same(['JavaScript', 'URI', 'Hide'], $selected['resource_xobject_action_types']);
        $t->same([50, 51, 53], $selected['resource_xobject_action_objects']);
        $t->same(false, $selected['resource_xobject_payload_text_exposed']);
        $t->same(false, $selected['executes_action']);
        $t->same(false, $selected['executes_javascript']);
        $t->same(false, $selected['executes_appearance_streams']);

        $t->same('acroform_widget_appearance_resource_xobject_review_boundary', $iconReview['source']);
        $t->same('Icon', $iconReview['resource_name']);
        $t->same(35, $iconReview['xobject_object']);
        $t->same('XObject', $iconReview['type']);
        $t->same('Form', $iconReview['subtype']);
        $t->same([0.0, 0.0, 12.0, 12.0], $iconReview['bbox']);
        $t->same(3, $iconReview['action_count']);
        $t->same(['JavaScript', 'URI', 'Hide'], $iconReview['action_types']);
        $t->same([50, 51, 53], $iconReview['action_objects']);
        $t->same(false, $iconReview['executes_action']);
        $t->same(false, $iconReview['executes_javascript']);
        $t->same(false, $iconReview['payload_text_exposed']);

        $t->same('JavaScript', $iconActions[0]['action_type']);
        $t->same('activation', $iconActions[0]['trigger']);
        $t->same('appearance_resource_xobject', $iconActions[0]['source']);
        $t->same(35, $iconActions[0]['source_object']);
        $t->same(50, $iconActions[0]['action_object']);
        $t->same($script, $iconActions[0]['script_preview']);
        $t->same(hash('sha256', $script), $iconActions[0]['script_sha256']);
        $t->same(['FlateDecode'], $iconActions[0]['script_filters']);
        $t->same(false, $iconActions[0]['executes_javascript']);

        $t->same('URI', $iconActions[1]['action_type']);
        $t->same(true, $iconActions[1]['chained']);
        $t->same('javascript:appearanceReview()', $iconActions[1]['target']);
        $t->same('javascript', $iconActions[1]['target_scheme']);
        $t->same(false, $iconActions[1]['safe_uri']);
        $t->same('blocked-unsafe-uri', $iconActions[1]['safety']);
        $t->same(false, $iconActions[1]['executes_action']);

        $t->same('Hide', $iconActions[2]['action_type']);
        $t->same('D', $iconActions[2]['trigger']);
        $t->same('mouse_down', $iconActions[2]['trigger_label']);
        $t->same(['article.appearance_resource'], $iconActions[2]['field_names']);
        $t->same([8], $iconActions[2]['field_objects']);
        $t->same(true, $iconActions[2]['hide']);
        $t->same('hide', $iconActions[2]['operation']);
        $t->same(false, $iconActions[2]['executes_action']);

        $t->same('Mask', $imageReview['resource_name']);
        $t->same(36, $imageReview['xobject_object']);
        $t->same('Image', $imageReview['subtype']);
        $t->same(0, $imageReview['action_count']);
        $t->same([], $imageReview['actions']);

        $t->same("Visible widget action resource appearance body\nSelected widget appearance review", $text);
        $t->same(false, str_contains($text, 'appearance resource action blocked'));
        $t->same(false, str_contains($text, 'javascript:appearanceReview'));
    },
];
