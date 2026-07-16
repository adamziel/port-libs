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

$directWidgetCanonicalBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct widget canonical body) Tj ET';
    $pageWidget = '<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>';
    $parentKidWidget = '<< /F 4 /P 3 0 R /Rect [72 640 320 664] /Par#65nt 10 0 R /Sub#74ype /Widget >>';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [{$pageWidget}] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (canonical.widget) /TU (Canonical widget label) /TM (canonical-widget-export) /V (Canonical widget value) /DV (Canonical widget default) /MaxLen 58 /Kids [{$parentKidWidget}] >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.canonical.decoy) /V (Detached canonical decoy value must not surface) /Kids [<< /F 4 /Rect [72 600 320 624] /Subtype /Widget /Parent 20 0 R >>] >>\nendobj\n"
        . "%%EOF";
};

return [
    'matches direct AcroForm Widget Kids dictionaries by decoded unordered dictionary content' => static function (
        TestRunner $t
    ) use ($directWidgetCanonicalBoundaryPdf, $fieldsByName): void {
        $pdf = $directWidgetCanonicalBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['canonical.widget'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['canonical.widget'];
        $t->same(10, $field['object']);
        $t->same('canonical.widget', $field['name']);
        $t->same('Canonical widget label', $field['alternate_name']);
        $t->same('canonical-widget-export', $field['mapping_name']);
        $t->same('text', $field['field_type_label']);
        $t->same('Canonical widget value', $field['value']);
        $t->same('Canonical widget default', $field['default_value']);
        $t->same(58, $field['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $field['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $field['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $field['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([0], array_column($field['widgets'], 'page_index'));
        $t->same([3], array_column($field['widgets'], 'page_object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $field['widgets'][0]['rect']);

        foreach ([
            'detached.canonical.decoy',
            'Detached canonical decoy value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm direct widget canonical body'));
        $t->true(!str_contains($visibleText, 'Canonical widget value'));
        $t->true(!str_contains($visibleText, 'Canonical widget default'));
        $t->same(false, $field['field_name_review']['field_value_used_as_visible_text']);
        $t->same(false, $field['field_hierarchy']['executes_form_actions']);
        $t->same(false, $field['field_hierarchy']['executes_javascript']);
    },
];
