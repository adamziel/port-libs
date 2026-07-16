<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicStartxrefOperandTailBoundaryCurrentBasePdf = static function (string $startxrefTail): array {
    $xmpPacket = static function (string $title): string {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    };
    $streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $currentXmp = $xmpPacket('Current Operand-Tail XRef Title');
    $tailXmp = $xmpPacket('Tail Operand XRef Title');
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current operand-tail xref page) Tj T* (Private startxref tail rejected) Tj ET';
    $tailContent = 'BT /F1 12 Tf 72 720 Td (Tail operand xref page) Tj T* (Comment startxref tail accepted) Tj ET';
    $currentPayload = '<wp-export><post id="current-operand-tail-xref"/></wp-export>';
    $tailPayload = '<wp-export><post id="tail-operand-xref"/></wp-export>';
    $currentChecksum = strtoupper(hash('md5', $currentPayload));
    $tailChecksum = strtoupper(hash('md5', $tailPayload));

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
    $addObject(7, '<< /Title (Current Operand-Tail XRef Info) /Author (Current Tail Importer) >>');
    $addObject(8, '<< /Names [(current-operand-tail-xref.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-operand-tail-xref.xml) /Desc (Current operand-tail xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
        . "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
    $addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
    $addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
    $addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(24, $streamObject($tailContent));
    $addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($tailXmp) . " >>\nstream\n{$tailXmp}\nendstream");
    $addObject(27, '<< /Title (Tail Operand XRef Info) /Author (Tail Importer) >>');
    $addObject(28, '<< /Names [(tail-operand-xref.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (tail-operand-xref.xml) /Desc (Tail operand xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
    $addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($tailPayload) . ' /CheckSum <' . $tailChecksum . "> >> /Length " . strlen($tailPayload) . " >>\nstream\n{$tailPayload}\nendstream");

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
        . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
        . "startxref\n{$tailXrefOffset}{$startxrefTail}\n%%EOF";

    return [
        $pdf,
        [
            'content' => $currentPayload,
            'checksum' => strtolower($currentChecksum),
            'lines' => ['Current operand-tail xref page', 'Private startxref tail rejected'],
            'text' => "Current operand-tail xref page\nPrivate startxref tail rejected",
            'title' => 'Current Operand-Tail XRef Title',
            'info_title' => 'Current Operand-Tail XRef Info',
            'author' => 'Current Tail Importer',
            'filename' => 'current-operand-tail-xref.xml',
            'description' => 'Current operand-tail xref attachment',
            'excluded' => 'Tail Operand',
            'excluded_file' => 'tail-operand-xref',
        ],
        [
            'content' => $tailPayload,
            'checksum' => strtolower($tailChecksum),
            'lines' => ['Tail operand xref page', 'Comment startxref tail accepted'],
            'text' => "Tail operand xref page\nComment startxref tail accepted",
            'title' => 'Tail Operand XRef Title',
            'info_title' => 'Tail Operand XRef Info',
            'author' => 'Tail Importer',
            'filename' => 'tail-operand-xref.xml',
            'description' => 'Tail operand xref attachment',
            'excluded' => 'Current Operand',
            'excluded_file' => 'current-operand-tail-xref',
        ],
        $currentXrefOffset,
        $tailXrefOffset,
    ];
};

$assertOperandTailSelection = static function (TestRunner $t, string $pdf, array $expected, int $currentXrefOffset, int $tailXrefOffset): void {
    $extractor = new PdfTextExtractor();
    $text = $extractor->extractPlainText($pdf);
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
    $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
    $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
    $encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
    $encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

    $t->true($currentXrefOffset > 0);
    $t->true($tailXrefOffset > $currentXrefOffset);
    $t->same($expected['lines'], $extractor->extractTextLines($pdf));
    $t->same($expected['lines'], $extractor->extractTextRuns($pdf));
    $t->same($expected['text'], $text);
    $t->same($expected['text'] . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same($expected['title'], $metadata['title']);
    $t->same($expected['info_title'], $metadata['info']['Title']);
    $t->same($expected['author'], $metadata['info']['Author']);
    $t->same(1, count($files));
    $t->same($expected['filename'], $files[0]['name']);
    $t->same($expected['filename'], $files[0]['filename']);
    $t->same($expected['description'], $files[0]['description']);
    $t->same('Source', $files[0]['relationship']);
    $t->same($expected['content'], $files[0]['content']);
    $t->same($expected['checksum'], $files[0]['checksum']);
    $t->same(true, $files[0]['checksum_matches']);
    $t->same(1, $attachmentSummary['attachment_count']);
    $t->same([$expected['filename']], $attachmentSummary['filenames']);
    $t->same(strlen($expected['content']), $attachmentSummary['total_bytes']);
    $t->same(false, $attachmentSummary['executes_python_or_models']);
    $t->same(false, $attachmentSummary['executes_external_pdf_tools']);
    $t->true(!str_contains($text, $expected['excluded']));
    $t->true(!str_contains($encodedMetadata, $expected['excluded']));
    $t->true(!str_contains($encodedFiles, $expected['excluded_file']));
    $t->true(!str_contains($encodedAttachmentSummary, $expected['excluded_file']));
    $t->true(!str_contains($text, "\0"));
};

return [
    'rejects numeric classic startxref operands with private tails before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicStartxrefOperandTailBoundaryCurrentBasePdf, $assertOperandTailSelection): void {
        [$pdf, $currentExpected, , $currentXrefOffset, $tailXrefOffset] = $xrefClassicStartxrefOperandTailBoundaryCurrentBasePdf(' /PrivateTail 20 0 R');

        $assertOperandTailSelection($t, $pdf, $currentExpected, $currentXrefOffset, $tailXrefOffset);
    },
    'accepts numeric classic startxref operands with PDF comment tails before WordPress imports' => static function (
        TestRunner $t
    ) use ($xrefClassicStartxrefOperandTailBoundaryCurrentBasePdf, $assertOperandTailSelection): void {
        [$pdf, , $tailExpected, $currentXrefOffset, $tailXrefOffset] = $xrefClassicStartxrefOperandTailBoundaryCurrentBasePdf(' % comment-only startxref operand tail');

        $assertOperandTailSelection($t, $pdf, $tailExpected, $currentXrefOffset, $tailXrefOffset);
    },
];
