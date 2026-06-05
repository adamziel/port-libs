<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicMalformedStartxrefBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };

    $staleXmp = $xmpPacket('Stale Malformed Startxref Title');
    $currentXmp = $xmpPacket('Current Malformed Startxref Title');
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale malformed-startxref page) Tj T* (Earlier numeric startxref leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed-startxref page) Tj T* (Malformed operand boundary repaired) Tj ET';
    $stalePayload = '<wp-export><post id="stale-malformed-startxref"/></wp-export>';
    $currentPayload = '<wp-export><post id="current-malformed-startxref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, '<< /Length ' . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
    $addObject(7, '<< /Title (Stale Malformed Startxref Info) /Author (Stale Startxref Importer) >>');
    $addObject(8, '<< /Names [(stale-malformed-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (stale-malformed-startxref.xml) /Desc (Stale malformed startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

    $previousXrefOffset = strlen($pdf);
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
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, '<< /Length ' . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(27, '<< /Title (Current Malformed Startxref Info) /Author (Current Startxref Importer) >>');
    $addObject(28, '<< /Names [(current-malformed-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (current-malformed-startxref.xml) /Desc (Current malformed startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
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
        . "startxref\nnot-a-byte-offset\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $previousXrefOffset, $currentXrefOffset];
};

return [
    'repairs malformed classic startxref operands to the current table boundary before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicMalformedStartxrefBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $previousXrefOffset, $currentXrefOffset] = $xrefClassicMalformedStartxrefBoundaryCurrentBasePdf();

        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current malformed-startxref page', 'Malformed operand boundary repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current malformed-startxref page', 'Malformed operand boundary repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current malformed-startxref page\nMalformed operand boundary repaired", $text);
        $t->same("Current malformed-startxref page\nMalformed operand boundary repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Malformed Startxref Title', $metadata['title']);
        $t->same('Current Malformed Startxref Info', $metadata['info']['Title']);
        $t->same('Current Startxref Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-malformed-startxref.xml', $files[0]['name']);
        $t->same('current-malformed-startxref.xml', $files[0]['filename']);
        $t->same('Current malformed startxref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-malformed-startxref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Stale malformed-startxref page'));
        $t->true(!str_contains($text, 'Earlier numeric startxref leak'));
        $t->true(!str_contains($encodedMetadata, 'Stale Malformed Startxref'));
        $t->true(!str_contains($encodedFiles, 'stale-malformed-startxref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'stale-malformed-startxref'));
        $t->true(!str_contains($text, 'not-a-byte-offset'));
        $t->true(!str_contains($text, "\0"));
    },
];
