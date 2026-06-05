<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicGenerationOffsetBoundaryCurrentBasePdf = static function (): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };

    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Generation-Offset XRef Title');
    $decoyXmp = $xmpPacket('Generation-Offset Decoy XRef Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation-offset xref page) Tj T* (Generation-offset rows repaired) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td (Generation-offset decoy xref page) Tj T* (Wrong generation root leak) Tj ET';
    $currentPayload = '<wp-export><post id="current-generation-offset-xref"/></wp-export>';
    $decoyPayload = '<wp-export><post id="decoy-generation-offset-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber][$generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, $streamObject($currentContent));
    $addObject(6, 0, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
    $addObject(7, 0, '<< /Title (Current Generation-Offset XRef Info) /Author (Current Generation Importer) >>');
    $addObject(8, 0, '<< /Names [(current-generation-offset-xref.xml) 9 0 R] >>');
    $addObject(9, 0, '<< /Type /Filespec /F (current-generation-offset-xref.xml) /Desc (Current generation-offset xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
    $addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Metadata 6 1 R /Names << /EmbeddedFiles 8 1 R >> >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
    $addObject(5, 1, $streamObject($decoyContent));
    $addObject(6, 1, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
    $addObject(7, 1, '<< /Title (Generation-Offset Decoy XRef Info) /Author (Decoy Generation Importer) >>');
    $addObject(8, 1, '<< /Names [(decoy-generation-offset-xref.xml) 9 1 R] >>');
    $addObject(9, 1, '<< /Type /Filespec /F (decoy-generation-offset-xref.xml) /Desc (Decoy generation-offset xref attachment) /AFRelationship /Source /EF << /F 10 1 R >> >>');
    $addObject(10, 1, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 11\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1][1], 0)
        . $xrefRow($offsets[2][1], 0)
        . $xrefRow($offsets[3][1], 0)
        . $xrefRow($offsets[4][0], 0)
        . $xrefRow($offsets[5][1], 0)
        . $xrefRow($offsets[6][1], 0)
        . $xrefRow($offsets[7][1], 0)
        . $xrefRow($offsets[8][1], 0)
        . $xrefRow($offsets[9][1], 0)
        . $xrefRow($offsets[10][1], 0)
        . "trailer\n<< /Size 11 /Root 1 0 R /Info 7 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $currentPayload, strtolower($currentChecksum), $xrefOffset, $offsets];
};

return [
    'repairs classic xref explicit offsets that point at same-object wrong generations' => static function (
        TestRunner $t
    ) use ($xrefClassicGenerationOffsetBoundaryCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $xrefOffset, $offsets] = $xrefClassicGenerationOffsetBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
        $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($xrefOffset > $offsets[10][1]);
        $t->true($offsets[1][1] > $offsets[1][0]);
        $t->true($offsets[6][1] > $offsets[6][0]);
        $t->true($offsets[10][1] > $offsets[10][0]);
        $t->same(['Current generation-offset xref page', 'Generation-offset rows repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current generation-offset xref page', 'Generation-offset rows repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current generation-offset xref page\nGeneration-offset rows repaired", $text);
        $t->same("Current generation-offset xref page\nGeneration-offset rows repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same('Current Generation-Offset XRef Title', $metadata['title']);
        $t->same('Current Generation-Offset XRef Info', $metadata['info']['Title']);
        $t->same('Current Generation Importer', $metadata['info']['Author']);
        $t->same(1, count($files));
        $t->same('current-generation-offset-xref.xml', $files[0]['name']);
        $t->same('current-generation-offset-xref.xml', $files[0]['filename']);
        $t->same('Current generation-offset xref attachment', $files[0]['description']);
        $t->same('Source', $files[0]['relationship']);
        $t->same($currentPayload, $files[0]['content']);
        $t->same($currentChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(['current-generation-offset-xref.xml'], $attachmentSummary['filenames']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
        $t->true(!str_contains($text, 'Generation-offset decoy xref page'));
        $t->true(!str_contains($text, 'Wrong generation root leak'));
        $t->true(!str_contains($encodedMetadata, 'Generation-Offset Decoy'));
        $t->true(!str_contains($encodedFiles, 'decoy-generation-offset-xref'));
        $t->true(!str_contains($encodedAttachmentSummary, 'decoy-generation-offset-xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
