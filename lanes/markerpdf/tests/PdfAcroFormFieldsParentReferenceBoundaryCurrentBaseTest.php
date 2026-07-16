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

$mismatchedChildFieldParentPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm child Parent reference body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile label) /V (parent@example.test) /DV (draft@example.test) /MaxLen 80 /Kids [8 0 R 12 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Parent 6 0 R /T (email) /TU (Email label) /TM (profile.email.export) /V (editor@example.test) /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Subtype /Widget /Parent 8 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Parent 20 0 R /T (spoof) /TU (Spoof label must not surface) /TM (profile.spoof.export) /V (spoof child value must not surface) /Kids [14 0 R] >>\nendobj\n"
        . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached) /V (detached parent value must not surface) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

$mismatchedChildWidgetParentPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm widget Parent reference body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Reviewed title value) /Kids [8 0 R 12 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /FT /Tx /T (detached.widget.parent) /V (detached widget parent value must not surface) /Kids [22 0 R] >>\nendobj\n"
        . "22 0 obj\n<< /Subtype /Widget /Parent 20 0 R /Rect [72 560 320 584] /F 4 >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects AcroForm child field dictionaries whose explicit Parent points outside the listed Kids branch' => static function (
        TestRunner $t
    ) use ($mismatchedChildFieldParentPdf, $fieldsByName): void {
        $pdf = $mismatchedChildFieldParentPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['profile.email'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['profile.email'];
        $t->same(8, $email['object']);
        $t->same('profile.email', $email['name']);
        $t->same('email', $email['partial_name']);
        $t->same('Email label', $email['alternate_name']);
        $t->same('profile.email.export', $email['mapping_name']);
        $t->same('text', $email['field_type_label']);
        $t->same('editor@example.test', $email['value']);
        $t->same('draft@example.test', $email['default_value']);
        $t->same([6, 8], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['profile', 'email'], array_column($email['field_hierarchy']['path'], 'partial_name'));
        $t->same(['FT', 'DV', 'DA', 'MaxLen'], $email['field_hierarchy']['inherited_attributes']);
        $t->same(['V'], $email['field_hierarchy']['local_value_attributes']);
        $t->same([10], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'profile.spoof',
            'Spoof label must not surface',
            'profile.spoof.export',
            'spoof child value must not surface',
            'detached parent value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm child Parent reference body'));
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'parent@example.test'));
    },
    'rejects AcroForm child widget dictionaries whose explicit Parent points outside the field Kids branch' => static function (
        TestRunner $t
    ) use ($mismatchedChildWidgetParentPdf, $fieldsByName): void {
        $pdf = $mismatchedChildWidgetParentPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.title'], array_keys($fields));
        $t->same(1, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $field = $fields['article.title'];
        $t->same(6, $field['object']);
        $t->same('Reviewed title value', $field['value']);
        $t->same([8], array_column($field['widgets'], 'object'));
        $t->same([0], array_column($field['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($field['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'detached.widget.parent',
            'detached widget parent value must not surface',
        ] as $decoyText) {
            $t->true(is_string($encoded) && !str_contains($encoded, $decoyText));
            $t->true(!str_contains($visibleText, $decoyText));
        }

        $t->true(str_contains($visibleText, 'Visible AcroForm widget Parent reference body'));
        $t->true(!str_contains($visibleText, 'Reviewed title value'));
    },
];
