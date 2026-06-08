<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Missing Prior Startxref Title');
    $tailXmp = $xmpPacket('Private-Tail Missing Prior Decoy Title');
    $laterXmp = $xmpPacket('Later Missing Prior Decoy Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current missing-prior-startxref page) Tj T* (Private-tail section excluded) Tj ET';
    $tailContent = 'BT /F1 12 Tf 72 720 Td (Private-tail missing-prior decoy page) Tj T* (Malformed startxref xref leak) Tj ET';
    $laterContent = 'BT /F1 12 Tf 72 720 Td (Later missing-prior decoy page) Tj T* (Unbounded final EOF leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-missing-prior-startxref"/></wp-export>';
    $tailPayload = '<wp-export><post id="tail-missing-prior-startxref"/></wp-export>';
    $laterPayload = '<wp-export><post id="later-missing-prior-startxref"/></wp-export>';
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
    $addObject(7, '<< /Title (Current Missing Prior Startxref Info) /Author (Current Missing Prior Importer) >>');
    $addObject(8, '<< /Names [(current-missing-prior-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-missing-prior-startxref.xml) /Desc (Current missing prior startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 10; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber]);
    }
    $pdf .= "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R >>\n"
        . "%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($tailContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($tailXmp) . " >>\nstream\n{$tailXmp}\nendstream");
    $addObject(27, '<< /Title (Private-Tail Missing Prior Decoy Info) /Author (Tail Missing Prior Importer) >>');
    $addObject(28, '<< /Names [(tail-missing-prior-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (tail-missing-prior-startxref.xml) /Desc (Tail missing prior startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($tailPayload) . " >>\nstream\n{$tailPayload}\nendstream");

    $tailXrefOffset = strlen($pdf);
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
        . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n{$tailXrefOffset} /PrivateTail 20 0 R\n";

    $addObject(40, '<< /Type /Catalog /Pages 41 0 R /Metadata 46 0 R /Names << /EmbeddedFiles 48 0 R >> >>');
    $addObject(41, '<< /Type /Pages /Kids [42 0 R] /Count 1 >>');
    $addObject(42, '<< /Type /Page /Parent 41 0 R /Resources << /Font << /F1 43 0 R >> >> /Contents 44 0 R >>');
    $addObject(43, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(44, $streamObject($laterContent));
    $addObject(46, "<< /Type /Metadata /Subtype /XML /Length " . strlen($laterXmp) . " >>\nstream\n{$laterXmp}\nendstream");
    $addObject(47, '<< /Title (Later Missing Prior Decoy Info) /Author (Later Missing Prior Importer) >>');
    $addObject(48, '<< /Names [(later-missing-prior-startxref.xml) 50 0 R] >>');
    $addObject(50, '<< /Type /Filespec /F (later-missing-prior-startxref.xml) /Desc (Later missing prior startxref attachment) /AFRelationship /Source /EF << /F 51 0 R >> >>');
    $addObject(51, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($laterPayload) . " >>\nstream\n{$laterPayload}\nendstream");

    $laterXrefOffset = strlen($pdf);
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
        . "trailer\n<< /Size 64 /Root 40 0 R /Info 47 0 R >>\n"
        . "%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $tailXrefOffset, $laterXrefOffset];
};

return [
    'bounds rejected private-tail startxref sections when no prior valid startxref exists' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $tailXrefOffset, $laterXrefOffset] = $xrefClassicRebuildPrivateTailMissingPriorStartxrefCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedReview = json_encode([$metadata, $files, $attachmentSummary], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($tailXrefOffset > $currentXrefOffset);
        $t->true($laterXrefOffset > $tailXrefOffset);
        $t->true(str_contains($pdf, "startxref\n{$tailXrefOffset} /PrivateTail 20 0 R"));
        $t->same(['Current missing-prior-startxref page', 'Private-tail section excluded'], $extractor->extractTextLines($pdf));
        $t->same(['Current missing-prior-startxref page', 'Private-tail section excluded'], $extractor->extractTextRuns($pdf));
        $t->same("Current missing-prior-startxref page\nPrivate-tail section excluded", $text);
        $t->same("Current missing-prior-startxref page\nPrivate-tail section excluded\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Missing Prior Startxref Title', $metadata['title']);
        $t->same('Current Missing Prior Startxref Info', $metadata['info']['Title']);
        $t->same('Current Missing Prior Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-missing-prior-startxref.xml', $files[0]['name']);
        $t->same('current-missing-prior-startxref.xml', $files[0]['filename']);
        $t->same('Current missing prior startxref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-missing-prior-startxref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Private-tail missing-prior decoy page'));
        $t->true(!str_contains($text, 'Malformed startxref xref leak'));
        $t->true(!str_contains($text, 'Later missing-prior decoy page'));
        $t->true(!str_contains($text, 'Unbounded final EOF leak'));
        $t->true(!str_contains($encodedReview, 'Private-Tail Missing Prior'));
        $t->true(!str_contains($encodedReview, 'Later Missing Prior'));
        $t->true(!str_contains($encodedReview, 'tail-missing-prior-startxref'));
        $t->true(!str_contains($encodedReview, 'later-missing-prior-startxref'));
        $t->true(!str_contains($text, "\0"));
    },
];
