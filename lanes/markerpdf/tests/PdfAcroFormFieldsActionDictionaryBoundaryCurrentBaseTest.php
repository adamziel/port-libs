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

$acroFormActionDictionaryBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm action dictionary boundary body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 20 0 R 24 0 R] /NeedAppearances true >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.title) /V (Visible title review value) /Kids [8 0 R 22 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx /T (article.summary) /V (Visible summary review value) /Kids [12 0 R] /AA << /K 32 0 R >> >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "20 0 obj\n<< /S /Hide /T (decoy.root.hide) /V (Root hide action decoy value) /H true >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /T (decoy.kid.uri) /URI (javascript:alert('kid')) /V (Kid URI action decoy value) >>\nendobj\n"
        . "24 0 obj\n<< /S /JavaScript /T (decoy.root.javascript) /JS (app.alert('root')) /V (Root JavaScript action decoy value) >>\nendobj\n"
        . "32 0 obj\n<< /S /Hide /T [6 0 R (named.target)] /H true >>\nendobj\n"
        . "%%EOF";
};

return [
    'excludes AcroForm action dictionaries that advertise target names as fields' => static function (
        TestRunner $t
    ) use ($acroFormActionDictionaryBoundaryPdf, $fieldsByName, $actionsByTrigger): void {
        $pdf = $acroFormActionDictionaryBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.title', 'article.summary'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $title = $fields['article.title'];
        $summary = $fields['article.summary'];
        $t->same([8], array_column($title['widgets'], 'object'));
        $t->same([12], array_column($summary['widgets'], 'object'));
        $t->same([0], array_column($title['widgets'], 'page_annotation_index'));
        $t->same([1], array_column($summary['widgets'], 'page_annotation_index'));

        $t->same([], $title['actions']);
        $summaryActions = $actionsByTrigger($summary['actions']);
        $hide = $summaryActions['K'];
        $t->same('Hide', $hide['action_type']);
        $t->same([6], $hide['field_objects']);
        $t->same(['article.title', 'named.target'], $hide['field_names']);
        $t->same([], $hide['unresolved_field_objects']);
        $t->same(false, $hide['executes_action']);
        $t->same(false, $hide['executes_javascript']);

        foreach ([
            'decoy.root.hide',
            'Root hide action decoy value',
            'decoy.kid.uri',
            'Kid URI action decoy value',
            'decoy.root.javascript',
            'Root JavaScript action decoy value',
            "javascript:alert('kid')",
            "app.alert('root')",
        ] as $decoyText) {
            $t->same(false, str_contains($encoded, $decoyText));
            $t->same(false, str_contains($visibleText, $decoyText));
        }

        foreach (['Visible title review value', 'Visible summary review value'] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }
        $t->same('Visible AcroForm action dictionary boundary body', $visibleText);
    },
];
