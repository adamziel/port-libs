<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$fieldsByName = static function (array $fields): array {
    $indexed = [];
    foreach ($fields as $field) {
        $indexed[$field['name']] = $field;
    }

    return $indexed;
};

$commentSplitReferencePdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm comment reference body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 % catalog AcroForm reference comment\n0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 % listed widget comment\n0 R 12 % page-only widget comment\n0 R 14 % inline widget comment\n0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields 20 % indirect Fields array comment\n0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT 35 % indirect field type comment\n0 R /T 30 % indirect field name comment\n0 R /TU 31 % indirect alternate label comment\n0 R /TM 32 % indirect mapping name comment\n0 R /V 33 % indirect value comment\n0 R /MaxLen 34 % indirect max length comment\n0 R /Kids 21 % indirect Kids array comment\n0 R >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 % widget Parent reference comment\n0 R /Rect [40 % rect llx comment\n0 R 41 % rect lly comment\n0 R 42 % rect urx comment\n0 R 43 % rect ury comment\n0 R] /P 3 0 R /F 44 % annotation flags comment\n0 R >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (settings) /V (publish) /Opt [(draft) (publish)] /Kids [12 % omitted parent widget comment\n0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 % page widget parent comment\n0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.commentref) /V (Inline comment reference value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n[6 % comment-split Fields entry\n0 R (99 0 R stays literal)]\nendobj\n"
        . "21 0 obj\n[8 % comment-split Kids entry\n0 R]\nendobj\n"
        . "30 0 obj\n(article.commentref)\nendobj\n"
        . "31 0 obj\n(Comment reference label)\nendobj\n"
        . "32 0 obj\n(article.commentref.export)\nendobj\n"
        . "33 0 obj\n(Comment reference value)\nendobj\n"
        . "34 0 obj\n40\nendobj\n"
        . "35 0 obj\n/T#78\nendobj\n"
        . "40 0 obj\n300\nendobj\n"
        . "41 0 obj\n664\nendobj\n"
        . "42 0 obj\n72\nendobj\n"
        . "43 0 obj\n640\nendobj\n"
        . "44 0 obj\n4\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (decoy.literal.reference) /V (Decoy literal value) >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF comments as whitespace inside AcroForm indirect references' => static function (
        TestRunner $t
    ) use ($commentSplitReferencePdf, $fieldsByName): void {
        $pdf = $commentSplitReferencePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.commentref', 'settings', 'inline.commentref'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.commentref'];
        $t->same(6, $article['object']);
        $t->same('Tx', $article['field_type']);
        $t->same('text', $article['field_type_label']);
        $t->same('Comment reference label', $article['alternate_name']);
        $t->same('article.commentref.export', $article['mapping_name']);
        $t->same('Comment reference value', $article['value']);
        $t->same(40, $article['max_length']);
        $t->same([8], array_column($article['widgets'], 'object'));
        $t->same([0], array_column($article['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($article['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 300.0, 664.0], $article['widgets'][0]['rect']);
        $t->same(4, $article['widgets'][0]['annotation_flags']);
        $t->same('visible', $article['widgets'][0]['annotation_visibility']);
        $t->same('field_terminal', $article['value_state']['hierarchy_boundary']['current_value_source']);

        $settings = $fields['settings'];
        $t->same(10, $settings['object']);
        $t->same('choice', $settings['field_type_label']);
        $t->same('publish', $settings['value']);
        $t->same([12], array_column($settings['widgets'], 'object'));
        $t->same([1], array_column($settings['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($settings['widgets'], 'referenced_from_page_annots'));

        $inline = $fields['inline.commentref'];
        $t->same(14, $inline['object']);
        $t->same('Inline comment reference value', $inline['value']);
        $t->same([14], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'decoy.literal.reference'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Decoy literal value'));
        $t->same('Visible AcroForm comment reference body', $visibleText);
        $t->true(!str_contains($visibleText, 'Comment reference value'));
        $t->true(!str_contains($visibleText, 'Inline comment reference value'));
        $t->true(!str_contains($visibleText, 'Decoy literal value'));
    },
];
