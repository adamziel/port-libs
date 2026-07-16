<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicLiteralPercentStartxrefCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Literal Percent StartXRef Title');
    $decoyXmp = $xmpPacket('Decoy Literal Percent StartXRef Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current literal-percent startxref page) Tj T* (Percent in string is not comment) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Decoy literal-percent startxref page) Tj T* (Percent comment leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-literal-percent-startxref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-literal-percent-startxref"/></wp-export>';
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
    $addObject(7, '<< /Title (Current Literal Percent StartXRef Info) /Author (Current Percent Importer) >>');
    $addObject(8, '<< /Names [(current-literal-percent-startxref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-literal-percent-startxref.xml) /Desc (Current literal-percent startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
        . "trailer\n<< /Size 48 /Root 1 0 R /Info 7 0 R >>\n"
        . "(producer note 100% complete before startxref) startxref\n{$currentXrefOffset}\n";

    $startxrefTokenOffset = strrpos($pdf, 'startxref');
    if (!is_int($startxrefTokenOffset)) {
        throw new RuntimeException('Unable to build literal-percent startxref fixture.');
    }

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($decoyContent));
    $addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(27, '<< /Title (Decoy Literal Percent StartXRef Info) /Author (Decoy Percent Importer) >>');
    $addObject(28, '<< /Names [(decoy-literal-percent-startxref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (decoy-literal-percent-startxref.xml) /Desc (Decoy literal-percent startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
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
        . "17 1\n"
        . $xrefRow($offsets[20])
        . "trailer\n<< /Size 48 /Root 20 0 R /Info 27 0 R >>\n"
        . "/startxref\n{$decoyXrefOffset}\n"
        . "%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $currentXrefOffset, $startxrefTokenOffset, $decoyXrefOffset];
};

return [
    'treats percent signs inside top-level literals as non-comments before classic startxref rebuild' => static function (
        TestRunner $t
    ) use ($xrefClassicLiteralPercentStartxrefCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $currentXrefOffset, $startxrefTokenOffset, $decoyXrefOffset] = $xrefClassicLiteralPercentStartxrefCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $text = $textExtractor->extractPlainText($pdf);
        $encodedReview = json_encode([$metadata, $embeddedFiles, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($currentXrefOffset > 0);
        $t->true($startxrefTokenOffset > $currentXrefOffset);
        $t->true($decoyXrefOffset > $startxrefTokenOffset);
        $t->true(str_contains($pdf, '(producer note 100% complete before startxref) startxref'));
        $t->same(['Current literal-percent startxref page', 'Percent in string is not comment'], $textExtractor->extractTextLines($pdf));
        $t->same(['Current literal-percent startxref page', 'Percent in string is not comment'], $textExtractor->extractTextRuns($pdf));
        $t->same("Current literal-percent startxref page\nPercent in string is not comment", $text);
        $t->same("Current literal-percent startxref page\nPercent in string is not comment\n", $textExtractor->naiveGetText($pdf));
        $t->same(1, $textExtractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Literal Percent StartXRef Title', $metadata['title']);
        $t->same('Current Literal Percent StartXRef Info', $metadata['info']['Title']);
        $t->same('Current Percent Importer', $metadata['info']['Author']);
        $t->same(1, count($embeddedFiles));
        $t->same('current-literal-percent-startxref.xml', $embeddedFiles[0]['name']);
        $t->same('current-literal-percent-startxref.xml', $embeddedFiles[0]['filename']);
        $t->same('Current literal-percent startxref attachment', $embeddedFiles[0]['description']);
        $t->same('Source', $embeddedFiles[0]['relationship']);
        $t->same($currentPayload, $embeddedFiles[0]['content']);
        $t->same($currentChecksum, $embeddedFiles[0]['checksum']);
        $t->same(true, $embeddedFiles[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-literal-percent-startxref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->same(true, $freeObjects[17] ?? null, 'The current classic xref free row must stay selected.');
        $t->true(!str_contains($text, 'Decoy literal-percent startxref page'));
        $t->true(!str_contains($text, 'Percent comment leak'));
        $t->true(!str_contains($encodedReview, 'Decoy Literal Percent'));
        $t->true(!str_contains($encodedReview, 'decoy-literal-percent-startxref'));
        $t->true(!str_contains($text, "\0"));
    },
];
