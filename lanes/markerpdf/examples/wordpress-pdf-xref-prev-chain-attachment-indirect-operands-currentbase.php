<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>2026-06-05T09:33:30Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$previousText = 'BT /F1 12 Tf 72 720 Td (Previous indirect operand attachment page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current indirect operand attachment page) Tj T* (Attachment preflight uses current xref operands) Tj ET';
$decoyText = 'BT /F1 12 Tf 72 720 Td (Post xref indirect operand decoy page) Tj ET';
$previousPayload = '<wp-export><post id="previous-indirect-attachment"/></wp-export>';
$currentPayload = '<wp-export><post id="current-indirect-attachment"/></wp-export>';
$decoyPayload = '<wp-export><post id="post-xref-indirect-decoy"/></wp-export>';

$previousXmp = gzcompress($xmpPacket('Previous Indirect Attachment XMP Title', 'Previous xref section must not win'));
$currentXmp = gzcompress($xmpPacket('Current Indirect Attachment XMP Title', 'Current xref-stream operands selected'));
$decoyXmp = gzcompress($xmpPacket('Post Xref Indirect Decoy XMP Title', 'Post-xref direct objects must not win'));
if (!is_string($previousXmp) || !is_string($currentXmp) || !is_string($decoyXmp)) {
    throw new RuntimeException('Unable to compress xref indirect operand smoke metadata streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$previousCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$previousPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousText) . " >>\nstream\n{$previousText}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Indirect Attachment Info Title) /Author (Previous Attachment Author) /Producer (Previous Attachment Producer) >>');
$previousMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
$previousNameTreeOffset = $addObject(8, 0, '<< /Names [(previous-indirect-attachment.xml) 10 0 R] >>');
$previousFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (previous-indirect-attachment.xml) /Desc (Previous indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$previousEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($previousPayload) . " >>\nstream\n{$previousPayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($previousCatalogOffset)
    . $xrefTableRow($previousPagesOffset)
    . $xrefTableRow($previousPageOffset)
    . $xrefTableRow($previousContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($previousInfoOffset)
    . $xrefTableRow($previousMetadataOffset)
    . $xrefTableRow($previousNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($previousFileSpecOffset)
    . $xrefTableRow($previousEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Indirect Attachment Info Title) /Author (Current Attachment Author) /Producer (Current Attachment Producer) >>');
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-indirect-attachment.xml) 10 0 R] >>');
$currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-indirect-attachment.xml) /Desc (Current indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(30, 0, '[1 4 1]');
$addObject(31, 0, '[1 8 10 2]');

$currentRows = ''
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, $currentInfoOffset, 0)
    . $xrefStreamRow(1, $currentMetadataOffset, 0)
    . $xrefStreamRow(1, $currentNameTreeOffset, 0)
    . $xrefStreamRow(1, $currentFileSpecOffset, 0)
    . $xrefStreamRow(1, $currentEmbeddedFileOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress current xref indirect operand smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /W 30 0 R /Index 31 0 R /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (it-IT) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($decoyText) . " >>\nstream\n{$decoyText}\nendstream");
$addObject(6, 0, '<< /Title (Post Xref Indirect Decoy Info Title) /Author (Decoy Attachment Author) /Producer (Decoy Attachment Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(8, 0, '<< /Names [(post-xref-indirect-decoy.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (post-xref-indirect-decoy.xml) /Desc (Post xref indirect operand decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");
$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$encoded = json_encode([
    'metadata' => $metadata,
    'embedded_files' => $embeddedFiles,
    'attachment_summary' => $attachmentSummary,
    'text' => $plainText,
], JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-prev-chain-attachment-indirect-operands-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'Attachment preflight resolves xref-stream indirect /W and /Index operands before stale /Prev and post-xref decoy attachment rows',
    'current_attachment_summary_selected' => ($attachmentSummary['filenames'] ?? []) === ['current-indirect-attachment.xml'],
    'current_embedded_file_selected' => ($embeddedFiles[0]['filename'] ?? null) === 'current-indirect-attachment.xml',
    'current_payload_selected' => ($embeddedFiles[0]['content'] ?? null) === $currentPayload,
    'current_metadata_selected' => ($metadata['title'] ?? null) === 'Current Indirect Attachment XMP Title',
    'current_text_selected' => str_contains($plainText, 'Current indirect operand attachment page'),
    'previous_prev_attachment_excluded' => !str_contains($encoded, 'previous-indirect-attachment'),
    'post_xref_decoy_attachment_excluded' => !str_contains($encoded, 'post-xref-indirect-decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF attachment review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
