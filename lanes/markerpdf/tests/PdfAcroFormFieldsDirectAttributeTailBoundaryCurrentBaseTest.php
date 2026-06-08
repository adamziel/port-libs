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

$directAttributeTailBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm direct attribute tail body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 18 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R 16 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) /Q 1 >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (article.valid) /Ff 0 /V (valid@example.test) /DV (draft@example.test) /Q 0 /MaxLen 64 /Kids [8 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
        . "10 0 obj\n<< /FT /Tx 90 0 R /T (article.tailed) /TU (Tailed direct attribute label) /TM (article.tailed.export) /Ff 4096 91 0 R /V (tailed current must not surface) 92 0 R /DV (tailed default must not surface) 93 0 R /Q 2 94 0 R /MaxLen 24 95 0 R /DA (/Helv 8 Tf 1 0 0 rg) 96 0 R /Kids [12 0 R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /P 3 0 R /F 4 >>\nendobj\n"
        . "16 0 obj\n<< /FT /Tx % field type comment tail\n/T (article.comment) /Ff 4096 % flags comment tail\n/V (comment current value) % value comment tail\n/DV (comment default value) % default comment tail\n/Q 2 % quadding comment tail\n/MaxLen 40 % max length comment tail\n/Kids [18 0 R] >>\nendobj\n"
        . "18 0 obj\n<< /Subtype /Widget /Parent 16 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
        . "90 0 obj\n<< /FT /Sig /T (tail.ft.decoy) /V (Tail FT decoy value) >>\nendobj\n"
        . "91 0 obj\n8192\nendobj\n"
        . "92 0 obj\n(Tail current decoy object)\nendobj\n"
        . "93 0 obj\n(Tail default decoy object)\nendobj\n"
        . "94 0 obj\n0\nendobj\n"
        . "95 0 obj\n8\nendobj\n"
        . "96 0 obj\n(/Decoy 12 Tf 0 1 0 rg)\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed direct AcroForm scalar attributes before WordPress field review' => static function (
        TestRunner $t
    ) use ($directAttributeTailBoundaryPdf, $fieldsByName): void {
        $pdf = $directAttributeTailBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['article.valid', 'article.tailed', 'article.comment'], array_keys($fields));
        $t->same(3, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $valid = $fields['article.valid'];
        $t->same(6, $valid['object']);
        $t->same('text', $valid['field_type_label']);
        $t->same('valid@example.test', $valid['value']);
        $t->same('draft@example.test', $valid['default_value']);
        $t->same(64, $valid['max_length']);
        $t->same('left', $valid['text_alignment']);
        $t->same([8], array_column($valid['widgets'], 'object'));

        $tailed = $fields['article.tailed'];
        $t->same(10, $tailed['object']);
        $t->same(null, $tailed['field_type']);
        $t->same('unknown', $tailed['field_type_label']);
        $t->same(0, $tailed['flags']);
        $t->same([], $tailed['flag_names']);
        $t->same(null, $tailed['value']);
        $t->same(null, $tailed['default_value']);
        $t->same(null, $tailed['max_length']);
        $t->same(1, $tailed['quadding']);
        $t->same('center', $tailed['text_alignment']);
        $t->same(false, $tailed['value_state']['has_current_value']);
        $t->same(false, $tailed['value_state']['has_default_value']);
        $t->same(null, $tailed['value_state']['current_source']);
        $t->same(null, $tailed['value_state']['display_value']);
        $t->same(['DA', 'Q'], $tailed['field_hierarchy']['inherited_attributes']);
        $t->same([], $tailed['field_hierarchy']['local_attributes']);
        $t->same([], $tailed['field_hierarchy']['local_value_attributes']);
        $t->same([12], array_column($tailed['widgets'], 'object'));
        $t->same([1], array_column($tailed['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($tailed['widgets'], 'referenced_from_page_annots'));

        $comment = $fields['article.comment'];
        $t->same(16, $comment['object']);
        $t->same('text', $comment['field_type_label']);
        $t->same(4096, $comment['flags']);
        $t->same(['multiline'], $comment['flag_names']);
        $t->same('comment current value', $comment['value']);
        $t->same('comment default value', $comment['default_value']);
        $t->same(40, $comment['max_length']);
        $t->same(2, $comment['quadding']);
        $t->same('right', $comment['text_alignment']);
        $t->same(true, $comment['value_state']['has_current_value']);
        $t->same(true, $comment['value_state']['has_default_value']);
        $t->same('comment current value', $comment['value_state']['display_value']);
        $t->same(['FT', 'Ff', 'V', 'DV', 'Q', 'MaxLen'], $comment['field_hierarchy']['local_attributes']);
        $t->same(['V', 'DV'], $comment['field_hierarchy']['local_value_attributes']);
        $t->same([18], array_column($comment['widgets'], 'object'));
        $t->same([2], array_column($comment['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($comment['widgets'], 'referenced_from_page_annots'));

        foreach ([
            'tailed current must not surface',
            'tailed default must not surface',
            'Tail FT decoy value',
            'Tail current decoy object',
            'Tail default decoy object',
            '/Decoy 12 Tf',
        ] as $tailedText) {
            $t->same(false, str_contains($encoded, $tailedText));
            $t->same(false, str_contains($visibleText, $tailedText));
        }

        foreach (['valid@example.test', 'comment current value', 'Tailed direct attribute label'] as $reviewOnlyText) {
            $t->same(false, str_contains($visibleText, $reviewOnlyText));
        }

        $t->same('Visible AcroForm direct attribute tail body', $visibleText);
    },
];
