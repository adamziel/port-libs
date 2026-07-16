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

$widgetAppearanceActionCyclePdf = static function (): array {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible form review body) Tj ET';
    $appearance = 'BT /FApp 9 Tf 0 0 Td (Widget appearance stays metadata) Tj ET';
    $script = "app.alert('widget cycle review only');";
    $compressedScript = gzcompress($script);
    if (!is_string($compressedScript)) {
        throw new RuntimeException('Unable to compress widget action cycle script.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.action) /V (Final widget field value) /DV (Draft widget field value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 340 664] /P 3 0 R /F 4 /AS /Ready /AP << /N << /Ready 30 0 R /Off 31 0 R >> >> /A 20 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://example.test/review) /Next [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /S /JavaScript /JS 40 0 R /Next 23 0 R >>\nendobj\n"
        . "22 0 obj\n<< /S /Launch /F (helper.exe) /Next 20 0 R >>\nendobj\n"
        . "23 0 obj\n<< /S /Hide /T [8 0 R] /H true /Next 21 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 24] /Resources << /Font << /FApp 32 0 R >> >> /Length " . strlen($appearance) . " >>\nstream\n{$appearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "40 0 obj\n<< /Length " . strlen($compressedScript) . " /Filter /FlateDecode >>\nstream\n{$compressedScript}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $appearance, $script];
};

return [
    'reviews cyclic widget action chains while selected appearances stay metadata only' => static function (TestRunner $t) use ($widgetAppearanceActionCyclePdf, $fieldsByName): void {
        [$pdf, $appearance, $script] = $widgetAppearanceActionCyclePdf();

        $fields = $fieldsByName((new PdfAcroFormExtractor())->extractFields($pdf));
        $field = $fields['article.action'];
        $widget = $field['widgets'][0];
        $appearanceReview = $widget['normal_appearance'];
        $selectedAppearance = $appearanceReview['selected_appearance'];
        $actions = $widget['actions'];
        $actionReview = $widget['action_review'];
        $text = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Final widget field value', $field['value']);
        $t->same('Final widget field value', $field['value_state']['current']);
        $t->same('Draft widget field value', $field['value_state']['default']);
        $t->true($field['value_state']['changed_from_default']);
        $t->same(0, $field['action_review']['action_count']);
        $t->same(0, $field['action_review']['cycle_edges_blocked']);

        $t->same('Ready', $widget['appearance_state']);
        $t->same(['Ready', 'Off'], $widget['appearance_states']);
        $t->same('state_dictionary', $appearanceReview['normal_appearance_type']);
        $t->same('Ready', $appearanceReview['selected_state']);
        $t->true($appearanceReview['state_matches_appearance']);
        $t->same(false, $appearanceReview['appearance_value_used_for_import']);
        $t->same(false, $appearanceReview['payload_text_exposed']);
        $t->same(30, $selectedAppearance['object']);
        $t->same(hash('sha256', $appearance), $selectedAppearance['decoded_sha256']);
        $t->same(false, $selectedAppearance['imports_visible_text']);
        $t->same(false, $selectedAppearance['executes_appearance_streams']);

        $t->same(['URI', 'JavaScript', 'Hide', 'Launch'], array_column($actions, 'action_type'));
        $t->same(['activation', 'activation', 'activation', 'activation'], array_column($actions, 'trigger'));
        $t->same([false, true, true, true], array_map(
            static fn (array $action): bool => (bool) ($action['chained'] ?? false),
            $actions
        ));
        $t->same([20, 21, 23, 22], array_column($actions, 'action_object'));
        $t->same('https://example.test/review', $actions[0]['target']);
        $t->same($script, $actions[1]['script_preview']);
        $t->same(hash('sha256', $script), $actions[1]['script_sha256']);
        $t->same(['FlateDecode'], $actions[1]['script_filters']);
        $t->same(['article.action'], $actions[2]['field_names']);
        $t->same('helper.exe', $actions[3]['target']);
        $t->same([false, false, false, false], array_column($actions, 'executes_action'));
        $t->same([false, false, false, false], array_map(
            static fn (array $action): bool => (bool) ($action['executes_javascript'] ?? false),
            $actions
        ));

        $t->same('acroform_action_chain_review_boundary', $actionReview['source']);
        $t->same('widget', $actionReview['action_source']);
        $t->same(8, $actionReview['source_object']);
        $t->same(8, $actionReview['max_depth']);
        $t->same(4, $actionReview['action_count']);
        $t->same(3, $actionReview['chained_action_count']);
        $t->same(2, $actionReview['cycle_edges_blocked']);
        $t->same(0, $actionReview['max_depth_edges_blocked']);
        $t->same([21, 20], $actionReview['blocked_cycle_action_objects']);
        $t->same([], $actionReview['blocked_max_depth_action_objects']);
        $t->true($actionReview['has_blocked_cycle']);
        $t->same(false, $actionReview['executes_action']);
        $t->same(false, $actionReview['executes_javascript']);
        $t->same(false, $actionReview['executes_appearance_streams']);

        $t->same("Visible form review body\nWidget appearance stays metadata", $text);
        $t->true(!str_contains($text, 'widget cycle review'));
        $t->true(!str_contains($text, 'helper.exe'));
    },
];
