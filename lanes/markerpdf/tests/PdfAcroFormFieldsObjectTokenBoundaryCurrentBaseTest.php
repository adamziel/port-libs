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

$acroFormObjectTokenBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object token boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.endobj.title) /TU (Editor label with endobj token) /TM (article.endobj.export) /V (Current value with endobj token) /DV (Default value with endobj token) /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 /MK << /CA (Caption with endobj token) >> >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (choice.endobj.status) /V (publish) /Opt [(draft endobj option) (publish)] /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (decoy.after.object.token) /V (Decoy after object token value) >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps literal endobj tokens inside AcroForm field objects before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormObjectTokenBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormObjectTokenBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.endobj.title', 'choice.endobj.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.endobj.title'];
        $t->same(6, $article['object']);
        $t->same('article.endobj.title', $article['name']);
        $t->same('article.endobj.title', $article['partial_name']);
        $t->same('Editor label with endobj token', $article['alternate_name']);
        $t->same('article.endobj.export', $article['mapping_name']);
        $t->same('text', $article['field_type_label']);
        $t->same('Current value with endobj token', $article['value']);
        $t->same('Default value with endobj token', $article['default_value']);
        $t->same(64, $article['max_length']);
        $t->same(['DA'], $article['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $article['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $article['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal', $article['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(false, $article['value_state']['hierarchy_boundary']['current_value_inherited']);
        $t->same(6, $article['value_state']['hierarchy_boundary']['current_value_source_object']);
        $t->same('Editor label with endobj token', $article['field_name_review']['wordpress_label']);
        $t->same(false, $article['field_name_review']['alternate_name_used_as_visible_text']);

        $articleWidget = $article['widgets'][0];
        $t->same(8, $articleWidget['object']);
        $t->same([72.0, 640.0, 320.0, 664.0], $articleWidget['rect']);
        $t->same(0, $articleWidget['page_index']);
        $t->same(0, $articleWidget['page_annotation_index']);
        $t->same(true, $articleWidget['referenced_from_page_annots']);
        $t->same('Caption with endobj token', $articleWidget['appearance_characteristics']['normal_caption']);

        $choice = $fields['choice.endobj.status'];
        $t->same(10, $choice['object']);
        $t->same('choice', $choice['field_type_label']);
        $t->same('publish', $choice['value']);
        $t->same([
            ['export' => 'draft endobj option', 'label' => 'draft endobj option'],
            ['export' => 'publish', 'label' => 'publish'],
        ], $choice['options']);
        $t->same([['index' => 1, 'export' => 'publish', 'label' => 'publish']], $choice['value_state']['selected_options']);
        $t->same([12], array_column($choice['widgets'], 'object'));
        $t->same([1], array_column($choice['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($choice['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'decoy.after.object.token'));
        $t->same('Visible AcroForm object token boundary body', $visibleText);
        $t->true(!str_contains($visibleText, 'Current value with endobj token'));
        $t->true(!str_contains($visibleText, 'Default value with endobj token'));
        $t->true(!str_contains($visibleText, 'Editor label with endobj token'));
        $t->true(!str_contains($visibleText, 'Caption with endobj token'));
        $t->true(!str_contains($visibleText, 'Decoy after object token value'));
    },
];
