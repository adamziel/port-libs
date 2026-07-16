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
        . '<xmp:CreateDate>2026-06-05T05:49:18Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale direct Prev owner page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current direct Prev owner page) Tj T* (Direct Prev helper selected) Tj ET';
$stalePayload = '<wp-export><post id="stale-direct-prev-owner"/></wp-export>';
$currentPayload = '<wp-export><post id="current-direct-prev-owner"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Direct Prev Owner XMP Title', 'Stale direct Prev owner metadata'));
$currentXmp = gzcompress($xmpPacket(
    'Current Direct Prev Owner XMP Title',
    'Current xref-stream direct Prev helper selected before decoy'
));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress direct Prev owner smoke fixture streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Direct Prev Owner Info Title) /Author (Stale Direct Prev Author) /Producer (Stale Direct Prev Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-direct-prev-owner.xml) 9 0 R] >>');
$staleFileSpecOffset = $addObject(9, 0, '<< /Type /Filespec /F (stale-direct-prev-owner.xml) /Desc (Stale direct Prev owner attachment) /AFRelationship /Data /EF << /F 10 0 R >> >>');
$staleEmbeddedOffset = $addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 11\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($staleCatalogOffset)
    . $xrefRow($stalePagesOffset)
    . $xrefRow($stalePageOffset)
    . $xrefRow($staleContentOffset)
    . $xrefRow($fontOffset)
    . $xrefRow($staleInfoOffset)
    . $xrefRow($staleMetadataOffset)
    . $xrefRow($staleNameTreeOffset)
    . $xrefRow($staleFileSpecOffset)
    . $xrefRow($staleEmbeddedOffset)
    . "trailer\n<< /Size 11 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$addObject(6, 0, '<< /Title (Current Direct Prev Owner Info Title) /Author (Current Direct Prev Author) /Producer (Current Direct Prev Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /Names [(current-direct-prev-owner.xml) 9 0 R] >>');
$addObject(9, 0, '<< /Type /Filespec /F (current-direct-prev-owner.xml) /Desc (Current direct Prev owner attachment) /AFRelationship /Data /EF << /F 10 0 R >> >>');
$addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(30, 0, (string) $previousXrefOffset);

$rows = ''
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress direct Prev owner xref rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev 30 0 R /Index [1 10] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "30 0 obj\n(post-xref direct Prev helper decoy)\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$text = $extractor->extractPlainText($pdf);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

echo 'current_direct_prev_owner_title_selected=' . (($metadata['title'] ?? null) === 'Current Direct Prev Owner XMP Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_direct_prev_owner_info_selected=' . (($metadata['info']['Title'] ?? null) === 'Current Direct Prev Owner Info Title' ? 'true' : 'false') . PHP_EOL;
echo 'current_direct_prev_owner_language_selected=' . (($metadata['language'] ?? null) === 'en-US' ? 'true' : 'false') . PHP_EOL;
echo 'current_direct_prev_owner_text_selected=' . (str_contains($text, 'Current direct Prev owner page') ? 'true' : 'false') . PHP_EOL;
echo 'current_direct_prev_owner_attachment_selected=' . (($files[0]['filename'] ?? null) === 'current-direct-prev-owner.xml' ? 'true' : 'false') . PHP_EOL;
echo 'current_direct_prev_owner_payload_selected=' . (($files[0]['content'] ?? null) === $currentPayload ? 'true' : 'false') . PHP_EOL;
echo 'direct_prev_helper_reference_present=' . (str_contains($pdf, '/Prev 30 0 R') ? 'true' : 'false') . PHP_EOL;
echo 'post_xref_same_number_decoy_present=' . (str_contains($pdf, 'post-xref direct Prev helper decoy') ? 'true' : 'false') . PHP_EOL;
echo 'stale_direct_prev_owner_metadata_excluded=' . (!str_contains($metadataJson, 'Stale Direct Prev Owner') ? 'true' : 'false') . PHP_EOL;
echo 'stale_direct_prev_owner_attachment_excluded=' . (!str_contains($filesJson, 'stale-direct-prev-owner') ? 'true' : 'false') . PHP_EOL;
echo 'stale_direct_prev_owner_text_excluded=' . (!str_contains($text, 'Stale direct Prev owner page') ? 'true' : 'false') . PHP_EOL;
echo 'executes_python_or_models=false' . PHP_EOL;
echo 'executes_external_pdf_tools=false' . PHP_EOL;
