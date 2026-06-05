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

$acroFormQuaddingBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm quadding boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 13 0 R 17 0 R 21 0 R 25 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R 18 0 R 22 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Q 1 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (site.title) /V (Site title value) /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article) /V (Parent alignment value must stay review only) /Q 2 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (summary) /V (Summary value) /Kids [13 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "14 0 obj\n<< /FT /Tx /T (article.caption) /Q 30 1 R /V (Caption value) /Kids [17 0 R] >>\nendobj\n"
        . "17 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "18 0 obj\n<< /FT /Tx /T (article.invalid) /Q 9 /V (Invalid alignment value) /Kids [21 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Subtype /Widget /Parent 18 0 R /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
        . "22 0 obj\n<< /FT /Tx /T (article.unresolved) /Q 31 0 R /V (Unresolved alignment value) /Kids [25 0 R] >>\nendobj\n"
        . "25 0 obj\n<< /Subtype /Widget /Parent 22 0 R /Rect [72 480 320 504] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n0\nendobj\n"
        . "30 0 obj\n2\nendobj\n"
        . "31 1 obj\n2\nendobj\n"
        . "%%EOF";
};

return [
    'resolves AcroForm Q quadding inheritance and generation boundaries before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormQuaddingBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormQuaddingBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['site.title', 'article.summary', 'article.caption', 'article.invalid', 'article.unresolved'], array_keys($fields));
        $t->same(5, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $titleReview = $fields['site.title']['quadding_review'] ?? [];
        $t->same('acroform_field_quadding_boundary', $titleReview['source'] ?? null);
        $t->same(6, $titleReview['field_object'] ?? null);
        $t->same(1, $fields['site.title']['quadding'] ?? null);
        $t->same('center', $fields['site.title']['text_alignment'] ?? null);
        $t->same(1, $titleReview['quadding'] ?? null);
        $t->same('center', $titleReview['quadding_label'] ?? null);
        $t->same(true, $titleReview['quadding_valid'] ?? null);
        $t->same(true, $titleReview['quadding_resolved'] ?? null);
        $t->same('acroform', $titleReview['quadding_source'] ?? null);
        $t->same(null, $titleReview['quadding_source_object'] ?? null);
        $t->same(true, $titleReview['quadding_inherited'] ?? null);
        $t->same('acroform_default', $titleReview['quadding_source_boundary'] ?? null);
        $t->same(['DA', 'Q'], $fields['site.title']['field_hierarchy']['inherited_attributes']);

        $summaryReview = $fields['article.summary']['quadding_review'] ?? [];
        $t->same(12, $summaryReview['field_object'] ?? null);
        $t->same(2, $summaryReview['quadding'] ?? null);
        $t->same('right', $summaryReview['quadding_label'] ?? null);
        $t->same(true, $summaryReview['quadding_valid'] ?? null);
        $t->same(true, $summaryReview['quadding_inherited'] ?? null);
        $t->same(10, $summaryReview['quadding_source_object'] ?? null);
        $t->same('field_hierarchy_inherited', $summaryReview['quadding_source_boundary'] ?? null);
        $t->same(['FT', 'DA', 'Q'], $fields['article.summary']['field_hierarchy']['inherited_attributes']);
        $t->same('field_terminal_override', $fields['article.summary']['value_state']['hierarchy_boundary']['current_value_source']);

        $captionReview = $fields['article.caption']['quadding_review'] ?? [];
        $t->same(14, $captionReview['field_object'] ?? null);
        $t->same(0, $fields['article.caption']['quadding'] ?? null);
        $t->same('left', $fields['article.caption']['text_alignment'] ?? null);
        $t->same(0, $captionReview['quadding'] ?? null);
        $t->same('left', $captionReview['quadding_label'] ?? null);
        $t->same(true, $captionReview['quadding_valid'] ?? null);
        $t->same(true, $captionReview['quadding_resolved'] ?? null);
        $t->same(false, $captionReview['quadding_inherited'] ?? null);
        $t->same(14, $captionReview['quadding_source_object'] ?? null);
        $t->same('field_terminal', $captionReview['quadding_source_boundary'] ?? null);
        $t->same(['FT', 'V', 'Q'], $fields['article.caption']['field_hierarchy']['local_attributes']);

        $invalidReview = $fields['article.invalid']['quadding_review'] ?? [];
        $t->same(18, $invalidReview['field_object'] ?? null);
        $t->same(9, $invalidReview['quadding'] ?? null);
        $t->same('unknown', $invalidReview['quadding_label'] ?? null);
        $t->same(false, $invalidReview['quadding_valid'] ?? null);
        $t->same(true, $invalidReview['quadding_resolved'] ?? null);
        $t->same(false, $invalidReview['quadding_inherited'] ?? null);
        $t->same('field_terminal', $invalidReview['quadding_source_boundary'] ?? null);

        $unresolvedReview = $fields['article.unresolved']['quadding_review'] ?? [];
        $t->same(22, $unresolvedReview['field_object'] ?? null);
        $t->same(null, $unresolvedReview['quadding'] ?? null);
        $t->same(null, $unresolvedReview['quadding_label'] ?? null);
        $t->same(false, $unresolvedReview['quadding_valid'] ?? null);
        $t->same(false, $unresolvedReview['quadding_resolved'] ?? null);
        $t->same(false, $unresolvedReview['quadding_inherited'] ?? null);
        $t->same('field_terminal', $unresolvedReview['quadding_source_boundary'] ?? null);

        foreach ($fields as $field) {
            $review = $field['quadding_review'] ?? [];
            $t->same(true, $review['review_only'] ?? null);
            $t->same(false, $review['quadding_used_for_visible_text'] ?? null);
            $t->same(false, $review['appearance_alignment_used_for_import'] ?? null);
            $t->same(false, $review['executes_form_actions'] ?? null);
            $t->same(false, $review['executes_javascript'] ?? null);
            $t->same(false, $review['executes_appearance_streams'] ?? null);
            $t->same(false, $review['renders_appearances'] ?? null);
        }

        foreach ([
            'Site title value',
            'Summary value',
            'Caption value',
            'Invalid alignment value',
            'Unresolved alignment value',
            'Parent alignment value must stay review only',
        ] as $formText) {
            $t->true(!str_contains($visibleText, $formText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm quadding boundary body'));
        $t->true(is_string($encoded) && str_contains($encoded, '"quadding_label":"left"'));
        $t->true(is_string($encoded) && str_contains($encoded, '"quadding_resolved":false'));
    },
];
