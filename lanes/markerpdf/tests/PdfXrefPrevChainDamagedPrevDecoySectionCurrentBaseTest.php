<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefPrevChainDamagedPrevDecoySectionXmp = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-08T16:15:23Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$xrefPrevChainDamagedPrevDecoySectionPdf = static function () use ($xrefPrevChainDamagedPrevDecoySectionXmp): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous damaged Prev decoy-section page) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Unlinked decoy xref-section page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged Prev decoy-section page) Tj T* (Declared Prev neighborhood repaired) Tj ET';
    $previousPayload = '<wp-export><post id="previous-damaged-prev-decoy-section"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-damaged-prev-section"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-damaged-prev-decoy-section"/></wp-export>';
    $previousXmp = gzcompress($xrefPrevChainDamagedPrevDecoySectionXmp(
        'Previous Damaged Prev Decoy Section XMP Title',
        'Previous trailer root should be updated by current rows'
    ));
    $decoyXmp = gzcompress($xrefPrevChainDamagedPrevDecoySectionXmp(
        'Decoy Damaged Prev Section XMP Title',
        'Unlinked decoy xref section must not own the chain'
    ));
    $currentXmp = gzcompress($xrefPrevChainDamagedPrevDecoySectionXmp(
        'Current Damaged Prev Decoy Section XMP Title',
        'Damaged Prev repairs to declared-offset neighborhood'
    ));
    if (!is_string($previousXmp) || !is_string($decoyXmp) || !is_string($currentXmp)) {
        throw new RuntimeException('Unable to compress damaged Prev decoy-section fixture streams.');
    }

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Damaged Prev Decoy Section Info Title) /Author (Previous Damaged Prev Author) /Producer (Previous Damaged Prev Producer) >>');
    $previousCatalogOffset = $addObject(12, 0, '<< /Type /Catalog /Pages 13 0 R /Lang (de-DE) /Metadata 17 0 R /Names << /EmbeddedFiles 18 0 R >> >>');
    $previousPagesOffset = $addObject(13, 0, '<< /Type /Pages /Kids [14 0 R] /Count 1 >>');
    $previousPageOffset = $addObject(14, 0, '<< /Type /Page /Parent 13 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R >>');
    $previousContentOffset = $addObject(15, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $previousMetadataOffset = $addObject(17, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
    $previousNameTreeOffset = $addObject(18, 0, '<< /Names [(previous-damaged-prev-decoy-section.xml) 19 0 R] >>');
    $previousFileSpecOffset = $addObject(19, 0, '<< /Type /Filespec /F (previous-damaged-prev-decoy-section.xml) /Desc (Previous damaged Prev decoy-section attachment) /AFRelationship /Source /EF << /F 20 0 R >> >>');
    $previousEmbeddedOffset = $addObject(20, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($previousPayload) . " >>\nstream\n{$previousPayload}\nendstream");

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefTableRow(0, 65535, 'f')
        . "5 2\n"
        . $xrefTableRow($fontOffset)
        . $xrefTableRow($previousInfoOffset)
        . "12 4\n"
        . $xrefTableRow($previousCatalogOffset)
        . $xrefTableRow($previousPagesOffset)
        . $xrefTableRow($previousPageOffset)
        . $xrefTableRow($previousContentOffset)
        . "17 4\n"
        . $xrefTableRow($previousMetadataOffset)
        . $xrefTableRow($previousNameTreeOffset)
        . $xrefTableRow($previousFileSpecOffset)
        . $xrefTableRow($previousEmbeddedOffset)
        . "trailer\n<< /Size 21 /Root 12 0 R /Info 6 0 R >>\n"
        . "startxref\n{$baseXrefOffset}\n%%EOF\n";

    $decoyInfoOffset = $addObject(38, 0, '<< /Title (Decoy Damaged Prev Section Info Title) /Author (Decoy Damaged Prev Author) /Producer (Decoy Damaged Prev Producer) >>');
    $decoyCatalogOffset = $addObject(30, 0, '<< /Type /Catalog /Pages 31 0 R /Lang (fr-FR) /Metadata 34 0 R /Names << /EmbeddedFiles 35 0 R >> >>');
    $decoyPagesOffset = $addObject(31, 0, '<< /Type /Pages /Kids [32 0 R] /Count 1 >>');
    $decoyPageOffset = $addObject(32, 0, '<< /Type /Page /Parent 31 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 33 0 R >>');
    $decoyContentOffset = $addObject(33, 0, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");
    $decoyMetadataOffset = $addObject(34, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $decoyNameTreeOffset = $addObject(35, 0, '<< /Names [(decoy-damaged-prev-section.xml) 36 0 R] >>');
    $decoyFileSpecOffset = $addObject(36, 0, '<< /Type /Filespec /F (decoy-damaged-prev-section.xml) /Desc (Decoy damaged Prev xref-section attachment) /AFRelationship /Data /EF << /F 37 0 R >> >>');
    $decoyEmbeddedOffset = $addObject(37, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $decoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "30 9\n"
        . $xrefTableRow($decoyCatalogOffset)
        . $xrefTableRow($decoyPagesOffset)
        . $xrefTableRow($decoyPageOffset)
        . $xrefTableRow($decoyContentOffset)
        . $xrefTableRow($decoyMetadataOffset)
        . $xrefTableRow($decoyNameTreeOffset)
        . $xrefTableRow($decoyFileSpecOffset)
        . $xrefTableRow($decoyEmbeddedOffset)
        . $xrefTableRow($decoyInfoOffset)
        . "trailer\n<< /Size 39 /Root 30 0 R /Info 38 0 R >>\n";

    $currentInfoOffset = $addObject(6, 0, '<< /Title (Current Damaged Prev Decoy Section Info Title) /Author (Current Damaged Prev Author) /Producer (Current Damaged Prev Producer) >>');
    $currentCatalogOffset = $addObject(12, 0, '<< /Type /Catalog /Pages 13 0 R /Lang (en-US) /Metadata 17 0 R /Names << /EmbeddedFiles 18 0 R >> >>');
    $currentPagesOffset = $addObject(13, 0, '<< /Type /Pages /Kids [14 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(14, 0, '<< /Type /Page /Parent 13 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R >>');
    $currentContentOffset = $addObject(15, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentMetadataOffset = $addObject(17, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $currentNameTreeOffset = $addObject(18, 0, '<< /Names [(current-damaged-prev-decoy-section.xml) 19 0 R] >>');
    $currentFileSpecOffset = $addObject(19, 0, '<< /Type /Filespec /F (current-damaged-prev-decoy-section.xml) /Desc (Current damaged Prev decoy-section attachment) /AFRelationship /Source /EF << /F 20 0 R >> >>');
    $currentEmbeddedOffset = $addObject(20, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $currentInfoOffset, 0)
        . $xrefStreamRow(1, $currentCatalogOffset, 0)
        . $xrefStreamRow(1, $currentPagesOffset, 0)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0)
        . $xrefStreamRow(1, $currentMetadataOffset, 0)
        . $xrefStreamRow(1, $currentNameTreeOffset, 0)
        . $xrefStreamRow(1, $currentFileSpecOffset, 0)
        . $xrefStreamRow(1, $currentEmbeddedOffset, 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress current damaged Prev decoy-section xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $damagedPrevOffset = $baseXrefOffset + 3;
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Info 6 0 R /Prev ' . $damagedPrevOffset . ' /Index [6 1 12 4 17 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return [
        'pdf' => $pdf,
        'baseXrefOffset' => $baseXrefOffset,
        'damagedPrevOffset' => $damagedPrevOffset,
        'decoyXrefOffset' => $decoyXrefOffset,
        'currentXrefOffset' => $currentXrefOffset,
        'currentPayload' => $currentPayload,
    ];
};

return [
    'repairs damaged Prev to its declared-offset xref neighborhood before unlinked decoy sections' => static function (
        TestRunner $t
    ) use ($xrefPrevChainDamagedPrevDecoySectionPdf): void {
        $fixture = $xrefPrevChainDamagedPrevDecoySectionPdf();
        $pdf = $fixture['pdf'];
        $extractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $text = $extractor->extractPlainText($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($fixture['damagedPrevOffset'] > $fixture['baseXrefOffset']);
        $t->true($fixture['damagedPrevOffset'] < $fixture['decoyXrefOffset']);
        $t->true($fixture['decoyXrefOffset'] < $fixture['currentXrefOffset']);
        $t->same(['Current damaged Prev decoy-section page', 'Declared Prev neighborhood repaired'], $extractor->extractTextLines($pdf));
        $t->same("Current damaged Prev decoy-section page\nDeclared Prev neighborhood repaired", $text);
        $t->same(['xmp', 'info', 'catalog'], $metadata['source']);
        $t->same('Current Damaged Prev Decoy Section XMP Title', $metadata['title']);
        $t->same('Damaged Prev repairs to declared-offset neighborhood', $metadata['description']);
        $t->same('Current Damaged Prev Decoy Section Info Title', $metadata['info']['Title']);
        $t->same(['Current Damaged Prev Author'], $metadata['authors']);
        $t->same('Current Damaged Prev Producer', $metadata['producer']);
        $t->same('en-US', $metadata['language']);
        $t->same('2026-06-08T16:15:23Z', $metadata['created_at_utc']);
        $t->same(1, count($files));
        $t->same('current-damaged-prev-decoy-section.xml', $files[0]['filename']);
        $t->same('Current damaged Prev decoy-section attachment', $files[0]['description']);
        $t->same($fixture['currentPayload'], $files[0]['content']);
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-damaged-prev-decoy-section.xml'], $summary['filenames']);
        $t->same(strlen($fixture['currentPayload']), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(str_contains($pdf, 'trailer' . "\n" . '<< /Size 39 /Root 30 0 R /Info 38 0 R >>'));
        $t->true(!str_contains($text, 'Unlinked decoy xref-section page'));
        $t->true(!str_contains($text, 'Previous damaged Prev decoy-section page'));
        $t->true(!str_contains($encodedMetadata, 'Decoy Damaged Prev'));
        $t->true(!str_contains($encodedFiles, 'decoy-damaged-prev'));
        $t->true(!str_contains($encodedSummary, 'decoy-damaged-prev'));
        $t->true(!str_contains($encodedMetadata, 'Previous Damaged Prev'));
        $t->true(!str_contains($encodedFiles, 'previous-damaged-prev'));
        $t->true(!str_contains($encodedSummary, 'previous-damaged-prev'));
        $t->true(!str_contains($text, "\0"));
    },
];
