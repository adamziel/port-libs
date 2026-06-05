<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};

$currentXmp = $xmpPacket('Current Row-State XRef Title');
$decoyXmp = $xmpPacket('Punctuation Row Decoy Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current row-state xref page) Tj T* (Punctuation row skipped) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Punctuation row decoy page) Tj T* (Row-state root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-row-state-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-row-state-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$streamObject = static fn (string $content): string => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
$xrefRow = static fn (
    int $offset,
    int $generation = 0,
    string $state = 'n',
    string $suffix = ' '
): string => sprintf("%010d %05d %s%s\n", $offset, $generation, $state, $suffix);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Row-State XRef Info) /Author (Current Row-State Importer) >>');
$addObject(8, '<< /Names [(current-row-state-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-row-state-xref.xml) /Desc (Current row-state xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1], 0, 'n', ' % valid row comment')
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow($offsets[7])
    . $xrefRow($offsets[8])
    . $xrefRow($offsets[9])
    . $xrefRow($offsets[10])
    . "trailer\n<< /Size 32 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($decoyContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(27, '<< /Title (Punctuation Row Decoy Info) /Author (Punctuation Row Decoy Importer) >>');
$addObject(28, '<< /Names [(decoy-row-state-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (decoy-row-state-xref.xml) /Desc (Decoy row-state xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$punctuationXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "20 12\n"
    . $xrefRow($offsets[20], 0, 'n', '.bad')
    . $xrefRow($offsets[21], 0, 'n', '.bad')
    . $xrefRow($offsets[22], 0, 'n', '.bad')
    . $xrefRow($offsets[23], 0, 'n', '.bad')
    . $xrefRow($offsets[24], 0, 'n', '.bad')
    . $xrefRow(0, 65535, 'f', '.bad')
    . $xrefRow($offsets[26], 0, 'n', '.bad')
    . $xrefRow($offsets[27], 0, 'n', '.bad')
    . $xrefRow($offsets[28], 0, 'n', '.bad')
    . $xrefRow(0, 65535, 'f', '.bad')
    . $xrefRow($offsets[30], 0, 'n', '.bad')
    . $xrefRow($offsets[31], 0, 'n', '.bad')
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$paragraphs = array_values(array_filter(array_map('trim', explode("\n", $plainText))));
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-classic-row-state-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'classic xref rebuild rejects row states with punctuation suffixes',
    'current_xref_offset' => $currentXrefOffset,
    'punctuation_xref_offset' => $punctuationXrefOffset,
    'uses_current_page_text' => str_contains($plainText, 'Current row-state xref page'),
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Row-State XRef Title',
    'embedded_file_current' => ($files[0]['name'] ?? null) === 'current-row-state-xref.xml',
    'attachment_summary_current' => ($attachmentSummary['filenames'][0] ?? null) === 'current-row-state-xref.xml',
    'rejects_punctuation_row_state_table' => !str_contains($plainText, 'Punctuation row decoy page')
        && !str_contains($encodedMetadata, 'Punctuation Row Decoy')
        && !str_contains($encodedFiles, 'decoy-row-state-xref'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
