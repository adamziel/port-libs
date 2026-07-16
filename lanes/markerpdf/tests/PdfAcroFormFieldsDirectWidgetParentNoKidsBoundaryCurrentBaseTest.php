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

$directWidgetParentNoKidsPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct widget parent no Kids body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [8 0 R 12 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (direct.nokids) /TU (Direct widget parent without Kids label) /TM (direct-nokids-export) /V (direct no-kids value) /DV (direct no-kids default) /MaxLen 48 >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (direct.emptykids) /TU (Explicit empty Kids decoy label) /TM (direct-emptykids-export) /V (explicit empty Kids direct decoy value) /Kids [] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (direct.mismatch) /TU (Mismatched Kids decoy label) /TM (direct-mismatch-export) /V (mismatched Kids direct decoy value) /Kids [18 0 R] >>\nendobj\n"
        . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'normalizes direct Widget Fields entries to Parent fields that omit Kids' => static function (
        TestRunner $t
    ) use ($directWidgetParentNoKidsPdf, $fieldsByName): void {
        $pdf = $directWidgetParentNoKidsPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.nokids'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['direct.nokids'];
        $t->same(6, $field['object']);
        $t->same('direct.nokids', $field['name']);
        $t->same('text', $field['field_type_label']);
        $t->same('direct no-kids value', $field['value']);
        $t->same('direct no-kids default', $field['default_value']);
        $t->same('Direct widget parent without Kids label', $field['alternate_name']);
        $t->same('direct-nokids-export', $field['mapping_name']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same(['V', 'DV'], $field['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(48, $field['max_length']);
        $t->same(false, $field['max_length_review']['max_length_inherited']);

        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        foreach ([
            'direct.emptykids',
            'direct.mismatch',
            'Explicit empty Kids decoy label',
            'Mismatched Kids decoy label',
            'explicit empty Kids direct decoy value',
            'mismatched Kids direct decoy value',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm direct widget parent no Kids body'));
        $t->true(!str_contains($visibleText, 'direct no-kids value'));
        $t->true(!str_contains($visibleText, 'direct no-kids default'));
        $t->true(!str_contains($visibleText, 'Direct widget parent without Kids label'));
    },
];
