<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicMalformedStartxrefEofBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Malformed Startxref EOF Title');
    $decoyXmp = $xmpPacket('Post EOF Malformed Startxref Decoy Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed-startxref EOF page) Tj T* (Malformed startxref EOF kept) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Post EOF malformed-startxref decoy page) Tj T* (Second EOF root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-malformed-startxref-eof"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-malformed-startxref-eof"/></wp-export>';
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
    $addObject(5, $streamObject($currentContent));
    $addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Malformed Startxref EOF Info) /Author (Current EOF Importer) >>');
    $addObject(8, '<< /Names [(current-malformed-startxref-eof.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-malformed-startxref-eof.xml) /Desc (Current malformed startxref EOF attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
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
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\nnot-a-byte-offset\n%%EOF\n";
    $currentEofOffset = strpos($pdf, '%%EOF');

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Post EOF Malformed Startxref Decoy Info) /Author (Decoy EOF Importer) >>');
    $addObject(28, '<< /Names [(decoy-malformed-startxref-eof.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-malformed-startxref-eof.xml) /Desc (Decoy malformed startxref EOF attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $decoyXrefOffset = strlen($pdf);
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
        . "%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, (int) $currentEofOffset, $decoyXrefOffset];
};

return [
    'bounds no-digit classic startxref rebuild before a later post-EOF trailer' => static function (
        TestRunner $t
    ) use ($xrefClassicMalformedStartxrefEofBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $currentEofOffset, $decoyXrefOffset] = $xrefClassicMalformedStartxrefEofBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($currentEofOffset > $currentXrefOffset);
        $t->true($decoyXrefOffset > $currentEofOffset);
        $t->same(['Current malformed-startxref EOF page', 'Malformed startxref EOF kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current malformed-startxref EOF page', 'Malformed startxref EOF kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current malformed-startxref EOF page\nMalformed startxref EOF kept", $text);
        $t->same("Current malformed-startxref EOF page\nMalformed startxref EOF kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Malformed Startxref EOF Title', $metadata['title']);
        $t->same('Current Malformed Startxref EOF Info', $metadata['info']['Title']);
        $t->same('Current EOF Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-malformed-startxref-eof.xml', $files[0]['name']);
        $t->same('current-malformed-startxref-eof.xml', $files[0]['filename']);
        $t->same('Current malformed startxref EOF attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-malformed-startxref-eof.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Post EOF malformed-startxref decoy page'));
        $t->true(!str_contains($text, 'Second EOF root leak'));
        $t->true(!str_contains($encodedMetadata, 'Post EOF Malformed Startxref Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-malformed-startxref-eof'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-malformed-startxref-eof'));
        $t->true(!str_contains($text, "\0"));
    },
];
