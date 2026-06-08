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

$actionsByTrigger = static function (array $actions): array {
    $indexed = [];
    foreach ($actions as $action) {
        $indexed[(string) ($action['trigger'] ?? '')] = $action;
    }

    return $indexed;
};

$indirectActionFlagsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm indirect action flags boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /SigFlags 30 1 R /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.email) /V (editor@example.test) /Kids [8 0 R] /AA << /V 40 1 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article.title) /V (Reviewed title) /Kids [12 0 R] /AA << /F 42 1 R >> >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "30 0 obj\n30\nendobj\n"
        . "30 1 obj\n3\nendobj\n"
        . "31 0 obj\n31\nendobj\n"
        . "31 1 obj\n4\nendobj\n"
        . "32 0 obj\n32\nendobj\n"
        . "32 1 obj\n512\nendobj\n"
        . "40 1 obj\n<< /S /SubmitForm /F (https://example.test/submit) /Fields [6 0 R 10 0 R] /Flags 31 1 R >>\nendobj\n"
        . "42 1 obj\n<< /S /ResetForm /Fields [6 0 R 10 0 R] /Flags 32 1 R >>\nendobj\n"
        . "%%EOF";
};

return [
    'resolves indirect AcroForm signature and submit-reset action flags before WordPress review' => static function (
        TestRunner $t
    ) use ($indirectActionFlagsBoundaryPdf, $fieldsByName, $actionsByTrigger): void {
        $pdf = $indirectActionFlagsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.email', 'article.title'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $signatureFlags = $form['signature_flags'];
        $t->same(3, $signatureFlags['flags']);
        $t->same(['signatures_exist', 'append_only'], $signatureFlags['flag_names']);
        $t->same(true, $signatureFlags['signatures_exist']);
        $t->same(true, $signatureFlags['append_only']);
        $t->same(false, $signatureFlags['executes_signature_validation']);

        $email = $fields['article.email'];
        $title = $fields['article.title'];
        $emailActions = $actionsByTrigger($email['actions']);
        $titleActions = $actionsByTrigger($title['actions']);

        $submit = $emailActions['V'];
        $t->same('SubmitForm', $submit['action_type']);
        $t->same(4, $submit['flags']);
        $t->same(['html_format'], $submit['flag_names']);
        $t->same('include', $submit['fields_mode']);
        $t->same('html', $submit['requested_submit_format']);
        $t->same(true, $submit['html_format_requested']);
        $t->same(false, $submit['get_method_requested']);
        $t->same(false, $submit['submit_coordinates_requested']);
        $t->same(['article.email', 'article.title'], $submit['field_names']);
        $t->same(['article.email', 'article.title'], $submit['field_value_review']['submitted_field_names']);
        $t->same(false, $submit['executes_action']);

        $reset = $titleActions['F'];
        $t->same('ResetForm', $reset['action_type']);
        $t->same(512, $reset['flags']);
        $t->same([], $reset['flag_names']);
        $t->same('include', $reset['fields_mode']);
        $t->same(['article.email', 'article.title'], $reset['field_names']);
        $t->same(['article.email', 'article.title'], $reset['field_value_review']['reset_field_names']);
        $t->same(false, $reset['executes_action']);
        $t->same(false, $reset['field_value_review']['executes_javascript']);

        foreach ([
            'editor@example.test',
            'Reviewed title',
            'https://example.test/submit',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }

        foreach (['"flags":30', '"flags":31', '"fields_mode":"exclude"', 'get_method_requested":true'] as $staleMarker) {
            $t->same(false, str_contains($encoded, $staleMarker));
        }

        $t->same('Visible AcroForm indirect action flags boundary body', $visibleText);
    },
];
