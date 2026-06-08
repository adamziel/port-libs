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

$choiceOptionOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice option operand boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Ch /T (workflow.option_boundary) /V (publish) /Opt ["
        . "[(draft) [(decoy.export) (Nested array label decoy)] << /Nested (Dictionary label decoy) >> (Draft label)] "
        . "[(publish) << /Nested (Wrong published label decoy) >> (Published label)] "
        . "(archive)"
        . "] /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'ignores nested AcroForm choice option operands before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($choiceOptionOperandBoundaryPdf, $fieldsByName): void {
        $pdf = $choiceOptionOperandBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['workflow.option_boundary'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['workflow.option_boundary'];
        $t->same(6, $field['object']);
        $t->same('choice', $field['field_type_label']);
        $t->same('publish', $field['value']);
        $t->same([
            ['export' => 'draft', 'label' => 'Draft label'],
            ['export' => 'publish', 'label' => 'Published label'],
            ['export' => 'archive', 'label' => 'archive'],
        ], $field['options']);
        $t->same(['publish'], $field['value_state']['choice_values']);
        $t->same([1], $field['value_state']['selected_indices']);
        $t->same('inferred_from_value', $field['value_state']['selected_indices_source']);
        $t->same([
            ['index' => 1, 'export' => 'publish', 'label' => 'Published label'],
        ], $field['value_state']['selected_options']);
        $t->same([], $field['value_state']['unmatched_values']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'decoy.export',
            'Nested array label decoy',
            'Dictionary label decoy',
            'Wrong published label decoy',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        foreach (['Draft label', 'Published label', 'archive', 'publish'] as $reviewOnlyText) {
            $t->true(!str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm choice option operand boundary body', $visibleText);
    },
];
