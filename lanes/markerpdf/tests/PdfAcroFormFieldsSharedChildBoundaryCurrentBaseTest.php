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

$sharedParentlessChildPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm shared child boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (billing) /TU (Billing group label) /TM (billing.group.map) /V (Billing parent value) /DV (Billing default value) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (shipping) /TU (Shipping group label) /TM (shipping.group.map) /V (Shipping parent value) /DV (Shipping default value) /MaxLen 48 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /T (email) /TU (Shared child label must not surface) /TM (shared.email.export) /V (shared-child@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

$singleParentlessChildPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm single child boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile group label) /TM (profile.group.map) /V (Profile parent value) /DV (Profile default value) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /T (email) /TU (Profile email label) /TM (profile.email.export) /V (profile@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects parentless AcroForm child dictionaries shared by multiple field parents' => static function (
        TestRunner $t
    ) use ($sharedParentlessChildPdf, $fieldsByName): void {
        $pdf = $sharedParentlessChildPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['billing', 'shipping'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $billing = $fields['billing'];
        $t->same(6, $billing['object']);
        $t->same('text', $billing['field_type_label']);
        $t->same('Billing parent value', $billing['value']);
        $t->same('Billing default value', $billing['default_value']);
        $t->same(64, $billing['max_length']);
        $t->same([], $billing['widgets']);
        $t->same([], $billing['field_hierarchy']['ancestor_objects']);
        $t->same('field_terminal', $billing['value_state']['hierarchy_boundary']['current_value_source']);

        $shipping = $fields['shipping'];
        $t->same(10, $shipping['object']);
        $t->same('text', $shipping['field_type_label']);
        $t->same('Shipping parent value', $shipping['value']);
        $t->same('Shipping default value', $shipping['default_value']);
        $t->same(48, $shipping['max_length']);
        $t->same([], $shipping['widgets']);
        $t->same([], $shipping['field_hierarchy']['ancestor_objects']);
        $t->same('field_terminal', $shipping['value_state']['hierarchy_boundary']['current_value_source']);

        foreach ([
            'billing.email',
            'shipping.email',
            'Shared child label must not surface',
            'shared.email.export',
            'shared-child@example.test',
        ] as $sharedChildLeak) {
            $t->same(false, isset($fields[$sharedChildLeak]));
            $t->same(false, str_contains($encoded, $sharedChildLeak));
            $t->same(false, str_contains($visibleText, $sharedChildLeak));
        }

        $t->same('Visible AcroForm shared child boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'Billing parent value'));
        $t->same(false, str_contains($visibleText, 'Shipping parent value'));
    },
    'keeps a single parentless AcroForm child branch usable for compact field trees' => static function (
        TestRunner $t
    ) use ($singleParentlessChildPdf, $fieldsByName): void {
        $pdf = $singleParentlessChildPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(12, $email['object']);
        $t->same('email', $email['partial_name']);
        $t->same('Profile email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('profile@example.test', $email['value']);
        $t->same('Profile default value', $email['default_value']);
        $t->same(64, $email['max_length']);
        $t->same([6, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $email['widgets'][0]['rect']);

        $t->same('Visible AcroForm single child boundary body', $visibleText);
        $t->same(false, str_contains($visibleText, 'profile@example.test'));
        $t->same(false, str_contains($visibleText, 'Profile default value'));
    },
];
