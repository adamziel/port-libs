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

$acroFormChildBranchBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm child branch boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /TM (profile-root-map) /V (parent@example.test) /DV (default@example.test) /MaxLen 64 /Kids [12 0 R 16 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Editor email label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Parent 10 0 R /T (secret) /TU (Sibling secret label) /TM (profile.secret.export) /V (sibling-secret@example.test) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'bounds malformed child AcroForm Fields entries to the referenced branch' => static function (
        TestRunner $t
    ) use ($acroFormChildBranchBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormChildBranchBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(12, $email['object']);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Editor email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('default@example.test', $email['default_value']);
        $t->same([10, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile parent label', 'Editor email label'], array_column($email['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['profile-root-map', 'profile.email.export'], array_column($email['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same(64, $email['max_length']);
        $t->same(10, $email['max_length_review']['max_length_source_object']);
        $t->same(true, $email['max_length_review']['max_length_inherited']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $t->true(!isset($fields['profile.secret']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'sibling-secret@example.test'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'profile.secret.export'));
        $t->true(str_contains($visibleText, 'Visible AcroForm child branch boundary body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'parent@example.test'));
        $t->true(!str_contains($visibleText, 'sibling-secret@example.test'));
    },
];
