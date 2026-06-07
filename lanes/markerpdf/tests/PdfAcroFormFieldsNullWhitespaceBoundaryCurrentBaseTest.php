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

$acroFormNullWhitespacePdf = static function (): string {
    $nul = "\0";
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm null whitespace body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5{$nul}0{$nul}R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8{$nul}0{$nul}R 12{$nul}0{$nul}R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6{$nul}0{$nul}R 10{$nul}0{$nul}R (99{$nul}0{$nul}R literal decoy)] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "6 0 obj\n<< /FT /Tx /T (nullws.email) /TU (Null whitespace email label) /TM (nullws.email.export) /V (nullws@example.test) /DV (draft-nullws@example.test) /MaxLen 64 /Kids [8{$nul}0{$nul}R] >>\nendobj\n"
        . "8 0 obj\n<< /Subtype /Widget /Parent 6{$nul}0{$nul}R /Rect [320{$nul}664{$nul}72{$nul}640] /P 3{$nul}0{$nul}R /F 34{$nul}0{$nul}R >>\nendobj\n"
        . "10 0 obj\n<< /FT /Ch /T (nullws.status) /V (publish) /Opt [(draft) (publish)] /Kids [12{$nul}0{$nul}R] >>\nendobj\n"
        . "12 0 obj\n<< /Subtype /Widget /Parent 10{$nul}0{$nul}R /Rect [72{$nul}600{$nul}260{$nul}624] /P 3{$nul}0{$nul}R /F 4 >>\nendobj\n"
        . "34 0 obj\n4\nendobj\n"
        . "99 0 obj\n<< /FT /Tx /T (nullws.literal.decoy) /V (NUL whitespace literal decoy) >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF null bytes as whitespace inside AcroForm field references and widget geometry' => static function (
        TestRunner $t
    ) use ($acroFormNullWhitespacePdf, $fieldsByName): void {
        $pdf = $acroFormNullWhitespacePdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['nullws.email', 'nullws.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['nullws.email'];
        $t->same(6, $email['object']);
        $t->same('text', $email['field_type_label']);
        $t->same('Null whitespace email label', $email['alternate_name']);
        $t->same('nullws.email.export', $email['mapping_name']);
        $t->same('nullws@example.test', $email['value']);
        $t->same('draft-nullws@example.test', $email['default_value']);
        $t->same('field_terminal', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([3], array_column($email['widgets'], 'page_object'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));
        $t->same([72.0, 640.0, 320.0, 664.0], $email['widgets'][0]['rect']);
        $t->same(4, $email['widgets'][0]['annotation_flags']);
        $t->same(['print'], $email['widgets'][0]['annotation_flag_names']);
        $t->same('visible', $email['widgets'][0]['annotation_visibility']);

        $status = $fields['nullws.status'];
        $t->same(10, $status['object']);
        $t->same('choice', $status['field_type_label']);
        $t->same('publish', $status['value']);
        $t->same([
            ['export' => 'draft', 'label' => 'draft'],
            ['export' => 'publish', 'label' => 'publish'],
        ], $status['options']);
        $t->same([['index' => 1, 'export' => 'publish', 'label' => 'publish']], $status['value_state']['selected_options']);
        $t->same([12], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));
        $t->same([72.0, 600.0, 260.0, 624.0], $status['widgets'][0]['rect']);

        $t->true(!isset($fields['nullws.literal.decoy']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'nullws.literal.decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NUL whitespace literal decoy'));
        $t->same('Visible AcroForm null whitespace body', trim($visibleText));
        $t->true(!str_contains($visibleText, 'nullws@example.test'));
        $t->true(!str_contains($visibleText, 'publish'));
        $t->true(!str_contains($visibleText, 'NUL whitespace literal decoy'));
    },
];
