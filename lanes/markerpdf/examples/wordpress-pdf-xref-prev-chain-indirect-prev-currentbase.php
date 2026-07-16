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
        . '<xmp:CreateDate>2026-06-04T23:40:38Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev helper page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev helper page) Tj T* (Indirect Prev offset repaired) Tj ET';
$stalePayload = '<wp-export><post id="stale-indirect-prev"/></wp-export>';
$currentPayload = '<wp-export><post id="current-indirect-prev"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Indirect Prev XMP Title', 'Stale indirect Prev metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Indirect Prev XMP Title', 'Current classic table indirect Prev offsets repaired'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress indirect Prev smoke fixture streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Indirect Prev Info Title) /Author (Stale Indirect Author) /Producer (Stale Indirect Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-indirect-prev.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-indirect-prev.xml) /Desc (Stale indirect Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$staleEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($staleCatalogOffset)
    . $xrefRow($stalePagesOffset)
    . $xrefRow($stalePageOffset)
    . $xrefRow($staleContentOffset)
    . $xrefRow($fontOffset)
    . $xrefRow($staleInfoOffset)
    . $xrefRow($staleMetadataOffset)
    . $xrefRow($staleNameTreeOffset)
    . $xrefRow(0, 0, 'f')
    . $xrefRow($staleFileSpecOffset)
    . $xrefRow($staleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$addObject(6, 0, '<< /Title (Current Indirect Prev Info Title) /Author (Current Indirect Author) /Producer (Current Indirect Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /Names [(current-indirect-prev.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-indirect-prev.xml) /Desc (Current indirect Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "1 4\n"
    . $xrefRow(0)
    . $xrefRow(0)
    . $xrefRow(0)
    . $xrefRow(0)
    . "6 3\n"
    . $xrefRow(0)
    . $xrefRow(0)
    . $xrefRow(0)
    . "10 2\n"
    . $xrefRow(0)
    . $xrefRow(0)
    . "30 1\n"
    . $xrefRow($prevHelperOffset)
    . "trailer\n<< /Size 31 /Root 1 0 R /Info 6 0 R /Prev 30 0 R >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$text = $extractor->extractPlainText($pdf);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

echo 'current_xmp_title_selected=' . (($metadata['title'] ?? null) === 'Current Indirect Prev XMP Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_info_title_selected=' . (($metadata['info']['Title'] ?? null) === 'Current Indirect Prev Info Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_catalog_language_selected=' . (($metadata['language'] ?? null) === 'en-US' ? 'true' : 'false') . PHP_EOL;
echo 'current_page_text_selected=' . (str_contains($text, 'Current indirect Prev helper page') ? 'true' : 'false') . PHP_EOL;
echo 'current_attachment_selected=' . (($files[0]['filename'] ?? null) === 'current-indirect-prev.xml' ? 'true' : 'false') . PHP_EOL;
echo 'indirect_prev_helper_used=' . (str_contains($pdf, '/Prev 30 0 R') ? 'true' : 'false') . PHP_EOL;
echo 'stale_prev_metadata_excluded=' . (!str_contains($metadataJson, 'Stale Indirect Prev') ? 'true' : 'false') . PHP_EOL;
echo 'stale_prev_attachment_excluded=' . (!str_contains($filesJson, 'stale-indirect-prev') ? 'true' : 'false') . PHP_EOL;
echo 'stale_prev_text_excluded=' . (!str_contains($text, 'Stale indirect Prev helper page') ? 'true' : 'false') . PHP_EOL;
echo 'executes_python_or_models=false' . PHP_EOL;
echo 'executes_external_pdf_tools=false' . PHP_EOL;
