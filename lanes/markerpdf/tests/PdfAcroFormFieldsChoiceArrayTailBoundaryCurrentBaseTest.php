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

$choiceArrayTailBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm choice array tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Ch /T (workflow.tailed) /V [(publish)] 90 0 R /DV [(draft)] 91 0 R /Opt [[(draft) (Draft label)] [(publish) (Published label)]] 92 0 R /I [1] 93 0 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /FT /Ch /T (workflow.comment) /V [(review)] % current value comment tail\n/DV [(draft)] % default value comment tail\n/Opt [[(draft) (Draft label)] [(review) (Review label)]] % option comment tail\n/I [1] % selected-index comment tail\n/Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "90 0 obj\n[(stale current choice must not surface)]\nendobj\n"
        . "91 0 obj\n[(stale default choice must not surface)]\nendobj\n"
        . "92 0 obj\n[[(stale export must not surface) (Stale label must not surface)]]\nendobj\n"
        . "93 0 obj\n[0]\nendobj\n"
        . "%%EOF";
};

return [
    'rejects direct AcroForm choice arrays with trailing operands before WordPress review' => static function (
        TestRunner $t
    ) use ($choiceArrayTailBoundaryPdf, $fieldsByName): void {
        $pdf = $choiceArrayTailBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['workflow.tailed', 'workflow.comment'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $tailed = $fields['workflow.tailed'];
        $t->same(6, $tailed['object']);
        $t->same('choice', $tailed['field_type_label']);
        $t->same(null, $tailed['value']);
        $t->same(null, $tailed['default_value']);
        $t->same([], $tailed['options']);
        $t->same(['FT'], $tailed['field_hierarchy']['local_attributes']);
        $t->same([], $tailed['field_hierarchy']['local_value_attributes']);
        $t->same(false, $tailed['value_state']['has_current_value']);
        $t->same(false, $tailed['value_state']['has_default_value']);
        $t->same(null, $tailed['value_state']['current_source']);
        $t->same(null, $tailed['value_state']['default_source']);
        $t->same(null, $tailed['value_state']['display_value']);
        $t->same([], $tailed['value_state']['choice_values']);
        $t->same([], $tailed['value_state']['default_choice_values']);
        $t->same([], $tailed['value_state']['selected_indices']);
        $t->same(null, $tailed['value_state']['selected_indices_source']);
        $t->same([], $tailed['value_state']['selected_options']);
        $t->same([], $tailed['value_state']['unmatched_values']);
        $t->same([8], array_column($tailed['widgets'], 'object'));
        $t->same([0], array_column($tailed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($tailed['widgets'], 'referenced_from_page_annots'));

        $comment = $fields['workflow.comment'];
        $t->same(16, $comment['object']);
        $t->same('choice', $comment['field_type_label']);
        $t->same(['review'], $comment['value']);
        $t->same(['draft'], $comment['default_value']);
        $t->same([
            ['export' => 'draft', 'label' => 'Draft label'],
            ['export' => 'review', 'label' => 'Review label'],
        ], $comment['options']);
        $t->same(['FT', 'V', 'DV', 'Opt', 'I'], $comment['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $comment['field_hierarchy']['local_value_attributes']);
        $t->same(true, $comment['value_state']['has_current_value']);
        $t->same(true, $comment['value_state']['has_default_value']);
        $t->same('field', $comment['value_state']['selected_indices_source']);
        $t->same([1], $comment['value_state']['selected_indices']);
        $t->same([['index' => 1, 'export' => 'review', 'label' => 'Review label']], $comment['value_state']['selected_options']);
        $t->same([], $comment['value_state']['unmatched_values']);
        $t->same([18], array_column($comment['widgets'], 'object'));
        $t->same([1], array_column($comment['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($comment['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'publish',
            'Published label',
            'stale current choice must not surface',
            'stale default choice must not surface',
            'stale export must not surface',
            'Stale label must not surface',
        ] as $tailedText) {
            $t->same(false, str_contains($encoded, $tailedText));
            $t->same(false, str_contains($visibleText, $tailedText));
        }

        $t->same('Visible AcroForm choice array tail body', $visibleText);
        $t->same(false, str_contains($visibleText, 'review'));
        $t->same(false, str_contains($visibleText, 'Review label'));
        $t->same(false, str_contains($visibleText, 'draft'));
    },
];
