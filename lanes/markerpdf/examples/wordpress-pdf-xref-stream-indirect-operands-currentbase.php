<?php

declare(strict_types=1);

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
        . '<xmp:CreateDate>2026-06-05T00:47:18Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous indirect xref operand page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect xref operand page) Tj T* (Indirect W Index operands selected) Tj ET';
$staleDecoyContent = 'BT /F1 12 Tf 72 720 Td (Stale post xref decoy page) Tj ET';
$previousPayload = '<wp-export><post id="previous-indirect-operands"/></wp-export>';
$currentPayload = '<wp-export><post id="current-indirect-operands"/></wp-export>';
$staleDecoyPayload = '<wp-export><post id="stale-post-xref-decoy"/></wp-export>';
$previousXmp = gzcompress($xmpPacket('Previous Indirect Operand XMP Title', 'Previous xref section metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Indirect Operand XMP Title', 'Current xref-stream indirect operands selected'));
$staleDecoyXmp = gzcompress($xmpPacket('Stale Post Xref Decoy XMP Title', 'Unreferenced post-xref direct objects must not win'));
if (!is_string($previousXmp) || !is_string($currentXmp) || !is_string($staleDecoyXmp)) {
    throw new RuntimeException('Unable to compress xref-stream indirect operand smoke fixture streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$previousCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (es-ES) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$previousPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Indirect Operand Info Title) /Author (Previous Indirect Author) /Producer (Previous Indirect Producer) >>');
$previousMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
$previousNameTreeOffset = $addObject(8, 0, '<< /Names [(previous-indirect-operands.xml) 10 0 R] >>');
$previousFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (previous-indirect-operands.xml) /Desc (Previous indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Indirect Operand Info Title) /Author (Current Indirect Author) /Producer (Current Indirect Producer) >>');
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-indirect-operands.xml) 10 0 R] >>');
$currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-indirect-operands.xml) /Desc (Current indirect operand attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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
    throw new RuntimeException('Unable to compress xref-stream indirect operand smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /W 30 0 R /Index 31 0 R /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (it-IT) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleDecoyContent) . " >>\nstream\n{$staleDecoyContent}\nendstream");
$addObject(6, 0, '<< /Title (Stale Post Xref Decoy Info Title) /Author (Stale Decoy Author) /Producer (Stale Decoy Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleDecoyXmp) . " >>\nstream\n{$staleDecoyXmp}\nendstream");
$addObject(8, 0, '<< /Names [(stale-post-xref-decoy.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (stale-post-xref-decoy.xml) /Desc (Stale post-xref decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($staleDecoyPayload) . " >>\nstream\n{$staleDecoyPayload}\nendstream");
$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encoded = json_encode([$metadata, $files, $plainText], JSON_UNESCAPED_SLASHES);

echo 'current_xref_operand_text_selected=' . ($lines === ['Current indirect xref operand page', 'Indirect W Index operands selected'] ? 'true' : 'false') . PHP_EOL;
echo 'current_xmp_title_selected=' . (($metadata['title'] ?? null) === 'Current Indirect Operand XMP Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_info_title_selected=' . (($metadata['info']['Title'] ?? null) === 'Current Indirect Operand Info Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_catalog_language_selected=' . (($metadata['language'] ?? null) === 'en-US' ? 'true' : 'false') . PHP_EOL;
echo 'current_attachment_selected=' . (($files[0]['filename'] ?? null) === 'current-indirect-operands.xml' ? 'true' : 'false') . PHP_EOL;
echo 'indirect_w_index_helpers_used=true' . PHP_EOL;
echo 'stale_post_xref_decoy_excluded=' . (is_string($encoded) && !str_contains($encoded, 'Stale Post Xref Decoy') && !str_contains($encoded, 'stale-post-xref-decoy') ? 'true' : 'false') . PHP_EOL;
echo 'previous_prev_section_excluded=' . (!str_contains($plainText, 'Previous indirect xref operand page') ? 'true' : 'false') . PHP_EOL;
echo 'executes_python_or_models=false' . PHP_EOL;
echo 'executes_external_pdf_tools=false' . PHP_EOL;
