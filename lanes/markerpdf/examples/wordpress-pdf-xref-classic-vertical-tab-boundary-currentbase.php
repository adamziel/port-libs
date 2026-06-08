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

$currentXmp = $xmpPacket('Current Vertical-Tab XRef Title');
$decoyXmp = $xmpPacket('Vertical-Tab Decoy XRef Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current vertical-tab xref page) Tj T* (PDF whitespace boundary kept) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Vertical-tab xref decoy page) Tj T* (VT root leak) Tj ET';
$currentPayload = '<wp-export><post id="current-vertical-tab-xref"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-vertical-tab-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
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

$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$encodedReview = json_encode([$metadata, $embeddedFiles, $attachmentSummary, $freeObjects], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'scenario' => 'wordpress-pdf-xref-classic-vertical-tab-boundary-currentbase',
    'native_boundary' => 'classic xref rebuild rejects vertical-tab-delimited xref pseudo-tables because vertical tab is not PDF whitespace',
    'current_xref_offset' => $currentXrefOffset,
    'vertical_tab_xref_offset' => $verticalTabXrefOffset,
    'uses_current_page_text' => str_contains($plainText, 'Current vertical-tab xref page'),
    'vertical_tab_xref_decoy_rejected' => !str_contains($plainText, 'Vertical-tab xref decoy page')
        && !str_contains($encodedReview, 'Vertical-Tab Decoy'),
    'metadata_title_current' => ($metadata['title'] ?? null) === 'Current Vertical-Tab XRef Title',
    'info_title_current' => ($metadata['info']['Title'] ?? null) === 'Current Vertical-Tab XRef Info',
    'embedded_file_current' => ($embeddedFiles[0]['name'] ?? null) === 'current-vertical-tab-xref.xml',
    'attachment_summary_current' => ($attachmentSummary['filenames'] ?? []) === ['current-vertical-tab-xref.xml'],
    'free_row_current' => ($freeObjects[17] ?? null) === true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['uses_current_page_text']
    || !$summary['vertical_tab_xref_decoy_rejected']
    || !$summary['metadata_title_current']
    || !$summary['info_title_current']
    || !$summary['embedded_file_current']
    || !$summary['attachment_summary_current']
    || !$summary['free_row_current']
) {
    throw new RuntimeException('Expected vertical-tab-delimited xref decoy to stay outside PDF parser whitespace.');
}

echo '<!-- markerpdf-xref-classic-vertical-tab-boundary-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
