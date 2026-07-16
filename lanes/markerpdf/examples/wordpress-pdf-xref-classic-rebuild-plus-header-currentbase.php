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
$staleXmp = $xmpPacket('Stale Plus Header XRef Title');
$currentXmp = $xmpPacket('Current Plus Header XRef Title');
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale plus-header xref page) Tj T* (Old plus-header trailer leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current plus-header xref page) Tj T* (Plus-signed subsection header repaired) Tj ET';
$stalePayload = '<wp-export><post id="stale-plus-header-xref"/></wp-export>';
$currentPayload = '<wp-export><post id="current-plus-header-xref"/></wp-export>';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-1.4\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$addObject(7, '<< /Title (Stale Plus Header XRef Info) /Author (Stale Plus Importer) >>');
$addObject(8, '<< /Names [(stale-plus-header-xref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (stale-plus-header-xref.xml) /Desc (Stale plus-header xref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
$addObject(10, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
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
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(27, '<< /Title (Current Plus Header XRef Info) /Author (Current Plus Importer) >>');
$addObject(28, '<< /Names [(current-plus-header-xref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (current-plus-header-xref.xml) /Desc (Current plus-header xref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "+20 +12\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[26])
    . $xrefRow($offsets[27])
    . $xrefRow($offsets[28])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[30])
    . $xrefRow($offsets[31])
    . "trailer\n<< /Size 32 /Root 20 0 R /Info 27 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';
$currentSelected = str_contains($plainText, 'Current plus-header xref page')
    && ($metadata['title'] ?? null) === 'Current Plus Header XRef Title'
    && ($files[0]['name'] ?? null) === 'current-plus-header-xref.xml';
$staleExcluded = !str_contains($plainText, 'Stale plus-header xref page')
    && !str_contains($encodedMetadata, 'Stale Plus Header')
    && !str_contains($encodedFiles, 'stale-plus-header-xref')
    && !str_contains($encodedAttachmentSummary, 'stale-plus-header-xref');

if (!$currentSelected || !$staleExcluded) {
    throw new RuntimeException('Classic xref plus-header rebuild failed to select current WordPress import boundary.');
}

echo '<!-- markerpdf-xref-classic-rebuild-plus-header-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'classic xref rebuild accepts non-negative plus-signed subsection headers before current trailer root selection',
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'plus_signed_subsection_header_accepted' => true,
    'uses_current_classic_trailer_root' => $currentSelected,
    'keeps_current_metadata_root' => ($metadata['title'] ?? null) === 'Current Plus Header XRef Title',
    'keeps_current_info_root' => ($metadata['info']['Title'] ?? null) === 'Current Plus Header XRef Info',
    'imports_current_attachment' => ($files[0]['name'] ?? null) === 'current-plus-header-xref.xml',
    'current_attachment_checksum_matches' => ($files[0]['checksum_matches'] ?? null) === true,
    'stale_previous_xref_excluded' => $staleExcluded,
    'attachment_count' => $attachmentSummary['attachment_count'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
