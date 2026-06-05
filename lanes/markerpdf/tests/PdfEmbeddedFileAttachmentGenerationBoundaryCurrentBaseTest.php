<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;

$embeddedFileAttachmentGenerationBoundaryPdf = static function (): array {
    $currentNamePayload = "Title,Status\nGeneration One NameTree,Ready\n";
    $currentCatalogPayload = '<wp-export><post id="generation-one-catalog-af"/></wp-export>';
    $staleNamePayload = "Title,Status\nGeneration Zero NameTree,Ignore\n";
    $staleCatalogPayload = '<wp-export><post id="generation-zero-catalog-af"/></wp-export>';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber][$generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $addEmbeddedFile = static function (
        int $fileSpecObject,
        int $streamObject,
        int $generation,
        string $filename,
        string $description,
        string $relationship,
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
            "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate ({$modDate}) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream"
        );
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 3 0 R >> /AF [7 0 R] >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(3, 0, '<< /Names [(generation-zero-name.csv) 4 0 R] >>');
    $addEmbeddedFile(4, 5, 0, 'generation-zero-name.csv', 'Stale generation-zero name tree rows', 'Alternative', $staleNamePayload, 'D:20260605194800Z');
    $addEmbeddedFile(7, 8, 0, 'generation-zero-catalog.csv', 'Stale generation-zero catalog AF rows', 'Alternative', $staleCatalogPayload, 'D:20260605194900Z');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $pdf .= isset($offsets[$objectNumber][0])
            ? $xrefRow($offsets[$objectNumber][0])
            : $xrefRow(0, 0, 'f');
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Names << /EmbeddedFiles 3 1 R >> /AF [7 0 R 7 1 R] >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [] /Count 0 >>');
    $addObject(3, 1, '<< /Names [(generation-zero-name.csv) 4 0 R (generation-one-name.csv) 4 1 R] >>');
    $addEmbeddedFile(4, 5, 1, 'generation-one-name.csv', 'Current generation-one name tree rows', 'Data', $currentNamePayload, 'D:20260605195000Z');
    $addEmbeddedFile(7, 8, 1, 'generation-one-catalog.csv', 'Current generation-one catalog AF rows', 'Source', $currentCatalogPayload, 'D:20260605195100Z');

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n1 1\n" . $xrefRow(0, 1, 'n')
        . "trailer\n<< /Size 9 /Root 1 1 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$latestXrefOffset}\n%%EOF\n";

    return [
        $pdf,
        [$currentNamePayload, $currentCatalogPayload],
        [$staleNamePayload, $staleCatalogPayload],
    ];
};

return [
    'keeps generation-exact EmbeddedFiles rows across full and lightweight attachment review' => static function (
        TestRunner $t
    ) use ($embeddedFileAttachmentGenerationBoundaryPdf): void {
        [$pdf, $currentPayloads, $stalePayloads] = $embeddedFileAttachmentGenerationBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(2, count($files));
        $t->same('generation-one-name.csv', $files[0]['name']);
        $t->same('generation-one-name.csv', $files[0]['filename']);
        $t->same('Current generation-one name tree rows', $files[0]['description']);
        $t->same('Data', $files[0]['relationship']);
        $t->same($currentPayloads[0], $files[0]['content']);
        $t->same(4, $files[0]['file_spec_object']);
        $t->same(5, $files[0]['embedded_file_object']);
        $t->same('generation-one-catalog.csv', $files[1]['filename']);
        $t->same('Current generation-one catalog AF rows', $files[1]['description']);
        $t->same('Source', $files[1]['relationship']);
        $t->same(true, $files[1]['associated_file']);
        $t->same(1, $files[1]['associated_file_index']);
        $t->same($currentPayloads[1], $files[1]['content']);

        $t->same(2, $summary['attachment_count']);
        $t->same(['generation-one-name.csv', 'generation-one-catalog.csv'], $summary['filenames']);
        $t->same('generation-one-name.csv', $summary['attachments'][0]['name_key']);
        $t->same('generation-one-catalog.csv', $summary['attachments'][1]['filename']);
        $t->same(1, $summary['attachments'][1]['associated_file_index']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $embeddedMetadata = $metadata['embedded_files'] ?? [];
        $associatedMetadata = $metadata['associated_files'] ?? [];
        $t->same('generation-one-name.csv', $embeddedMetadata[0]['name_tree_name'] ?? null);
        $t->same('generation-one-name.csv', $embeddedMetadata[0]['filename'] ?? null);
        $t->same('generation-one-catalog.csv', $associatedMetadata[0]['filename'] ?? null);
        $t->same(1, $associatedMetadata[0]['associated_file_index'] ?? null);

        foreach ([
            'generation-zero-name.csv',
            'generation-zero-catalog.csv',
            'Stale generation-zero',
            ...$currentPayloads,
            ...$stalePayloads,
        ] as $hidden) {
            $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, $hidden));
            $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $hidden));
            $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hidden));
        }
    },
];
