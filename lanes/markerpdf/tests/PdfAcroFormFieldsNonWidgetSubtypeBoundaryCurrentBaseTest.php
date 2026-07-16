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

$acroFormNonWidgetSubtypeBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm non-widget subtype boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 20 0 R 22 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 12 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /TU (Article title label) /TM (article-title-export) /V (Accepted article title) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Link /T (link.title.decoy) /V (Link field value must not surface) /A << /S /URI /URI (https://example.test/leak) >> >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Text /T (note.title.decoy) /V (Note annotation value must not surface) /Contents (Sticky note payload must not surface) >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /FreeText /T (freetext.title.decoy) /Kids [16 0 R] /V (FreeText annotation value must not surface) >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx /T (nested.annotation.child.decoy) /V (Nested annotation child value must not surface) >>\nendobj\n"
        . "20 0 obj\n<< /Subtype /Link /T (page.link.decoy) /A << /S /URI /URI (https://example.test/page-leak) >> >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /FT /Tx /T (inline.widget) /V (Inline widget value) /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects non-widget subtype dictionaries listed as AcroForm fields before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormNonWidgetSubtypeBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormNonWidgetSubtypeBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.title', 'inline.widget'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $article = $fields['article.title'];
        $t->same(6, $article['object']);
        $t->same('article.title', $article['name']);
        $t->same('Article title label', $article['alternate_name']);
        $t->same('article-title-export', $article['mapping_name']);
        $t->same('text', $article['field_type_label']);
        $t->same('Accepted article title', $article['value']);
        $t->same(['DA'], $article['field_hierarchy']['inherited_attributes']);
        $t->same(['FT', 'V'], $article['field_hierarchy']['local_attributes']);
        $t->same('field_terminal', $article['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($article['widgets'], 'object'));
        $t->same([0], array_column($article['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($article['widgets'], 'referenced_from_page_annots'));

        $inline = $fields['inline.widget'];
        $t->same(22, $inline['object']);
        $t->same('inline.widget', $inline['name']);
        $t->same('text', $inline['field_type_label']);
        $t->same('Inline widget value', $inline['value']);
        $t->same([22], array_column($inline['widgets'], 'object'));
        $t->same([2], array_column($inline['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($inline['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'link.title.decoy',
            'Link field value must not surface',
            'note.title.decoy',
            'Note annotation value must not surface',
            'Sticky note payload must not surface',
            'freetext.title.decoy',
            'FreeText annotation value must not surface',
            'nested.annotation.child.decoy',
            'Nested annotation child value must not surface',
            'page.link.decoy',
            'https://example.test/leak',
            'https://example.test/page-leak',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->same('Visible AcroForm non-widget subtype boundary body', $visibleText);
        $t->true(!str_contains($visibleText, 'Accepted article title'));
        $t->true(!str_contains($visibleText, 'Inline widget value'));
    },
];
