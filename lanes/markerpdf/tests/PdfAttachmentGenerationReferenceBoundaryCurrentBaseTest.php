<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$attachmentGenerationReferencePdf = static function (): array {
    $currentNamePayload = "Title,Status\nCurrent Generation NameTree,Ready\n";
    $currentCatalogPayload = '<wp-export><post id="current-generation-catalog-af"/></wp-export>';
    $currentPagePayload = '<wp-page><attachment role="current-generation-page-af"/></wp-page>';
    $staleNamePayload = "Title,Status\nStale Generation NameTree,Ignore\n";
    $staleCatalogPayload = '<wp-export><post id="stale-generation-catalog-af"/></wp-export>';
    $stalePagePayload = '<wp-page><attachment role="stale-generation-page-af"/></wp-page>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber][$generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $addEmbeddedFile = static function (
        int $fileSpecObject,
        int $streamObject,
        int $generation,
        string $filename,
        string $description,
        string $relationship,
        string $subtype,
        string $payload,
        string $modDate
    ) use ($addObject): void {
        $checksum = md5($payload);
        $addObject(
            $fileSpecObject,
            $generation,
            "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /{$relationship} /EF << /F {$streamObject} {$generation} R >> >>"
        );
        $addObject(
            $streamObject,
            $generation,
            "<< /Type /EmbeddedFile /Subtype /{$subtype} /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate ({$modDate}) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream"
        );
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 3 0 R >> /AF [7 0 R] >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Names [(stale-generation-nametree.csv) 4 0 R] >>');
    $addEmbeddedFile(4, 5, 0, 'stale-generation-nametree.csv', 'Stale generation name tree rows', 'Alternative', 'text#2Fcsv', $staleNamePayload, 'D:20260605041000Z');
    $addEmbeddedFile(7, 8, 0, 'stale-generation-catalog.xml', 'Stale generation catalog associated file', 'Alternative', 'text#2Fxml', $staleCatalogPayload, 'D:20260605041100Z');
    $addObject(11, 0, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [12 0 R] >>');
    $addEmbeddedFile(12, 13, 0, 'stale-generation-page.xml', 'Stale generation page associated file', 'Alternative', 'text#2Fxml', $stalePagePayload, 'D:20260605041200Z');

    $previousXrefOffset = strlen($pdf);
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 14\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 13; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber][0])
            ? $xrefRow($offsets[$objectNumber][0])
            : $xrefRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 14 /Root 1 0 R >>\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Names << /EmbeddedFiles 3 1 R >> /AF [7 1 R] >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [11 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Names [(current-generation-nametree.csv) 4 1 R] >>');
    $addEmbeddedFile(4, 5, 1, 'current-generation-nametree.csv', 'Current generation name tree rows', 'Data', 'text#2Fcsv', $currentNamePayload, 'D:20260605041300Z');
    $addEmbeddedFile(7, 8, 1, 'current-generation-catalog.xml', 'Current generation catalog associated file', 'Source', 'text#2Fxml', $currentCatalogPayload, 'D:20260605041400Z');
    $addObject(11, 1, '<< /Type /Page /Parent 2 1 R /MediaBox [0 0 612 792] /AF [12 1 R] >>');
    $addEmbeddedFile(12, 13, 1, 'current-generation-page.xml', 'Current generation page associated file', 'Source', 'text#2Fxml', $currentPagePayload, 'D:20260605041500Z');

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n1 1\n" . $xrefRow(0, 1, 'n')
        . "trailer\n<< /Size 14 /Root 1 1 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF\n";

    return [
        $pdf,
        [$currentNamePayload, $currentCatalogPayload, $currentPagePayload],
        [$staleNamePayload, $staleCatalogPayload, $stalePagePayload],
    ];
};

return [
    'repairs generationed attachment references after damaged latest xref rows' => static function (
        TestRunner $t
    ) use ($attachmentGenerationReferencePdf): void {
        [$pdf, $currentPayloads, $stalePayloads] = $attachmentGenerationReferencePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(3, $summary['attachment_count']);
        $t->same([
            'current-generation-nametree.csv',
            'current-generation-catalog.xml',
            'current-generation-page.xml',
        ], $summary['filenames']);

        $nameTree = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $nameTree['source']);
        $t->same('current-generation-nametree.csv', $nameTree['name_key']);
        $t->same('current-generation-nametree.csv', $nameTree['filename']);
        $t->same('Current generation name tree rows', $nameTree['description']);
        $t->same('Data', $nameTree['relationship']);
        $t->same('base_data_for_visual_presentation', $nameTree['relationship_role']);
        $t->same(4, $nameTree['file_spec_object_id']);
        $t->same(5, $nameTree['stream_object_id']);
        $t->same(strlen($currentPayloads[0]), $nameTree['byte_length']);
        $t->same(true, $nameTree['checksum_matches']);
        $t->same('D:20260605041300Z', $nameTree['modified_at']);
        $t->same(false, array_key_exists('bytes', $nameTree));

        $catalog = $summary['attachments'][1];
        $t->same('catalog-associated-file', $catalog['source']);
        $t->same(true, $catalog['associated_file']);
        $t->same(1, $catalog['catalog_object_id']);
        $t->same(7, $catalog['file_spec_object_id']);
        $t->same('current-generation-catalog.xml', $catalog['filename']);
        $t->same('Source', $catalog['relationship']);
        $t->same('original_source', $catalog['relationship_role']);
        $t->same(strlen($currentPayloads[1]), $catalog['byte_length']);
        $t->same(true, $catalog['checksum_matches']);
        $t->same('D:20260605041400Z', $catalog['modified_at']);
        $t->same(false, array_key_exists('bytes', $catalog));

        $page = $summary['attachments'][2];
        $t->same('page-associated-file', $page['source']);
        $t->same(true, $page['associated_file']);
        $t->same(true, $page['page_associated_file']);
        $t->same(1, $page['page_number']);
        $t->same(11, $page['page_object_id']);
        $t->same(12, $page['file_spec_object_id']);
        $t->same('current-generation-page.xml', $page['filename']);
        $t->same('Source', $page['relationship']);
        $t->same('original_source', $page['relationship_role']);
        $t->same(strlen($currentPayloads[2]), $page['byte_length']);
        $t->same(true, $page['checksum_matches']);
        $t->same('D:20260605041500Z', $page['modified_at']);
        $t->same(false, array_key_exists('bytes', $page));

        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'stale-generation-nametree.csv',
            'stale-generation-catalog.xml',
            'stale-generation-page.xml',
            ...$currentPayloads,
            ...$stalePayloads,
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
];
