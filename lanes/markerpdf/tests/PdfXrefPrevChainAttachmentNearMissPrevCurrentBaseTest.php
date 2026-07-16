<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$xrefPrevChainAttachmentNearMissPrevPdf = static function (): array {
    $nameTreePayload = "Title,Status\nRepaired Prev Attachment,Ready\n";
    $pagePayload = '<wp-page><attachment role="repaired-prev-page-af"/></wp-page>';
    $decoyPayload = '<wp-export><post id="unindexed-generation-decoy"/></wp-export>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . pack('n', $fieldThree);

    $addObject(6, 0, '<< /Names [(repaired-prev-chain.csv) 7 0 R] >>');
    $addObject(
        7,
        0,
        '<< /Type /Filespec /F (repaired-prev-chain.csv) /Desc (Repaired near-miss Prev name tree rows) /AFRelationship /Data /EF << /F 8 0 R >> >>'
    );
    $addObject(
        8,
        0,
        '<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size ' . strlen($nameTreePayload)
            . ' /CheckSum <' . md5($nameTreePayload) . '> /ModDate (D:20260605100853Z) >> /Length '
            . strlen($nameTreePayload) . " >>\nstream\n{$nameTreePayload}\nendstream"
    );
    $addObject(
        9,
        0,
        '<< /Type /Filespec /F (repaired-prev-page.xml) /Desc (Repaired near-miss Prev page AF) /AFRelationship /Source /EF << /F 10 0 R >> >>'
    );
    $addObject(
        10,
        0,
        '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($pagePayload)
            . ' /CheckSum <' . md5($pagePayload) . '> /ModDate (D:20260605100953Z) >> /Length '
            . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream"
    );

    $previousCommentOffset = strlen($pdf);
    $pdf .= "% producer padding before previous xref\n";
    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['6:0'])
        . $xrefRow($offsets['7:0'])
        . $xrefRow($offsets['8:0'])
        . $xrefRow($offsets['9:0'])
        . $xrefRow($offsets['10:0'])
        . "trailer\n<< /Size 11 >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(6, 1, '<< /Names [(unindexed-decoy.csv) 7 1 R] >>');
    $addObject(7, 1, '<< /Type /Filespec /F (unindexed-decoy.csv) /Desc (Unindexed generation decoy) /AFRelationship /Alternative /EF << /F 8 1 R >> >>');
    $addObject(8, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
    $addObject(9, 1, '<< /Type /Filespec /F (unindexed-page-decoy.xml) /Desc (Unindexed page decoy) /AFRelationship /Alternative /EF << /F 10 1 R >> >>');
    $addObject(10, 1, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Names << /EmbeddedFiles 6 0 R >> /AF [7 0 R] >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /MediaBox [0 0 612 792] /AF [9 0 R] >>');

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:1'], 1)
        . $xrefStreamRow(1, $offsets['2:1'], 1)
        . $xrefStreamRow(1, $offsets['3:1'], 1)
        . $xrefStreamRow(1, 0, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress near-miss Prev xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $nearMissPrevOffset = $previousCommentOffset + 2;
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $nearMissPrevOffset
        . ' /Index [1 3 20 1] /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [$pdf, [$nameTreePayload, $pagePayload], $decoyPayload, $previousXrefOffset, $nearMissPrevOffset];
};

return [
    'repairs near miss xref stream Prev offset before generation exact attachments' => static function (
        TestRunner $t
    ) use ($xrefPrevChainAttachmentNearMissPrevPdf): void {
        [$pdf, $payloads, $decoyPayload, $previousXrefOffset, $nearMissPrevOffset] = $xrefPrevChainAttachmentNearMissPrevPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->true($nearMissPrevOffset < $previousXrefOffset);
        $t->same(2, $summary['attachment_count']);
        $t->same(['repaired-prev-chain.csv', 'repaired-prev-page.xml'], $summary['filenames']);

        $nameTree = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $nameTree['source']);
        $t->same(true, $nameTree['associated_file']);
        $t->same('catalog_af', $nameTree['associated_file_source']);
        $t->same('repaired-prev-chain.csv', $nameTree['name_key']);
        $t->same('repaired-prev-chain.csv', $nameTree['filename']);
        $t->same('Repaired near-miss Prev name tree rows', $nameTree['description']);
        $t->same('Data', $nameTree['relationship']);
        $t->same('base_data_for_visual_presentation', $nameTree['relationship_role']);
        $t->same(7, $nameTree['file_spec_object_id']);
        $t->same(8, $nameTree['stream_object_id']);
        $t->same(strlen($payloads[0]), $nameTree['byte_length']);
        $t->same(true, $nameTree['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $nameTree));

        $page = $summary['attachments'][1];
        $t->same('page-associated-file', $page['source']);
        $t->same(true, $page['page_associated_file']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object_id']);
        $t->same(9, $page['file_spec_object_id']);
        $t->same(10, $page['stream_object_id']);
        $t->same('repaired-prev-page.xml', $page['filename']);
        $t->same('Source', $page['relationship']);
        $t->same('original_source', $page['relationship_role']);
        $t->same(strlen($payloads[1]), $page['byte_length']);
        $t->same(true, $page['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $page));

        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach (['unindexed-decoy.csv', 'unindexed-page-decoy.xml', $decoyPayload, ...$payloads] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
];
