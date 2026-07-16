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

$acroFormParentChildOverlapBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm parent child overlap boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile label) /TM (profile.export) /V (Parent current review only) /DV (Parent default review only) /MaxLen 64 /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Parent 6 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.duplicate.decoy) /V (Detached duplicate decoy) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'deduplicates overlapping AcroForm parent and child Fields entries before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($acroFormParentChildOverlapBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormParentChildOverlapBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(10, $email['object']);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('Tx', $email['field_type']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('Parent default review only', $email['default_value']);
        $t->same([6, 10], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile label', 'Email label'], array_column($email['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['profile.export', 'profile.email.export'], array_column($email['field_hierarchy']['path'], 'mapping_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same(true, $email['value_state']['hierarchy_boundary']['terminal_overrides_parent_value']);
        $t->same([12], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $t->true(!isset($fields['detached.duplicate.decoy']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Detached duplicate decoy'));
        $t->true(str_contains($visibleText, 'Visible AcroForm parent child overlap boundary body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'Parent current review only'));
        $t->true(!str_contains($visibleText, 'Parent default review only'));
        $t->true(!str_contains($visibleText, 'Detached duplicate decoy'));
    },
];
