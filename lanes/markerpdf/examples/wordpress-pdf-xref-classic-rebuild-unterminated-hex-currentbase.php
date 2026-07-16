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

$decoyXrefOffset = strlen($pdf) + strlen("< dangling hex import tail\n");
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

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$encodedReview = json_encode([$metadata, $embeddedFiles, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';
$flags = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-unterminated-hex-currentbase',
    'native_boundary' => 'self-pointing classic xref tables inside unterminated hex import tails stay unselectable',
    'current_xref_offset' => $currentXrefOffset,
    'decoy_xref_offset' => $decoyXrefOffset,
    'uses_current_classic_trailer_root' => str_contains($plainText, 'Current unterminated-hex xref page'),
    'keeps_current_metadata_root' => ($metadata['title'] ?? null) === 'Current Unterminated Hex XRef Title',
    'keeps_current_attachment' => ($embeddedFiles[0]['name'] ?? null) === 'current-unterminated-hex-xref.xml',
    'keeps_current_free_row' => ($freeObjects[17] ?? false) === true,
    'excludes_decoy_xref_text' => !str_contains($plainText, 'Unterminated hex xref decoy page'),
    'excludes_decoy_metadata' => !str_contains($encodedReview, 'Unterminated Hex XRef Decoy'),
    'excludes_decoy_attachment' => !str_contains($encodedReview, 'decoy-unterminated-hex-xref'),
    'excludes_decoy_free_row' => !isset($freeObjects[29]),
    'attachment_count' => $attachmentSummary['attachment_count'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$expectedFlags = [
    'uses_current_classic_trailer_root' => true,
    'keeps_current_metadata_root' => true,
    'keeps_current_attachment' => true,
    'keeps_current_free_row' => true,
    'excludes_decoy_xref_text' => true,
    'excludes_decoy_metadata' => true,
    'excludes_decoy_attachment' => true,
    'excludes_decoy_free_row' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];
foreach ($expectedFlags as $key => $expected) {
    if (($flags[$key] ?? null) !== $expected) {
        throw new RuntimeException('Expected unterminated-hex xref smoke flag to match: ' . $key);
    }
}

echo '<!-- markerpdf-xref-classic-rebuild-unterminated-hex-currentbase-smoke '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
