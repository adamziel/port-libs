<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$attachmentTrailerRootBoundaryPdf = static function (): array {
    $currentNamePayload = "Title,Status\nCurrent Root NameTree,Ready\n";
    $currentCatalogPayload = '<wp-export><post id="current-root-catalog-af"/></wp-export>';
    $currentPagePayload = '<wp-page><attachment role="current-root-page-af"/></wp-page>';
    $staleNamePayload = "Title,Status\nStale Orphan NameTree,Ignore\n";
    $staleCatalogPayload = '<wp-export><post id="stale-orphan-catalog-af"/></wp-export>';
    $stalePagePayload = '<wp-page><attachment role="stale-orphan-page-af"/></wp-page>';

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addEmbeddedFile = static function (
        int $fileSpecObject,
        int $streamObject,
        string $filename,
        string $description,
        string $relationship,
        string $subtype,
        string $payload,
        string $modDate
    ) use ($addObject): void {
        $checksum = md5($payload);
        $addObject($fileSpecObject, "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /{$relationship} /EF << /F {$streamObject} 0 R >> >>");
        $addObject($streamObject, "<< /Type /EmbeddedFile /Subtype /{$subtype} /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate ({$modDate}) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 4 0 R >> /AF [40 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [50 0 R] >>');
    $addObject(4, '<< /Names [(stale-orphan-nametree.csv) 5 0 R] >>');
    $addEmbeddedFile(5, 6, 'stale-orphan-nametree.csv', 'Stale orphan catalog name tree rows', 'Alternative', 'text#2Fcsv', $staleNamePayload, 'D:20260605032000Z');
    $addEmbeddedFile(40, 41, 'stale-orphan-catalog.xml', 'Stale orphan catalog associated file', 'Alternative', 'text#2Fxml', $staleCatalogPayload, 'D:20260605032100Z');
    $addEmbeddedFile(50, 51, 'stale-orphan-page.xml', 'Stale orphan page associated file', 'Alternative', 'text#2Fxml', $stalePagePayload, 'D:20260605032200Z');

    $addObject(10, '<< /Type /Catalog /Pages 11 0 R /Names << /EmbeddedFiles 12 0 R >> /AF [20 0 R] >>');
    $addObject(11, '<< /Type /Pages /Kids [13 0 R] /Count 1 >>');
    $addObject(12, '<< /Names [(current-root-nametree.csv) 14 0 R] >>');
    $addObject(13, '<< /Type /Page /Parent 11 0 R /MediaBox [0 0 612 792] /AF [30 0 R] >>');
    $addEmbeddedFile(14, 15, 'current-root-nametree.csv', 'Current trailer Root name tree rows', 'Data', 'text#2Fcsv', $currentNamePayload, 'D:20260605032300Z');
    $addEmbeddedFile(20, 21, 'current-root-catalog.xml', 'Current trailer Root catalog associated file', 'Source', 'text#2Fxml', $currentCatalogPayload, 'D:20260605032400Z');
    $addEmbeddedFile(30, 31, 'current-root-page.xml', 'Current trailer Root page associated file', 'Source', 'text#2Fxml', $currentPagePayload, 'D:20260605032500Z');

    $xrefOffset = strlen($pdf);
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $pdf .= "xref\n0 52\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 51; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber])
            ? $xrefRow($offsets[$objectNumber])
            : $xrefRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 52 /Root 10 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return [
        $pdf,
        [$currentNamePayload, $currentCatalogPayload, $currentPagePayload],
        [$staleNamePayload, $staleCatalogPayload, $stalePagePayload],
    ];
};

return [
    'uses latest trailer Root catalog before orphan catalog attachment rows' => static function (
        TestRunner $t
    ) use ($attachmentTrailerRootBoundaryPdf): void {
        [$pdf, $currentPayloads, $stalePayloads] = $attachmentTrailerRootBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(3, $summary['attachment_count']);
        $t->same([
            'current-root-nametree.csv',
            'current-root-catalog.xml',
            'current-root-page.xml',
        ], $summary['filenames']);

        $nameTree = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $nameTree['source']);
        $t->same('current-root-nametree.csv', $nameTree['name_key']);
        $t->same('current-root-nametree.csv', $nameTree['filename']);
        $t->same('Current trailer Root name tree rows', $nameTree['description']);
        $t->same('Data', $nameTree['relationship']);
        $t->same('base_data_for_visual_presentation', $nameTree['relationship_role']);
        $t->same(14, $nameTree['file_spec_object_id']);
        $t->same(15, $nameTree['stream_object_id']);
        $t->same(strlen($currentPayloads[0]), $nameTree['byte_length']);
        $t->same(true, $nameTree['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $nameTree));

        $catalog = $summary['attachments'][1];
        $t->same('catalog-associated-file', $catalog['source']);
        $t->same(true, $catalog['associated_file']);
        $t->same(10, $catalog['catalog_object_id']);
        $t->same(20, $catalog['file_spec_object_id']);
        $t->same('current-root-catalog.xml', $catalog['filename']);
        $t->same('Source', $catalog['relationship']);
        $t->same('original_source', $catalog['relationship_role']);
        $t->same(strlen($currentPayloads[1]), $catalog['byte_length']);
        $t->same(true, $catalog['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $catalog));

        $page = $summary['attachments'][2];
        $t->same('page-associated-file', $page['source']);
        $t->same(true, $page['associated_file']);
        $t->same(true, $page['page_associated_file']);
        $t->same(1, $page['page_number']);
        $t->same(13, $page['page_object_id']);
        $t->same(30, $page['file_spec_object_id']);
        $t->same('current-root-page.xml', $page['filename']);
        $t->same('Source', $page['relationship']);
        $t->same('original_source', $page['relationship_role']);
        $t->same(strlen($currentPayloads[2]), $page['byte_length']);
        $t->same(true, $page['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $page));

        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'stale-orphan-nametree.csv',
            'stale-orphan-catalog.xml',
            'stale-orphan-page.xml',
            ...$currentPayloads,
            ...$stalePayloads,
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
];
