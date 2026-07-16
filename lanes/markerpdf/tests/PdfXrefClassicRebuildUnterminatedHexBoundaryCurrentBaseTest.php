<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicRebuildUnterminatedHexBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Unterminated Hex XRef Title');
    $decoyXmp = $xmpPacket('Unterminated Hex XRef Decoy Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current unterminated-hex xref page) Tj T* (Dangling hex xref skipped) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Unterminated hex xref decoy page) Tj T* (Hex tail root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-unterminated-hex-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-unterminated-hex-xref"/></wp-export>';
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
    $addObject(6, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, '<< /Title (Current Unterminated Hex XRef Info) /Author (Current Hex Importer) >>');
    $addObject(8, '<< /Names [(current-unterminated-hex-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-unterminated-hex-xref.xml) /Desc (Current unterminated hex xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
        . "17 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R >>\n"
        . "%%EOF\n";
    $currentEofOffset = strpos($pdf, '%%EOF');

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Unterminated Hex XRef Decoy Info) /Author (Hex Decoy Importer) >>');
    $addObject(28, '<< /Names [(decoy-unterminated-hex-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-unterminated-hex-xref.xml) /Desc (Decoy unterminated hex xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $unterminatedHexOffset = strlen($pdf);
    $decoyXrefOffset = $unterminatedHexOffset + strlen("< dangling hex import tail\n");
    $pdf .= "< dangling hex import tail\n"
        . "xref\n"
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
        . $xrefRow(0, 2, 'f')
        . $xrefRow($offsets[30])
        . $xrefRow($offsets[31])
        . "trailer\n<< /Size 64 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n{$decoyXrefOffset}\n%%EOF";

    return [
        $pdf,
        $currentPayload,
        strtolower($currentChecksum),
        $currentXrefOffset,
        (int) $currentEofOffset,
        $unterminatedHexOffset,
        $decoyXrefOffset,
    ];
};

return [
    'skips unterminated hex-string xref decoys before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildUnterminatedHexBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $currentEofOffset, $unterminatedHexOffset, $decoyXrefOffset] = $xrefClassicRebuildUnterminatedHexBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $encodedReview = json_encode([$metadata, $files, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($currentEofOffset > $currentXrefOffset);
        $t->true($unterminatedHexOffset > $currentEofOffset);
        $t->true($decoyXrefOffset > $unterminatedHexOffset);
        $t->same(['Current unterminated-hex xref page', 'Dangling hex xref skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current unterminated-hex xref page', 'Dangling hex xref skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current unterminated-hex xref page\nDangling hex xref skipped", $text);
        $t->same("Current unterminated-hex xref page\nDangling hex xref skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Unterminated Hex XRef Title', $metadata['title']);
        $t->same('Current Unterminated Hex XRef Info', $metadata['info']['Title']);
        $t->same('Current Hex Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-unterminated-hex-xref.xml', $files[0]['name']);
        $t->same('current-unterminated-hex-xref.xml', $files[0]['filename']);
        $t->same('Current unterminated hex xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-unterminated-hex-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->same(true, $freeObjects[17] ?? null, 'Current free rows must stay selected before the dangling hex tail.');
        $t->true(!isset($freeObjects[29]), 'Free rows inside the dangling hex tail must stay ignored.');
        $t->true(!str_contains($text, 'Unterminated hex xref decoy page'));
        $t->true(!str_contains($text, 'Hex tail root leak'));
        $t->true(!str_contains($encodedReview, 'Unterminated Hex XRef Decoy'));
        $t->true(!str_contains($encodedReview, 'decoy-unterminated-hex-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
