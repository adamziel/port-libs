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

$acroFormObjectStreamFieldsBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object-stream fields body) Tj ET';

    $compressedMembers = [
        6 => '<< /FT /Tx /T (compressed.email) /TU (Compressed email label) /TM (compressed.email.export) /V (editor@example.test) /DV (draft@example.test) /MaxLen 80 /Kids [8 0 R] >>',
        8 => '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>',
        10 => '<< /FT /Ch /T (compressed.status) /TU (Compressed status label) /V (publish) /Opt [(draft) (publish)] /Kids [14 0 R] >>',
        14 => '<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>',
        30 => '<< /FT /Tx /T (detached.objectstream.decoy) /V (Detached object-stream decoy must not surface) >>',
    ];

    $memberData = '';
    $headerPairs = [];
    foreach ($compressedMembers as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($memberData);
        $memberData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs) . ' ';
    $payload = $header . $memberData;
    $compressedPayload = gzcompress($payload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException('Unable to compress AcroForm object-stream fixture.');
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "20 0 obj\n<< /Type /ObjStm /N " . count($compressedMembers) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

$acroFormObjectStreamFieldOffsetBoundaryPdf = static function (): string {
    $pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm offset-boundary body) Tj ET';
    $embeddedDecoy = '<< /FT /Tx /T (compressed.offset.decoy) /TU (Offset decoy label) /V (Offset decoy must not surface) >>';

    $currentField = '<< /FT /Tx /T (compressed.current) /TU (Current compressed field label) /Note (ignored note with decoy '
        . $embeddedDecoy
        . ' inside literal) /V (current@example.test) /Kids [8 0 R] >>';
    $currentWidget = '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>';
    $statusField = '<< /FT /Ch /T (compressed.review.status) /TU (Review status label) /V (ready) /Opt [(draft) (ready)] /Kids [14 0 R] >>';
    $statusWidget = '<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>';

    $memberData = $currentField . "\n" . $currentWidget . "\n" . $statusField . "\n" . $statusWidget . "\n";
    $badOffset = strpos($memberData, $embeddedDecoy);
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate AcroForm object-stream decoy offset.');
    }

    $headerPairs = [
        '6 0',
        '8 ' . strlen($currentField . "\n"),
        '30 ' . $badOffset,
        '10 ' . strlen($currentField . "\n" . $currentWidget . "\n"),
        '14 ' . strlen($currentField . "\n" . $currentWidget . "\n" . $statusField . "\n"),
    ];
    $header = implode(' ', $headerPairs) . ' ';
    $payload = $header . $memberData;
    $compressedPayload = gzcompress($payload);
    if (!is_string($compressedPayload)) {
        throw new RuntimeException('Unable to compress AcroForm object-stream offset fixture.');
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 14 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Fields [6 0 R 30 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
        . "20 0 obj\n<< /Type /ObjStm /N 5 /First " . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'expands object-stream AcroForm field and widget dictionaries before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormObjectStreamFieldsBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormObjectStreamFieldsBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['compressed.email', 'compressed.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $email = $fields['compressed.email'];
        $t->same(6, $email['object']);
        $t->same('text', $email['field_type_label']);
        $t->same('Compressed email label', $email['alternate_name']);
        $t->same('compressed.email.export', $email['mapping_name']);
        $t->same('editor@example.test', $email['value']);
        $t->same('draft@example.test', $email['default_value']);
        $t->same(80, $email['max_length']);
        $t->same([6], array_column($email['field_hierarchy']['path'], 'object'));
        $t->same(['FT', 'V', 'DV', 'MaxLen'], $email['field_hierarchy']['local_attributes']);
        $t->same('field_terminal', $email['value_state']['hierarchy_boundary']['current_value_source']);
        $t->same([8], array_column($email['widgets'], 'object'));
        $t->same([0], array_column($email['widgets'], 'page_index'));
        $t->same([0], array_column($email['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($email['widgets'], 'referenced_from_page_annots'));

        $status = $fields['compressed.status'];
        $t->same(10, $status['object']);
        $t->same('choice', $status['field_type_label']);
        $t->same('Compressed status label', $status['alternate_name']);
        $t->same('publish', $status['value']);
        $t->same([
            ['export' => 'draft', 'label' => 'draft'],
            ['export' => 'publish', 'label' => 'publish'],
        ], $status['options']);
        $t->same([['index' => 1, 'export' => 'publish', 'label' => 'publish']], $status['value_state']['selected_options']);
        $t->same([14], array_column($status['widgets'], 'object'));
        $t->same([1], array_column($status['widgets'], 'page_annotation_index'));
        $t->same([true], array_column($status['widgets'], 'referenced_from_page_annots'));

        $t->true(is_string($encoded) && !str_contains($encoded, 'detached.objectstream.decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Detached object-stream decoy must not surface'));
        $t->same('Visible AcroForm object-stream fields body', $visibleText);
        $t->true(!str_contains($visibleText, 'editor@example.test'));
        $t->true(!str_contains($visibleText, 'publish'));
        $t->true(!str_contains($visibleText, 'Compressed email label'));
        $t->true(!str_contains($visibleText, 'Detached object-stream decoy must not surface'));
    },
    'rejects AcroForm object-stream member offsets inside literal strings before WordPress review' => static function (
        TestRunner $t
    ) use ($acroFormObjectStreamFieldOffsetBoundaryPdf, $fieldsByName): void {
        $pdf = $acroFormObjectStreamFieldOffsetBoundaryPdf();
        $form = (new PdfAcroFormExtractor())->extractForm($pdf);
        $fields = $fieldsByName($form['fields']);
        $visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($form, JSON_UNESCAPED_SLASHES);

        $t->same(['compressed.current', 'compressed.review.status'], array_keys($fields));
        $t->same(2, count($form['fields']));
        $t->same(true, $form['need_appearances']);

        $current = $fields['compressed.current'];
        $t->same(6, $current['object']);
        $t->same('text', $current['field_type_label']);
        $t->same('current@example.test', $current['value']);
        $t->same([8], array_column($current['widgets'], 'object'));
        $t->same([true], array_column($current['widgets'], 'referenced_from_page_annots'));

        $status = $fields['compressed.review.status'];
        $t->same(10, $status['object']);
        $t->same('choice', $status['field_type_label']);
        $t->same('ready', $status['value']);
        $t->same([14], array_column($status['widgets'], 'object'));

        $t->same('Visible AcroForm offset-boundary body', $visibleText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'compressed.offset.decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Offset decoy must not surface'));
        $t->true(!str_contains($visibleText, 'compressed.offset.decoy'));
        $t->true(!str_contains($visibleText, 'Offset decoy must not surface'));
    },
];
