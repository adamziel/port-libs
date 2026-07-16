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

$acroFormInheritedActionsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm inherited action boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article) /AA << /V 30 0 R /C 31 0 R >> /Kids [10 0 R 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Parent 6 0 R /T (title) /V (Reviewed inherited title) /DV (Draft inherited title) /Kids [14 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 6 0 R /T (slug) /V (reviewed-slug) /AA << /F 32 0 R >> /Kids [16 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /S /SubmitForm /F (https://example.test/inherited-submit) /Fields [10 0 R 12 0 R (named.extra)] /Flags 6 >>\nendobj\n"
        . "31 0 obj\n<< /S /JavaScript /JS (this.getField('article.title').value = 'calculated';) >>\nendobj\n"
        . "32 0 obj\n<< /S /ResetForm /Fields [10 0 R] >>\nendobj\n"
        . "%%EOF";
};

return [
    'inherits parent AcroForm additional actions as review metadata on terminal child fields' => static function (
        TestRunner $t
    ) use ($acroFormInheritedActionsBoundaryPdf, $fieldsByName, $actionsByTrigger): void {
        $pdf = $acroFormInheritedActionsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['article.title', 'article.slug'], array_keys($fields));
        $t->same(true, $form['need_appearances']);

        $title = $fields['article.title'];
        $t->same('text', $title['field_type_label']);
        $t->same('Reviewed inherited title', $title['value']);
        $t->same(['FT', 'AA'], $title['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV'], $title['field_hierarchy']['local_attributes']);
        $t->same(6, $title['field_hierarchy']['attribute_sources']['AA']['source_object']);
        $t->same(true, $title['field_hierarchy']['attribute_sources']['AA']['inherited']);

        $actions = $actionsByTrigger($title['actions']);
        $t->same(['V', 'C'], array_keys($actions));

        $submit = $actions['V'];
        $t->same('SubmitForm', $submit['action_type']);
        $t->same('validate', $submit['trigger_label']);
        $t->same('field', $submit['source']);
        $t->same(6, $submit['source_object']);
        $t->same(30, $submit['action_object']);
        $t->same(['include_no_value_fields', 'html_format'], $submit['flag_names']);
        $t->same([10, 12], $submit['field_objects']);
        $t->same(['article.title', 'article.slug', 'named.extra'], $submit['field_names']);
        $t->same(['article.title', 'article.slug'], $submit['field_value_review']['submitted_field_names']);
        $t->same(['article.title', 'article.slug'], $submit['action_resource_review']['selected_field_names']);
        $t->same(false, $submit['executes_action']);

        $calculate = $actions['C'];
        $t->same('JavaScript', $calculate['action_type']);
        $t->same('calculate', $calculate['trigger_label']);
        $t->same(6, $calculate['source_object']);
        $t->same(31, $calculate['action_object']);
        $t->same(false, $calculate['executes_action']);
        $t->same(false, $calculate['executes_javascript']);

        $calculationState = $title['calculation_state'];
        $t->same(true, $calculationState['has_calculate_action']);
        $t->same(6, $calculationState['calculate_actions'][0]['source_object']);
        $t->same(31, $calculationState['calculate_actions'][0]['action_object']);
        $t->same(false, $calculationState['executes_javascript']);

        foreach ([
            'Reviewed inherited title',
            'Draft inherited title',
            'reviewed-slug',
            'https://example.test/inherited-submit',
            'calculated',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }
        $t->same('Visible AcroForm inherited action boundary body', $visibleText);
    },
    'lets terminal AcroForm additional actions override inherited parent actions' => static function (
        TestRunner $t
    ) use ($acroFormInheritedActionsBoundaryPdf, $fieldsByName, $actionsByTrigger): void {
        $form = (new PdfAcroFormExtractor())->extractForm($acroFormInheritedActionsBoundaryPdf());
        $fields = $fieldsByName($form['fields']);

        $slug = $fields['article.slug'];
        $t->same('reviewed-slug', $slug['value']);
        $t->same(['FT'], $slug['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'AA'], $slug['field_hierarchy']['local_attributes']);
        $t->same(12, $slug['field_hierarchy']['attribute_sources']['AA']['source_object']);
        $t->same(false, $slug['field_hierarchy']['attribute_sources']['AA']['inherited']);

        $actions = $actionsByTrigger($slug['actions']);
        $t->same(['F'], array_keys($actions));
        $reset = $actions['F'];
        $t->same('ResetForm', $reset['action_type']);
        $t->same('format', $reset['trigger_label']);
        $t->same(12, $reset['source_object']);
        $t->same(32, $reset['action_object']);
        $t->same([10], $reset['field_objects']);
        $t->same(['article.title'], $reset['field_names']);
        $t->same(['article.title'], $reset['field_value_review']['reset_field_names']);
        $t->same(false, isset($actions['V']));
        $t->same(false, isset($actions['C']));
        $t->same(false, $slug['calculation_state']['has_calculate_action']);
        $t->same(false, $reset['executes_action']);
        $t->same(false, $reset['executes_javascript']);
    },
];
