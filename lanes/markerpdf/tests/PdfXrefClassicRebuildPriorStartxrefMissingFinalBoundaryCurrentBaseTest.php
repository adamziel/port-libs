<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $staleXmp = $xmpPacket('Stale Prior Startxref Title');
    $currentXmp = $xmpPacket('Current Missing Final Startxref Title');
    $decoyXmp = $xmpPacket('Post EOF Missing Final Startxref Decoy Title');
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale prior startxref page) Tj T* (Old trailer should not win) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current missing-final-startxref page) Tj T* (EOF rebuild picked current xref) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Post EOF missing-final-startxref decoy page) Tj T* (EOF leak) Tj ET';
    $stalePayload = '<wp-export><post id="stale-prior-startxref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-missing-final-startxref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-missing-final-startxref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, $streamObject($staleContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale Prior Startxref Info) /Author (Stale Importer) >>');
    $addObject(8, '<< /Names [(stale-prior-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-prior-startxref.xml) /Desc (Stale prior startxref source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $priorXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow($offsets[3])
        . $xrefRow($offsets[4])
        . $xrefRow($offsets[5])
        . $xrefRow($offsets[6])
        . $xrefRow($offsets[7])
        . $xrefRow($offsets[8])
        . $xrefRow($offsets[9])
        . $xrefRow($offsets[10])
        . "trailer\n<< /Size 11 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$priorXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($currentContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current Missing Final Startxref Info) /Author (Current Importer) >>');
    $addObject(28, '<< /Names [(current-missing-final-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-missing-final-startxref.xml) /Desc (Current missing final startxref source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "20 12\n"
        . $xrefRow($offsets[20])
        . $xrefRow($offsets[21])
        . $xrefRow($offsets[22])
        . $xrefRow($offsets[23])
        . $xrefRow($offsets[24])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[26])
        . $xrefRow($offsets[27])
        . $xrefRow($offsets[28])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "%%EOF\n";
    $finalEofOffset = strrpos($pdf, '%%EOF');

    $addObject(40, '<< /Type /Catalog /Pages 41 0 R /Metadata 46 0 R /Names << /EmbeddedFiles 48 0 R >> >>');
    $addObject(41, '<< /Type /Pages /Kids [42 0 R] /Count 1 >>');
    $addObject(42, '<< /Type /Page /Parent 41 0 R /Resources << /Font << /F1 43 0 R >> >> /Contents 44 0 R >>');
    $addObject(43, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(44, $streamObject($decoyContent));
    $addObject(46, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(47, '<< /Title (Post EOF Missing Final Startxref Decoy Info) /Author (Decoy Importer) >>');
    $addObject(48, '<< /Names [(decoy-missing-final-startxref.xml) 50 0 R] >>');
    $addObject(50, '<< /Type /Filespec /F (decoy-missing-final-startxref.xml) /Desc (Decoy missing final startxref source) /AFRelationship /Source /EF << /F 51 0 R >> >>');
    $addObject(51, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
    $decoyXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "40 12\n"
        . $xrefRow($offsets[40])
        . $xrefRow($offsets[41])
        . $xrefRow($offsets[42])
        . $xrefRow($offsets[43])
        . $xrefRow($offsets[44])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[46])
        . $xrefRow($offsets[47])
        . $xrefRow($offsets[48])
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[50])
        . $xrefRow($offsets[51])
        . "trailer\n<< /Size 52 /Root 40 0 R /Info 47 0 R >>\n";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $priorXrefOffset, $currentXrefOffset, (int) $finalEofOffset, $decoyXrefOffset];
};

return [
    'bounds rebuild after prior valid startxref when final current startxref is missing' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $priorXrefOffset, $currentXrefOffset, $finalEofOffset, $decoyXrefOffset] = $xrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($priorXrefOffset > 0);
        $t->true($currentXrefOffset > $priorXrefOffset);
        $t->true($finalEofOffset > $currentXrefOffset);
        $t->true($decoyXrefOffset > $finalEofOffset);
        $t->same(['Current missing-final-startxref page', 'EOF rebuild picked current xref'], $extractor->extractTextLines($pdf));
        $t->same(['Current missing-final-startxref page', 'EOF rebuild picked current xref'], $extractor->extractTextRuns($pdf));
        $t->same("Current missing-final-startxref page\nEOF rebuild picked current xref", $text);
        $t->same("Current missing-final-startxref page\nEOF rebuild picked current xref\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Missing Final Startxref Title', $metadata['title']);
        $t->same('Current Missing Final Startxref Info', $metadata['info']['Title']);
        $t->same('Current Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-missing-final-startxref.xml', $files[0]['name']);
        $t->same('current-missing-final-startxref.xml', $files[0]['filename']);
        $t->same('Current missing final startxref source', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-missing-final-startxref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale prior startxref page'));
        $t->true(!str_contains($text, 'Old trailer should not win'));
        $t->true(!str_contains($encodedMetadata, 'Stale Prior Startxref'));
        $t->true(!str_contains($encodedFiles, 'stale-prior-startxref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-prior-startxref'));
        $t->true(!str_contains($text, 'Post EOF missing-final-startxref decoy page'));
        $t->true(!str_contains($text, 'EOF leak'));
        $t->true(!str_contains($encodedMetadata, 'Post EOF Missing Final Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-missing-final-startxref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-missing-final-startxref'));
    },
];
