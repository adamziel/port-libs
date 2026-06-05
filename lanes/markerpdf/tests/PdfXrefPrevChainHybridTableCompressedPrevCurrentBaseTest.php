<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainHybridTableCompressedPrevCurrentBasePdf = static function (): string {
    $currentBaseContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid table compressed Prev page) Tj T* (Compressed table Prev helper selected) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Post-helper hybrid table decoy page) Tj ET';
    $currentPayload = '<wp-export><post id="current-hybrid-table-compressed-prev"/></wp-export>';
    $decoyPayload = '<wp-export><post id="post-helper-hybrid-table-decoy"/></wp-export>';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $plain = $header . "\n" . $objectData;
        $compressed = gzcompress($plain);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress hybrid table compressed Prev object stream.');
        }

        return [$header, $compressed];
    };

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $baseCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-GB) /Names << /EmbeddedFiles 8 0 R >> >>');
    $basePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $basePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $baseContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentBaseContent) . " >>\nstream\n{$currentBaseContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $baseInfoOffset = $addObject(6, 0, '<< /Title (Previous Hybrid Table Info Title) /Author (Previous Hybrid Author) >>');
    $baseNameTreeOffset = $addObject(8, 0, '<< /Names [(current-hybrid-table-compressed-prev.xml) 10 0 R] >>');
    $baseFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-hybrid-table-compressed-prev.xml) /Desc (Current hybrid table compressed Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $baseEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 12\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($baseCatalogOffset)
        . $xrefTableRow($basePagesOffset)
        . $xrefTableRow($basePageOffset)
        . $xrefTableRow($baseContentOffset)
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($baseInfoOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($baseNameTreeOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($baseFileSpecOffset)
        . $xrefTableRow($baseEmbeddedOffset)
        . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Names << /EmbeddedFiles 8 0 R >> >>');
    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Hybrid Table Compressed Prev Info Title) /Author (Current Hybrid Table Author) /Producer (Current Hybrid Table Producer) >>');

    $prevHelperIndexes = [];
    [$prevHelperHeader, $prevHelperCompressed] = $objectStream([
        30 => (string) $previousXrefOffset,
    ], $prevHelperIndexes);
    $prevHelperCarrierOffset = $addObject(90, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($prevHelperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($prevHelperCompressed) . " >>\nstream\n{$prevHelperCompressed}\nendstream");

    $companionRows = ''
        . $xrefStreamRow(2, 90, $prevHelperIndexes[30])
        . $xrefStreamRow(1, $prevHelperCarrierOffset, 0);
    $compressedCompanionRows = gzcompress($companionRows);
    if (!is_string($compressedCompanionRows)) {
        throw new RuntimeException('Unable to compress hybrid table companion xref stream.');
    }

    $companionXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 91 /Index [30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCompanionRows) . " >>\n"
        . "stream\n{$compressedCompanionRows}\nendstream\nendobj\n";

    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");
    $addObject(8, 0, '<< /Names [(post-helper-hybrid-table-decoy.xml) 10 0 R] >>');
    $addObject(10, 0, '<< /Type /Filespec /F (post-helper-hybrid-table-decoy.xml) /Desc (Post-helper hybrid table decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
    $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "1 1\n"
        . $xrefTableRow(0)
        . "6 1\n"
        . $xrefTableRow(0)
        . "trailer\n<< /Size 91 /Root 1 0 R /Info 6 0 R /Prev 30 0 R /XRefStm {$companionXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves classic xref table Prev offsets from compressed companion helper object streams' => static function (
        TestRunner $t
    ) use ($xrefPrevChainHybridTableCompressedPrevCurrentBasePdf): void {
        $pdf = $xrefPrevChainHybridTableCompressedPrevCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(
            ['Current hybrid table compressed Prev page', 'Compressed table Prev helper selected'],
            $extractor->extractTextLines($pdf)
        );
        $t->same("Current hybrid table compressed Prev page\nCompressed table Prev helper selected", $text);
        $t->same('Current Hybrid Table Compressed Prev Info Title', $metadata['title']);
        $t->same(['Current Hybrid Table Author'], $metadata['authors']);
        $t->same('Current Hybrid Table Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same(1, count($files));
        $t->same('current-hybrid-table-compressed-prev.xml', $files[0]['filename']);
        $t->same('Current hybrid table compressed Prev attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $currentPayload = '<wp-export><post id="current-hybrid-table-compressed-prev"/></wp-export>';
        $t->same($currentPayload, $files[0]['content']);
        $t->same(hash('sha256', $currentPayload), $files[0]['content_sha256']);
        $t->true(str_contains($pdf, '/XRefStm '));
        $t->true(str_contains($pdf, '/Prev 30 0 R'));
        $t->true(str_contains($pdf, '/Type /ObjStm'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Previous Hybrid Table Info Title'));
        $t->true(is_string($encodedFiles) && !str_contains($encodedFiles, 'post-helper-hybrid-table-decoy'));
        $t->true(!str_contains($text, 'Post-helper hybrid table decoy page'));
        $t->true(!str_contains($text, "\0"));
    },
];
