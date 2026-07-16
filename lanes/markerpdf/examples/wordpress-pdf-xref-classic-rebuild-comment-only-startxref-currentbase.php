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

$staleXmp = $xmpPacket('Stale Comment-Only Startxref Title');
$currentXmp = $xmpPacket('Current Comment-Only Startxref Title');
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale comment-only startxref page) Tj T* (Comment marker blocked rebuild) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment-only startxref page) Tj T* (Comment marker ignored for rebuild) Tj ET';
$stalePayload = '<wp-export><post id="stale-comment-only-startxref"/></wp-export>';
$currentPayload = '<wp-export><post id="current-comment-only-startxref"/></wp-export>';
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
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R /Names << /EmbeddedFiles 28 0 R >> >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, $streamObject($staleContent));
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$addObject(27, '<< /Title (Stale Comment-Only Startxref Info) /Author (Stale Comment Importer) >>');
$addObject(28, '<< /Names [(stale-comment-only-startxref.xml) 30 0 R] >>');
$addObject(30, '<< /Type /Filespec /F (stale-comment-only-startxref.xml) /Desc (Stale comment-only startxref attachment) /AFRelationship /Source /EF << /F 31 0 R >> >>');
$addObject(31, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
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
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, $streamObject($currentContent));
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Comment-Only Startxref Info) /Author (Current Comment Importer) >>');
$addObject(8, '<< /Names [(current-comment-only-startxref.xml) 9 0 R] >>');
$addObject(9, '<< /Type /Filespec /F (current-comment-only-startxref.xml) /Desc (Current comment-only startxref attachment) /AFRelationship /Source /EF << /F 10 0 R >> >>');
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
    . "trailer\n<< /Size 64 /Root 1 0 R /Info 7 0 R /Prev {$previousXrefOffset} >>\n"
    . "% startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-classic-rebuild-comment-only-startxref-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-comment-only-startxref-currentbase',
    'native_boundary' => 'classic xref rebuild accepts a /Prev-linked current table whose final startxref marker is PDF-commented',
    'current_xref_after_previous_startxref' => $currentXrefOffset > $previousXrefOffset,
    'uses_current_classic_trailer_root' => str_contains($plainText, 'Current comment-only startxref page'),
    'repairs_commented_only_startxref_boundary' => str_contains($plainText, 'Comment marker ignored for rebuild'),
    'keeps_current_metadata_root' => ($metadata['title'] ?? null) === 'Current Comment-Only Startxref Title',
    'keeps_current_info_root' => ($metadata['info']['Title'] ?? null) === 'Current Comment-Only Startxref Info',
    'imports_current_attachment' => ($files[0]['name'] ?? null) === 'current-comment-only-startxref.xml',
    'current_attachment_checksum_matches' => ($files[0]['checksum_matches'] ?? null) === true,
    'attachment_summary_current' => ($attachmentSummary['filenames'] ?? []) === ['current-comment-only-startxref.xml'],
    'excludes_stale_page' => !str_contains($plainText, 'Stale comment-only startxref page'),
    'excludes_stale_metadata' => !str_contains($encodedMetadata, 'Stale Comment-Only Startxref'),
    'excludes_stale_attachment' => !str_contains($encodedFiles, 'stale-comment-only-startxref')
        && !str_contains($encodedAttachmentSummary, 'stale-comment-only-startxref'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
