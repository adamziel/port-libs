<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicVerticalTabBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Vertical-Tab XRef Title');
    $decoyXmp = $xmpPacket('Vertical-Tab Decoy XRef Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current vertical-tab xref page) Tj T* (PDF whitespace boundary kept) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Vertical-tab xref decoy page) Tj T* (VT root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-vertical-tab-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-vertical-tab-xref"/></wp-export>';
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
    $addObject(7, '<< /Title (Current Vertical-Tab XRef Info) /Author (Current VT Importer) >>');
    $addObject(8, '<< /Names [(current-vertical-tab-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-vertical-tab-xref.xml) /Desc (Current vertical-tab xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 18\n"
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
        . $xrefRow($offsets[10]);
    for ($objectNumber = 11; $objectNumber <= 16; $objectNumber++) {
        $pdf .= $xrefRow(0, 65535, 'f');
    }
    $pdf .= $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Vertical-Tab Decoy XRef Info) /Author (Decoy VT Importer) >>');
    $addObject(28, '<< /Names [(decoy-vertical-tab-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-vertical-tab-xref.xml) /Desc (Decoy vertical-tab xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $verticalTabXrefOffset = strlen($pdf);
    $pdf .= "xref\v"
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
        . "startxref\n{$verticalTabXrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $verticalTabXrefOffset];
};

return [
    'rejects vertical-tab delimited classic xref decoys before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicVerticalTabBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $verticalTabXrefOffset] = $xrefClassicVerticalTabBoundaryCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $text = $textExtractor->extractPlainText($pdf);
        $encodedReview = json_encode([$metadata, $embeddedFiles, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($verticalTabXrefOffset > $currentXrefOffset);
        $t->true(str_contains($pdf, "xref\v20 12"));
        $t->same(['Current vertical-tab xref page', 'PDF whitespace boundary kept'], $textExtractor->extractTextLines($pdf));
        $t->same(['Current vertical-tab xref page', 'PDF whitespace boundary kept'], $textExtractor->extractTextRuns($pdf));
        $t->same("Current vertical-tab xref page\nPDF whitespace boundary kept", $text);
        $t->same("Current vertical-tab xref page\nPDF whitespace boundary kept\n", $textExtractor->naiveGetText($pdf));
        $t->same(1, $textExtractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Vertical-Tab XRef Title', $metadata['title']);
        $t->same('Current Vertical-Tab XRef Info', $metadata['info']['Title']);
        $t->same('Current VT Importer', $metadata['info']['Author']);
        $t->same(1, count($embeddedFiles));
        $t->same('current-vertical-tab-xref.xml', $embeddedFiles[0]['name']);
        $t->same('current-vertical-tab-xref.xml', $embeddedFiles[0]['filename']);
        $t->same('Current vertical-tab xref attachment', $embeddedFiles[0]['description']);
        $t->same('Source', $embeddedFiles[0]['relationship']);
        $t->same($currentPayload, $embeddedFiles[0]['content']);
        $t->same($currentChecksum, $embeddedFiles[0]['checksum']);
        $t->same(true, $embeddedFiles[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-vertical-tab-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->same(true, $freeObjects[17] ?? null, 'The strict current xref table free row must stay selected.');
        $t->true(!str_contains($text, 'Vertical-tab xref decoy page'));
        $t->true(!str_contains($text, 'VT root leak'));
        $t->true(!str_contains($encodedReview, 'Vertical-Tab Decoy'));
        $t->true(!str_contains($encodedReview, 'Decoy VT Importer'));
        $t->true(!str_contains($encodedReview, 'decoy-vertical-tab-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
