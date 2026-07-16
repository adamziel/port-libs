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

$pdfHexTextString = static function (string $value): string {
    $encoded = iconv('UTF-8', 'UTF-16BE', $value);
    assert(is_string($encoded));

    return '<FEFF' . strtoupper(bin2hex($encoded)) . '>';
};

$acroFormMalformedHexScalarPdf = static function () use ($pdfHexTextString): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm malformed hex scalar body) Tj ET';
    $fieldName = $pdfHexTextString('workflow.hex_status');
    $fieldLabel = $pdfHexTextString('Hex status label');
    $mappingName = $pdfHexTextString('workflow.hex_status.export');

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Ch /T {$fieldName} /TU {$fieldLabel} /TM {$mappingName} /Ff 2097152 "
        . "/V [<2F /private_choice_decoy> (publish)] /DV [<2F /draft_decoy> (draft)] "
        . "/Opt [<2F /private_choice_decoy> [(draft) (Draft label)] [(publish) (Published label)] <2F /archive_decoy>] "
        . "/Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'consumes malformed hex-string scalar operands before AcroForm choice review' => static function (
        TestRunner $t
    ) use ($acroFormMalformedHexScalarPdf, $fieldsByName): void {
        $pdf = $acroFormMalformedHexScalarPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['workflow.hex_status'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['workflow.hex_status'];
        $t->same(6, $field['object']);
        $t->same('workflow.hex_status', $field['name']);
        $t->same('workflow.hex_status', $field['partial_name']);
        $t->same('Hex status label', $field['alternate_name']);
        $t->same('workflow.hex_status.export', $field['mapping_name']);
        $t->same('choice', $field['field_type_label']);
        $t->same(2097152, $field['flags']);
        $t->same(['multi_select'], $field['flag_names']);
        $t->same(['publish'], $field['value']);
        $t->same(['draft'], $field['default_value']);
        $t->same('publish', $field['value_state']['display_value']);
        $t->same(['publish'], $field['value_state']['choice_values']);
        $t->same(['draft'], $field['value_state']['default_choice_values']);
        $t->same([1], $field['value_state']['selected_indices']);
        $t->same('inferred_from_value', $field['value_state']['selected_indices_source']);
        $t->same([
            ['index' => 1, 'export' => 'publish', 'label' => 'Published label'],
        ], $field['value_state']['selected_options']);
        $t->same([], $field['value_state']['unmatched_values']);
        $t->same([
            ['export' => 'draft', 'label' => 'Draft label'],
            ['export' => 'publish', 'label' => 'Published label'],
        ], $field['options']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same('Hex status label', $field['field_name_review']['wordpress_label'] ?? null);
        $t->same(false, $field['field_name_review']['alternate_name_used_as_visible_text'] ?? null);
        $t->same(false, $field['field_name_review']['field_value_used_as_visible_text'] ?? null);

        foreach (['private_choice_decoy', 'draft_decoy', 'archive_decoy'] as $decoy) {
            $t->same(false, str_contains($encoded, $decoy));
            $t->same(false, str_contains($visibleText, $decoy));
        }

        $t->same('Visible AcroForm malformed hex scalar body', $visibleText);
        $t->same(false, str_contains($visibleText, 'publish'));
        $t->same(false, str_contains($visibleText, 'Draft label'));
        $t->same(false, str_contains($visibleText, 'Hex status label'));
    },
];
