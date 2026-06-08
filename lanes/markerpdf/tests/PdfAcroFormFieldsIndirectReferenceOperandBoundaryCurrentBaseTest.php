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

$indirectReferenceOperandBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect reference operand body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [80 0 R 81 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [40 0 R 41 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (indirect.ref.email) /TU (Indirect ref label) /TM (indirect-ref-export) /V (indirect-ref@example.test) /DV (draft-indirect-ref@example.test) /MaxLen 80 /Kids [50 0 R 51 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 60 0 R /Rect [72 640 320 664] /P 70 0 R /F 4 >>\nendobj\n"
        . "40 0 obj\n6 0 R\nendobj\n"
        . "41 0 obj\n98 0 R 6 0 R\nendobj\n"
        . "50 0 obj\n8 0 R\nendobj\n"
        . "51 0 obj\n99 0 R 8 0 R\nendobj\n"
        . "60 0 obj\n6 0 R\nendobj\n"
        . "70 0 obj\n3 0 R\nendobj\n"
        . "80 0 obj\n8 0 R\nendobj\n"
        . "81 0 obj\n90 0 R 8 0 R\nendobj\n"
        . "90 0 obj\n<< /Subtype /Widget /FT /Tx /T (tailed.annots.decoy) /V (Tailed Annots decoy value) /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "98 0 obj\n<< /FT /Tx /T (tailed.fields.decoy) /V (Tailed Fields reference value) >>\nendobj\n"
        . "99 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves indirect AcroForm field reference operands before page widget repair' => static function (
        TestRunner $t
    ) use ($indirectReferenceOperandBoundaryPdf, $fieldsByName): void {
        $pdf = $indirectReferenceOperandBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['indirect.ref.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['indirect.ref.email'];
        $t->same(6, $field['object']);
        $t->same('text', $field['field_type_label']);
        $t->same('Indirect ref label', $field['alternate_name']);
        $t->same('indirect-ref-export', $field['mapping_name']);
        $t->same('indirect-ref@example.test', $field['value']);
        $t->same('draft-indirect-ref@example.test', $field['default_value']);
        $t->same(80, $field['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);

        $widgets = $field['widgets'];
        $t->same(1, count($widgets));
        $t->same([8], array_column($widgets, 'object'));
        $t->same([0], array_column($widgets, 'page_index'));
        $t->same([3], array_column($widgets, 'page_object'));
        $t->same([0], array_column($widgets, 'page_annotation_index'));
        $t->same([true], array_column($widgets, 'referenced_from_page_annots'));
        $t->same([[72.0, 640.0, 320.0, 664.0]], array_column($widgets, 'rect'));
        $t->same([4], array_column($widgets, 'annotation_flags'));
        $t->same(['visible'], array_column($widgets, 'annotation_visibility'));

        foreach ([
            'tailed.fields.decoy',
            'Tailed Fields reference value',
            'tailed.annots.decoy',
            'Tailed Annots decoy value',
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm indirect reference operand body', $visibleText);
        $t->same(false, str_contains($visibleText, 'indirect-ref@example.test'));
        $t->same(false, str_contains($visibleText, 'Indirect ref label'));
        $t->same(false, str_contains($visibleText, 'draft-indirect-ref@example.test'));
    },
];
