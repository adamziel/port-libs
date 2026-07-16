<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};
$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$currentPayload = '<wp-export><post id="current-private-tail-eof"/></wp-export>';
$tailPayload = '<wp-export><post id="tail-private-tail-eof"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-private-tail-eof"/></wp-export>';
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
$addObject(5, $streamObject('BT /F1 12 Tf 72 720 Td (Current private-tail EOF page) Tj T* (Rejected tail stays bounded) Tj ET'));
$addObject(6, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmpPacket('Current Private-Tail EOF Title')) . " >>\nstream\n" . $xmpPacket('Current Private-Tail EOF Title') . "\nendstream");
$addObject(7, '<< /Title (Current Private-Tail EOF Info) /Author (Current EOF Importer) >>');
$addObject(8, '<< /Names [(current-private-tail-eof.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-private-tail-eof.xml) /Desc (Current private-tail EOF attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
    . "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject('BT /F1 12 Tf 72 720 Td (Rejected private-tail xref page) Tj T* (Private tail root leak) Tj ET'));
$addObject(26, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmpPacket('Rejected Private-Tail EOF Title')) . " >>\nstream\n" . $xmpPacket('Rejected Private-Tail EOF Title') . "\nendstream");
$addObject(27, '<< /Title (Rejected Private-Tail EOF Info) /Author (Tail EOF Importer) >>');
$addObject(28, '<< /Names [(tail-private-tail-eof.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (tail-private-tail-eof.xml) /Desc (Tail private-tail EOF attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($tailPayload) . " >>\nstream\n{$tailPayload}\nendstream");

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
    . "startxref\n{$tailXrefOffset} /PrivateTail 20 0 R\n%%EOF\n";
$tailEofOffset = strrpos($pdf, '%%EOF');

$addObject(40, '<< /Type /Catalog /Pages 41 0 R /Metadata 46 0 R /Names << /EmbeddedFiles 48 0 R >> >>');
$addObject(41, '<< /Type /Pages /Kids [42 0 R] /Count 1 >>');
$addObject(42, '<< /Type /Page /Parent 41 0 R /Resources << /Font << /F1 43 0 R >> >> /Contents 44 0 R >>');
$addObject(43, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(44, $streamObject('BT /F1 12 Tf 72 720 Td (Post EOF private-tail decoy page) Tj T* (Later EOF root leak) Tj ET'));
$addObject(46, '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xmpPacket('Post EOF Private-Tail Decoy Title')) . " >>\nstream\n" . $xmpPacket('Post EOF Private-Tail Decoy Title') . "\nendstream");
$addObject(47, '<< /Title (Post EOF Private-Tail Decoy Info) /Author (Decoy EOF Importer) >>');
$addObject(48, '<< /Names [(decoy-private-tail-eof.xml) 50 0 R] >>');
$addObject(50, '<< /Type /Filespec /F (decoy-private-tail-eof.xml) /Desc (Decoy private-tail EOF attachment) /AFRelationship /Source /EF << /F 51 0 R >> >>');
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
    . "trailer\n<< /Size 64 /Root 40 0 R /Info 47 0 R >>\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));
$encodedReview = json_encode([$metadata, $files, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';

$privateTailEofBounded = $paragraphs === ['Current private-tail EOF page', 'Rejected tail stays bounded']
    && ($metadata['title'] ?? null) === 'Current Private-Tail EOF Title'
    && ($metadata['info']['Title'] ?? null) === 'Current Private-Tail EOF Info'
    && ($files[0]['name'] ?? null) === 'current-private-tail-eof.xml'
    && ($files[0]['checksum_matches'] ?? null) === true
    && ($attachmentSummary['filenames'] ?? []) === ['current-private-tail-eof.xml']
    && ($freeObjects[17] ?? null) === true
    && !str_contains($encodedReview, 'tail-private-tail-eof')
    && !str_contains($encodedReview, 'decoy-private-tail-eof')
    && !str_contains($plainText, 'Post EOF private-tail decoy page');

if (!$privateTailEofBounded) {
    throw new RuntimeException('Expected private-tail startxref EOF boundary to keep the last valid current xref revision.');
}

echo '<!-- markerpdf-xref-classic-private-tail-eof-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-private-tail-eof-currentbase',
    'native_boundary' => 'rejected numeric startxref private-tail revisions bound classic rebuild scans before post-EOF xref garbage',
    'uses_current_page_text' => str_contains($plainText, 'Current private-tail EOF page'),
    'rejects_private_tail_revision' => !str_contains($encodedReview, 'tail-private-tail-eof'),
    'excludes_post_eof_decoy' => !str_contains($encodedReview, 'decoy-private-tail-eof') && !str_contains($plainText, 'Post EOF private-tail decoy page'),
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Private-Tail EOF Title',
    'info_title_current' => ($metadata['info']['Title'] ?? null) === 'Current Private-Tail EOF Info',
    'embedded_file_current' => ($files[0]['name'] ?? null) === 'current-private-tail-eof.xml',
    'free_row_current' => ($freeObjects[17] ?? null) === true,
    'current_xref_offset' => $currentXrefOffset,
    'tail_xref_offset' => $tailXrefOffset,
    'tail_eof_offset' => $tailEofOffset,
    'decoy_xref_offset' => $decoyXrefOffset,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
