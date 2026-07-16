<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};

$buildPdf = static function (string $startxrefTail) use ($streamObject, $xrefRow, $xmpPacket): array {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current Operand Tail Import) Tj T* (Private startxref tail rejected) Tj ET';
    $tailContent = 'BT /F1 12 Tf 72 720 Td (Tail Operand Import) Tj T* (Comment startxref tail accepted) Tj ET';
    $currentXmp = $xmpPacket('Current Operand Tail Import');
    $tailXmp = $xmpPacket('Tail Operand Import');
    $currentPayload = '<wp-export><post id="current-operand-tail-import"/></wp-export>';
    $tailPayload = '<wp-export><post id="tail-operand-import"/></wp-export>';
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
    $addObject(7, '<< /Title (Current Operand Tail Info) /Author (Current Importer) >>');
    $addObject(8, '<< /Names [(current-operand-tail-import.xml) 9 0 R] >>');
    $addObject(9, '<< /Type /Filespec /F (current-operand-tail-import.xml) /Desc (Current operand tail source) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
    $addObject(27, '<< /Title (Tail Operand Info) /Author (Tail Importer) >>');
    $addObject(28, '<< /Names [(tail-operand-import.xml) 30 0 R] >>');
    $addObject(30, '<< /Type /Filespec /F (tail-operand-import.xml) /Desc (Tail operand source) /AFRelationship /Source /EF << /F 31 0 R >> >>');
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

    return [$pdf, $currentXrefOffset, $tailXrefOffset];
};

$summarize = static function (string $pdf): array {
    $extractor = new PdfTextExtractor();
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
    $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
    $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

    return [
        'lines' => $lines,
        'plain_text' => $plainText,
        'metadata_title' => $metadata['title'] ?? null,
        'embedded_file' => $files[0]['name'] ?? null,
        'attachment_filenames' => $attachmentSummary['filenames'] ?? [],
    ];
};

[$privatePdf, $privateCurrentXrefOffset, $privateTailXrefOffset] = $buildPdf(' /PrivateTail 20 0 R');
[$commentPdf, $commentCurrentXrefOffset, $commentTailXrefOffset] = $buildPdf(' % comment-only startxref operand tail');
$private = $summarize($privatePdf);
$comment = $summarize($commentPdf);

$privateTailRejected = $private['lines'] === ['Current Operand Tail Import', 'Private startxref tail rejected']
    && $private['metadata_title'] === 'Current Operand Tail Import'
    && $private['embedded_file'] === 'current-operand-tail-import.xml'
    && $private['attachment_filenames'] === ['current-operand-tail-import.xml']
    && !str_contains($private['plain_text'], 'Tail Operand Import');
$commentTailAccepted = $comment['lines'] === ['Tail Operand Import', 'Comment startxref tail accepted']
    && $comment['metadata_title'] === 'Tail Operand Import'
    && $comment['embedded_file'] === 'tail-operand-import.xml'
    && $comment['attachment_filenames'] === ['tail-operand-import.xml']
    && !str_contains($comment['plain_text'], 'Current Operand Tail Import');

if (!$privateTailRejected || !$commentTailAccepted) {
    throw new RuntimeException('Expected numeric startxref private tails to be rejected while PDF comment tails remain valid.');
}

echo '<!-- markerpdf-classic-startxref-operand-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-repair',
    'support_component' => 'native-pdf-startxref-operand-boundary',
    'native_boundary' => 'numeric startxref operands must end at PDF whitespace/comment boundaries before WordPress import roots are selected',
    'private_tail_lines' => $private['lines'],
    'comment_tail_lines' => $comment['lines'],
    'private_tail_rejected' => $privateTailRejected,
    'comment_tail_accepted' => $commentTailAccepted,
    'private_current_xref_offset' => $privateCurrentXrefOffset,
    'private_tail_xref_offset' => $privateTailXrefOffset,
    'comment_current_xref_offset' => $commentCurrentXrefOffset,
    'comment_tail_xref_offset' => $commentTailXrefOffset,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($private['lines'] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
