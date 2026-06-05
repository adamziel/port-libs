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

$directPageWidgetBoundaryPdf = static function (): string {
    $pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible direct page widget boundary page one body) Tj ET';
    $pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible direct page widget boundary page two body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 40 0 R /Annots [\n"
        . "<< /Subtype /Widget /FT /Tx /T (direct.inline) /TU (Direct inline label) /TM (direct-inline-export) /V (Direct inline value) /DV (Direct inline default) /Rect [72 640 320 664] /P 3 0 R /F 4 /DA (/Helv 8 Tf 0 0 1 rg) >>\n"
        . "<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\n"
        . "<< /Subtype /Widget /Parent 12 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\n"
        . "<< /Subtype /Widget /FT /Tx /T (wrongpage.direct) /V (Wrong page direct value must not surface) /Rect [72 520 320 544] /P 4 0 R /F 4 >>\n"
        . "<< /Subtype /Text /FT /Tx /T (text.annotation.decoy) /V (Text annotation decoy value) /Rect [72 480 320 504] /P 3 0 R /F 4 >>\n"
        . "] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 41 0 R /Annots [\n"
        . "<< /Subtype /Widget /Parent 18 0 R /Rect [72 640 280 664] /P 4 0 R /F 4 >>\n"
        . "] >>\nendobj\n"
        . "5 0 obj\n<< /Fields [] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (parent.direct) /TU (Direct parent label) /TM (direct-parent-export) /V (Direct parent value) /DV (Direct parent default) /MaxLen 48 >>\nendobj\n"
        . "12 0 obj\n<< /FT /Tx /T (emptykids.direct) /TU (Explicit empty Kids direct label) /TM (emptykids-direct-export) /V (Explicit empty Kids direct value) /Kids [] >>\nendobj\n"
        . "18 0 obj\n<< /FT /Ch /T (second.direct) /TU (Second direct status label) /TM (second-direct-export) /V (published) /Opt [(draft) (published)] >>\nendobj\n"
        . "40 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
        . "41 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'materializes direct page Widget annotations before AcroForm field repair' => static function (
        TestRunner $t
    ) use ($directPageWidgetBoundaryPdf, $fieldsByName): void {
        $pdf = $directPageWidgetBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['direct.inline', 'parent.direct', 'second.direct'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $inline = $fields['direct.inline'];
        $t->same('text', $inline['field_type_label']);
        $t->same('Direct inline value', $inline['value']);
        $t->same('Direct inline default', $inline['default_value']);
        $t->same('Direct inline label', $inline['alternate_name']);
        $t->same('direct-inline-export', $inline['mapping_name']);
        $t->same('field_terminal', $inline['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([0], array_column($inline['widgets'], 'page_index'));
        $t->same([3], array_column($inline['widgets'], 'page_object'));
        $t->same([0], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $inline['widgets'][0]['rect']);
        $t->true(is_int($inline['object']) && $inline['object'] > 41);
        $t->same($inline['object'], $inline['widgets'][0]['object']);

        $parent = $fields['parent.direct'];
        $t->same(10, $parent['object']);
        $t->same('text', $parent['field_type_label']);
        $t->same('Direct parent value', $parent['value']);
        $t->same('Direct parent default', $parent['default_value']);
        $t->same('Direct parent label', $parent['alternate_name']);
        $t->same('direct-parent-export', $parent['mapping_name']);
        $t->same(48, $parent['max_length']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $parent['field_hierarchy']['local_attributes']);
        $t->same(['DA'], $parent['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $parent['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([0], array_column($parent['widgets'], 'page_index'));
        $t->same([3], array_column($parent['widgets'], 'page_object'));
        $t->same([1], array_column($parent['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($parent['widgets'], 'referenced_from_page_annots'));

        $second = $fields['second.direct'];
        $t->same(18, $second['object']);
        $t->same('choice', $second['field_type_label']);
        $t->same('published', $second['value']);
        $t->same([
            ['export' => 'draft', 'label' => 'draft'],
            ['export' => 'published', 'label' => 'published'],
        ], $second['options']);
        $t->same([1], array_column($second['widgets'], 'page_index'));
        $t->same([4], array_column($second['widgets'], 'page_object'));
        $t->same([0], array_column($second['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($second['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'wrongpage.direct',
            'emptykids.direct',
            'text.annotation.decoy',
            'Wrong page direct value must not surface',
            'Explicit empty Kids direct value',
            'Text annotation decoy value',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible direct page widget boundary page one body'));
        $t->true(str_contains($visibleText, 'Visible direct page widget boundary page two body'));
        $t->true(!str_contains($visibleText, 'Direct inline value'));
        $t->true(!str_contains($visibleText, 'Direct parent value'));
        $t->true(!str_contains($visibleText, 'published'));
    },
];
