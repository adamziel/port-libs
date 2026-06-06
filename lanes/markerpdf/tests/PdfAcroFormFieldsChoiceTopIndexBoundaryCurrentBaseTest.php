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

$acroFormChoiceTopIndexBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice top index boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 22 0 R 32 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R 20 0 R 30 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (workflow) /Ff 2097152 /V [(publish) (archive)] /I [2 3] /Opt [[(draft) (Draft)] [(review) (Review)] [(publish) (Published)] [(archive) (Archived)]] /TI 35 1 R /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (status) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Ch /T (workflow.invalid_top) /V (draft) /Opt [(draft) (review)] /TI 9 /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /FT /Ch /T (workflow.unresolved_top) /V (review) /Opt [(draft) (review)] /TI 36 0 R /Kids [32 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "35 1 obj\n2\nendobj\n"
        . "35 0 obj\n0\nendobj\n"
        . "36 1 obj\n1\nendobj\n"
        . "%%EOF";
};

return [
    'reviews AcroForm choice TI top index inheritance before WordPress field import' => static function (
        TestRunner $t
    ) use ($acroFormChoiceTopIndexBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormChoiceTopIndexBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['workflow.status', 'workflow.invalid_top', 'workflow.unresolved_top'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $status = $fields['workflow.status'];
        $statusReview = $status['choice_top_index_review'] ?? [];
        $t->same('choice', $status['field_type_label']);
        $t->same(['multi_select'], $status['flag_names']);
        $t->same(['publish', 'archive'], $status['value_state']['choice_values']);
        $t->same([2, 3], $status['value_state']['selected_indices']);
        $t->same('field', $status['value_state']['selected_indices_source']);
        $t->same(2, $status['value_state']['choice_top_index']);
        $t->same('Published', $status['value_state']['choice_top_option_label']);
        $t->same(true, $status['value_state']['choice_top_index_valid']);
        $t->same(true, $status['value_state']['choice_top_index_inherited']);
        $t->same('acroform_choice_top_index_boundary', $statusReview['source'] ?? null);
        $t->same(12, $statusReview['field_object'] ?? null);
        $t->same(2, $statusReview['top_index'] ?? null);
        $t->same('Published', $statusReview['top_option_label'] ?? null);
        $t->same('publish', $statusReview['top_option_export'] ?? null);
        $t->same(true, $statusReview['top_index_valid'] ?? null);
        $t->same(true, $statusReview['top_index_resolved'] ?? null);
        $t->same(true, $statusReview['top_index_inherited'] ?? null);
        $t->same(10, $statusReview['top_index_source_object'] ?? null);
        $t->same('field_hierarchy_inherited', $statusReview['top_index_source_boundary'] ?? null);
        $t->same(false, $statusReview['top_index_used_for_visible_text'] ?? null);
        $t->same(false, $statusReview['appearance_scroll_position_used_for_import'] ?? null);
        $t->same(['FT', 'Ff', 'V', 'DA', 'Opt', 'I', 'TI'], $status['field_hierarchy']['inherited_attributes']);

        $invalid = $fields['workflow.invalid_top'];
        $invalidReview = $invalid['choice_top_index_review'] ?? [];
        $t->same(9, $invalid['value_state']['choice_top_index']);
        $t->same(false, $invalid['value_state']['choice_top_index_valid']);
        $t->same(null, $invalid['value_state']['choice_top_option_label']);
        $t->same(false, $invalidReview['top_index_inherited'] ?? null);
        $t->same('field_terminal', $invalidReview['top_index_source_boundary'] ?? null);
        $t->same(true, $invalidReview['top_index_resolved'] ?? null);
        $t->same(false, $invalidReview['top_index_valid'] ?? null);

        $unresolved = $fields['workflow.unresolved_top'];
        $unresolvedReview = $unresolved['choice_top_index_review'] ?? [];
        $t->same(null, $unresolved['value_state']['choice_top_index']);
        $t->same(false, $unresolved['value_state']['choice_top_index_valid']);
        $t->same(null, $unresolved['value_state']['choice_top_option_label']);
        $t->same(false, $unresolvedReview['top_index_resolved'] ?? null);
        $t->same(false, $unresolvedReview['top_index_valid'] ?? null);
        $t->same(30, $unresolvedReview['top_index_source_object'] ?? null);

        foreach ($fields as $field) {
            $review = $field['choice_top_index_review'] ?? [];
            $t->same(true, $review['review_only'] ?? null);
            $t->same(false, $review['top_index_used_for_import'] ?? null);
            $t->same(false, $review['executes_form_actions'] ?? null);
            $t->same(false, $review['executes_javascript'] ?? null);
            $t->same(false, $review['executes_appearance_streams'] ?? null);
            $t->same(false, $review['renders_appearances'] ?? null);
        }

        foreach ([
            'publish',
            'archive',
            'Draft',
            'Review',
            'Published',
            'Archived',
        ] as $formText) {
            $t->true(!str_contains($visibleText, $formText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm choice top index boundary body'));
        $t->true(is_string($encoded) && str_contains($encoded, '"choice_top_index":2'));
        $t->true(is_string($encoded) && str_contains($encoded, '"choice_top_index_valid":false'));
    },
];
