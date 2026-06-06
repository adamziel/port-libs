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

$acroFormNonFieldParentBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm non-field parent boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 1 /TU (Page tree label must not surface) /TM (page.tree.map) /V (Page tree value must not surface) /DV (Page tree default must not surface) /MaxLen 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 34 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 32 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /Parent 2 0 R /FT /Tx /T (article.title) /V (Current article title) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /FT /Tx /DV (Anonymous inherited draft) /MaxLen 40 /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Parent 30 0 R /T (anonymous.child) /V (Anonymous child value) /Kids [34 0 R] >>\nendobj\n"
        . "34 0 obj\n<< /Subtype /Widget /Parent 32 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects page tree dictionaries as AcroForm field parents while preserving anonymous field grouping nodes' => static function (
        TestRunner $t
    ) use ($acroFormNonFieldParentBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormNonFieldParentBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.title', 'anonymous.child'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.title'];
        $t->same(6, $article['object']);
        $t->same('article.title', $article['name']);
        $t->same('text', $article['field_type_label']);
        $t->same('Current article title', $article['value']);
        $t->same(null, $article['default_value']);
        $t->same(null, $article['max_length']);
        $t->same([6], array_column($article['field_hierarchy']['path'], 'object'));
        $t->same(['article.title'], array_column($article['field_hierarchy']['path'], 'partial_name'));
        $t->same([], $article['field_hierarchy']['ancestor_objects']);
        $t->same(['FT', 'V'], $article['field_hierarchy']['local_attributes']);
        $t->same([], $article['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal', $article['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $article['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(false, $article['value_state']['hierarchy_boundary']['max_length_inherited']);
        $t->same([8], array_column($article['widgets'], 'object'));
        $t->same([0], array_column($article['widgets'], 'page_index'));
        $t->same([3], array_column($article['widgets'], 'page_object'));
        $t->same([0], array_column($article['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($article['widgets'], 'referenced_from_page_annots'));

        $anonymous = $fields['anonymous.child'];
        $t->same(32, $anonymous['object']);
        $t->same('anonymous.child', $anonymous['name']);
        $t->same('text', $anonymous['field_type_label']);
        $t->same('Anonymous child value', $anonymous['value']);
        $t->same('Anonymous inherited draft', $anonymous['default_value']);
        $t->same(40, $anonymous['max_length']);
        $t->same([30, 32], array_column($anonymous['field_hierarchy']['path'], 'object'));
        $t->same([null, 'anonymous.child'], array_column($anonymous['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'MaxLen'], $anonymous['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $anonymous['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $anonymous['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $anonymous['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(true, $anonymous['value_state']['hierarchy_boundary']['max_length_inherited']);
        $t->same([34], array_column($anonymous['widgets'], 'object'));
        $t->same([1], array_column($anonymous['widgets'], 'page_annotation_index'));

        foreach ([
            'Page tree label must not surface',
            'page.tree.map',
            'Page tree value must not surface',
            'Page tree default must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm non-field parent boundary body'));
        $t->true(!str_contains($visibleText, 'Current article title'));
        $t->true(!str_contains($visibleText, 'Anonymous child value'));
    },
];
