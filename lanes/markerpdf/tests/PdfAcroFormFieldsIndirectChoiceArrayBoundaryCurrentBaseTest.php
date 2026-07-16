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

$indirectChoiceArrayBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect choice array body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 22 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 20 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Ch /T (workflow.indirect_arrays) /Ff 2097152 /V 30 1 R /DV 31 1 R /Opt 32 1 R /I 33 1 R /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Ch /T (workflow.stale_arrays) /V 40 1 R /DV 41 1 R /Opt 42 1 R /I 43 1 R /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 1 obj\n[(publish) (archive)]\nendobj\n"
        . "31 1 obj\n[(draft)]\nendobj\n"
        . "32 1 obj\n[[(draft) (Draft label)] [(review) (Review label)] [(publish) (Published label)] [(archive) (Archived label)]]\nendobj\n"
        . "33 1 obj\n[2 3]\nendobj\n"
        . "40 0 obj\n[(stale current choice must not surface)]\nendobj\n"
        . "41 0 obj\n[(stale default choice must not surface)]\nendobj\n"
        . "42 0 obj\n[[(stale option export must not surface) (Stale option label must not surface)]]\nendobj\n"
        . "43 0 obj\n[0]\nendobj\n"
        . "%%EOF";
};

return [
    'resolves generation exact indirect AcroForm choice arrays before WordPress field review' => static function (
        TestRunner $t
    ) use ($indirectChoiceArrayBoundaryPdf, $fieldsByName): void {
        $pdf = $indirectChoiceArrayBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['workflow.indirect_arrays', 'workflow.stale_arrays'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $workflow = $fields['workflow.indirect_arrays'];
        $t->same(6, $workflow['object']);
        $t->same('Ch', $workflow['field_type']);
        $t->same('choice', $workflow['field_type_label']);
        $t->same(2097152, $workflow['flags']);
        $t->same(['multi_select'], $workflow['flag_names']);
        $t->same(['publish', 'archive'], $workflow['value']);
        $t->same(['draft'], $workflow['default_value']);
        $t->same('publish, archive', $workflow['value_state']['display_value']);
        $t->same(['publish', 'archive'], $workflow['value_state']['choice_values']);
        $t->same(['draft'], $workflow['value_state']['default_choice_values']);
        $t->same([2, 3], $workflow['value_state']['selected_indices']);
        $t->same('field', $workflow['value_state']['selected_indices_source']);
        $t->same([
            ['index' => 2, 'export' => 'publish', 'label' => 'Published label'],
            ['index' => 3, 'export' => 'archive', 'label' => 'Archived label'],
        ], $workflow['value_state']['selected_options']);
        $t->same([], $workflow['value_state']['unmatched_values']);
        $t->same([
            ['export' => 'draft', 'label' => 'Draft label'],
            ['export' => 'review', 'label' => 'Review label'],
            ['export' => 'publish', 'label' => 'Published label'],
            ['export' => 'archive', 'label' => 'Archived label'],
        ], $workflow['options']);
        $t->same([8], array_column($workflow['widgets'], 'object'));
        $t->same([0], array_column($workflow['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($workflow['widgets'], 'referenced_from_page_annots'));

        $stale = $fields['workflow.stale_arrays'];
        $t->same(20, $stale['object']);
        $t->same(null, $stale['value']);
        $t->same(null, $stale['default_value']);
        $t->same([], $stale['options']);
        $t->same([], $stale['value_state']['choice_values']);
        $t->same([], $stale['value_state']['default_choice_values']);
        $t->same([], $stale['value_state']['selected_indices']);
        $t->same(null, $stale['value_state']['selected_indices_source']);
        $t->same([22], array_column($stale['widgets'], 'object'));

        foreach ([
            'stale current choice must not surface',
            'stale default choice must not surface',
            'stale option export must not surface',
            'Stale option label must not surface',
        ] as $staleText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $staleText));
            $t->true(!str_contains($visibleText, $staleText));
        }

        $t->same('Visible AcroForm indirect choice array body', $visibleText);
        $t->true(!str_contains($visibleText, 'publish'));
        $t->true(!str_contains($visibleText, 'Published label'));
        $t->true(!str_contains($visibleText, 'draft'));
    },
];
