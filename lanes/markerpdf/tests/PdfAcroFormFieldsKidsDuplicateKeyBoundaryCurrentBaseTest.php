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

$duplicateKidsKeyPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate AcroForm Kids body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile label) /TM (profile.export) /V (stale parent value) /DV (draft parent value) /Kids [30 0 R] /Kids [8 0 R 12 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Parent 6 0 R /T (email) /TU (Editor email label) /TM (profile.email.export) /V (editor@example.test) /Kids [32 0 R] /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Parent 6 0 R /T (status) /FT /Ch /TU (Status label) /TM (profile.status.export) /V (publish) /Opt [(draft) (publish)] /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n<< /Parent 6 0 R /T (secret) /TU (Stale secret label) /TM (profile.secret.export) /V (stale-secret@example.test) /Kids [34 0 R] >>\nendobj\n"
        . "32 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "34 0 obj\n<< /Subtype /Widget /Parent 30 0 R /Rect [72 520 320 544] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses the last top-level AcroForm Kids key before WordPress field review' => static function (
        TestRunner $t
    ) use ($duplicateKidsKeyPdf, $fieldsByName): void {
        $pdf = $duplicateKidsKeyPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email', 'profile.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(8, $email['object']);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Editor email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('draft parent value', $email['default_value']);
        $t->same([6, 8], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same([10], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $status = $fields['profile.status'];
        $t->same(12, $status['object']);
        $t->same('profile.status', $status['name']);
        $t->same('choice', $status['field_type_label']);
        $t->same('publish', $status['value']);
        $t->same([6, 12], array_column($status['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'status'], array_column($status['field_hierarchy']['path'], 'partial_name'));
        $t->same([14], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));
        $t->same([['export' => 'draft', 'label' => 'draft'], ['export' => 'publish', 'label' => 'publish']], $status['options']);

        foreach ([
            'profile.secret',
            'Stale secret label',
            'profile.secret.export',
            'stale-secret@example.test',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(!in_array(32, array_column($email['widgets'], 'object'), true));
        $t->same('Visible duplicate AcroForm Kids body', $visibleText);
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'draft parent value'));
        $t->true(!str_contains($visibleText, 'publish'));
    },
];
