<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[(string) ($field['name'] ?? '')] = $field;
    }

    return $indexed;
};

$actionsByTrigger = static function (array $actions): array {
    $indexed = [];
    foreach ($actions as $action) {
        $indexed[(string) ($action['trigger'] ?? '')] = $action;
    }

    return $indexed;
};

$acroFormActionFieldGenerationBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action field generation boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 1 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 1 R 10 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 1 obj\n<< /FT /Tx /T (current.email) /V (current@example.test) /Kids [8 1 R] /AA << /V 30 0 R /F 31 0 R >> >>\nendobj\n"
        . "8 1 obj\n<< /Subtype /Widget /Parent 6 1 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (stale.email) /V (stale@example.test) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 620 320 644] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (current.title) /V (Reviewed title) /DV (Default title) /Kids [12 0 R] /AA << /Bl 32 0 R >> >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /S /SubmitForm /F (https://example.test/submit) /Fields [6 0 R 10 0 R 99 0 R (named.extra)] /Flags 4 >>\nendobj\n"
        . "31 0 obj\n<< /S /ResetForm /Fields [6 0 R 10 0 R 99 0 R (named.extra)] >>\nendobj\n"
        . "32 0 obj\n<< /S /Hide /T [6 0 R 10 0 R 100 0 R (named.extra)] /H true >>\nendobj\n"
        . "%%EOF";
};

return [
    'drops stale-generation AcroForm action field references while preserving missing review targets' => static function (
        TestRunner $t
    ) use ($acroFormActionFieldGenerationBoundaryPdf, $fieldsByName, $actionsByTrigger): void {
        $pdf = $acroFormActionFieldGenerationBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['current.email', 'current.title'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['current.email'];
        $emailActions = $actionsByTrigger($email['actions']);
        $submit = $emailActions['V'];
        $reset = $emailActions['F'];

        $t->same('SubmitForm', $submit['action_type']);
        $t->same([10, 99], $submit['field_objects']);
        $t->same(['current.title', 'named.extra'], $submit['field_names']);
        $t->same([99], $submit['unresolved_field_objects']);
        $t->same(['current.title'], $submit['action_resource_review']['selected_field_names']);
        $t->same(['current.title'], $submit['field_value_review']['submitted_field_names']);
        $t->same(false, in_array('current.email', $submit['field_names'], true));
        $t->same(false, in_array(6, $submit['field_objects'], true));
        $t->same(false, str_contains((string) $encoded, 'stale.email'));
        $t->same(false, str_contains((string) $encoded, 'stale@example.test'));

        $t->same('ResetForm', $reset['action_type']);
        $t->same([10, 99], $reset['field_objects']);
        $t->same(['current.title', 'named.extra'], $reset['field_names']);
        $t->same([99], $reset['unresolved_field_objects']);
        $t->same(['current.title'], $reset['action_resource_review']['selected_field_names']);
        $t->same(['current.title'], $reset['field_value_review']['reset_field_names']);
        $t->same(['current.title'], $reset['field_value_review']['default_value_field_names']);
        $t->same(false, in_array('current.email', $reset['field_names'], true));
        $t->same(false, in_array(6, $reset['field_objects'], true));

        $title = $fields['current.title'];
        $titleActions = $actionsByTrigger($title['actions']);
        $hide = $titleActions['Bl'];
        $t->same('Hide', $hide['action_type']);
        $t->same([10, 100], $hide['field_objects']);
        $t->same(['current.title', 'named.extra'], $hide['field_names']);
        $t->same([100], $hide['unresolved_field_objects']);
        $t->same(false, in_array('current.email', $hide['field_names'], true));
        $t->same(false, in_array(6, $hide['field_objects'], true));
        $t->same(false, $hide['executes_action']);
        $t->same(false, $hide['executes_javascript']);

        foreach ([
            'current@example.test',
            'Reviewed title',
            'Default title',
            'stale@example.test',
            'https://example.test/submit',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }
        $t->same('Visible AcroForm action field generation boundary body', $visibleText);
    },
];
