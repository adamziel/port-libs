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

$acroFormPageWidgetSiblingBranchPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm sibling branch repair body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [12 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (profile) /TU (Profile group label) /TM (profile.group.map) /V (parent@example.test) /DV (draft@example.test) /MaxLen 64 /Kids [12 0 R 16 0 R 20 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Parent 10 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /Parent 10 0 R /T (status) /TU (Status label) /TM (profile.status.export) /V (publish) /Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /Parent 10 0 R /T (secret) /TU (Secret label must not surface) /TM (profile.secret.export) /V (private secret value must not surface) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

$acroFormDirectWidgetUnownedParentPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct widget ownership body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [8 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (unowned.direct.parent) /V (Unowned direct widget value must not surface) /Kids [10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'promotes page owned AcroForm sibling widget branches without importing the parent root' => static function (
        TestRunner $t
    ) use ($acroFormPageWidgetSiblingBranchPdf, $fieldsByName): void {
        $pdf = $acroFormPageWidgetSiblingBranchPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email', 'profile.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(12, $email['object']);
        $t->same('editor@example.test', $email['value']);
        $t->same('draft@example.test', $email['default_value']);
        $t->same([10, 12], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same([14], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));
        $t->same('field_terminal_override', $email['value_state']['hierarchy_boundary']['current_value_source']);

        $status = $fields['profile.status'];
        $t->same(16, $status['object']);
        $t->same('publish', $status['value']);
        $t->same('draft@example.test', $status['default_value']);
        $t->same([10, 16], array_column($status['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'status'], array_column($status['field_hierarchy']['path'], 'partial_name'));
        $t->same(['Profile group label', 'Status label'], array_column($status['field_hierarchy']['path'], 'alternate_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $status['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $status['field_hierarchy']['local_value_attributes']);
        $t->same([18], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($status['widgets'], 'referenced_from_page_annots'));

        $t->true(!isset($fields['profile.secret']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'private secret value must not surface'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'profile.secret.export'));
        $t->true(str_contains($visibleText, 'Visible AcroForm sibling branch repair body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'publish'));
        $t->true(!str_contains($visibleText, 'private secret value must not surface'));
    },
    'rejects direct widget AcroForm Fields entries when Parent Kids do not own the widget' => static function (
        TestRunner $t
    ) use ($acroFormDirectWidgetUnownedParentPdf, $fieldsByName): void {
        $pdf = $acroFormDirectWidgetUnownedParentPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same([], array_keys($fields));
        $t->same(0, count($form['fields']));
        $t->same(true, $form['need_appearances']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'unowned.direct.parent'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unowned direct widget value must not surface'));
        $t->true(str_contains($visibleText, 'Visible AcroForm direct widget ownership body'));
        $t->true(!str_contains($visibleText, 'Unowned direct widget value must not surface'));
    },
];
