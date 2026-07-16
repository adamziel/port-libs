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
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic direct Prev owner page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic direct Prev owner page) Tj T* (Classic direct Prev helper selected before decoy) Tj ET';
$stalePayload = '<wp-export><post id="stale-classic-direct-prev-owner"/></wp-export>';
$currentPayload = '<wp-export><post id="current-classic-direct-prev-owner"/></wp-export>';
$staleXmp = gzcompress($xmpPacket(
    'Stale Classic Direct Prev Owner XMP Title',
    'Stale classic direct Prev owner metadata must not win'
));
$currentXmp = gzcompress($xmpPacket(
    'Current Classic Direct Prev Owner XMP Title',
    'Current classic table direct Prev helper selected before decoy'
));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress classic direct-Prev owner smoke streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Classic Direct Prev Owner Info Title) /Author (Stale Classic Direct Prev Author) /Producer (Stale Classic Direct Prev Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-classic-direct-prev-owner.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-classic-direct-prev-owner.xml) /Desc (Stale classic direct Prev owner attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$staleEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleCatalogOffset)
    . $xrefTableRow($stalePagesOffset)
    . $xrefTableRow($stalePageOffset)
    . $xrefTableRow($staleContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($staleInfoOffset)
    . $xrefTableRow($staleMetadataOffset)
    . $xrefTableRow($staleNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleFileSpecOffset)
    . $xrefTableRow($staleEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Title (Current Classic Direct Prev Owner Info Title) /Author (Current Classic Direct Prev Author) /Producer (Current Classic Direct Prev Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /Names [(current-classic-direct-prev-owner.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-classic-direct-prev-owner.xml) /Desc (Current classic direct Prev owner attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "1 4\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "6 3\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "10 2\n"
    . $xrefTableRow(0)
    . $xrefTableRow(0)
    . "30 1\n"
    . $xrefTableRow($prevHelperOffset)
    . "trailer\n<< /Size 31 /Root 1 0 R /Info 6 0 R /Prev 30 0 R >>\n"
    . "30 0 obj\n(post-xref classic Prev helper decoy)\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$text = (new PdfTextExtractor())->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

$currentTextSelected = str_contains($text, 'Current classic direct Prev owner page')
    && str_contains($text, 'Classic direct Prev helper selected before decoy');
$staleTextExcluded = !str_contains($text, 'Stale classic direct Prev owner page');
$currentTitleSelected = ($metadata['title'] ?? null) === 'Current Classic Direct Prev Owner XMP Title';
$currentInfoSelected = ($metadata['info']['Title'] ?? null) === 'Current Classic Direct Prev Owner Info Title';
$currentAttachmentSelected = ($files[0]['filename'] ?? null) === 'current-classic-direct-prev-owner.xml'
    && ($files[0]['content'] ?? null) === $currentPayload;
$staleMetadataExcluded = is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Classic Direct Prev');
$staleAttachmentExcluded = is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-classic-direct-prev-owner');

if (!$currentTextSelected || !$staleTextExcluded || !$currentTitleSelected || !$currentInfoSelected || !$currentAttachmentSelected || !$staleMetadataExcluded || !$staleAttachmentExcluded) {
    throw new RuntimeException('Classic xref-table /Prev direct helper owner boundary failed.');
}

foreach ([
    'classic_table_direct_prev_helper_selected' => true,
    'post_table_same_number_decoy_present' => str_contains($pdf, 'post-xref classic Prev helper decoy'),
    'current_text_selected' => $currentTextSelected,
    'stale_text_excluded' => $staleTextExcluded,
    'current_xmp_title_selected' => $currentTitleSelected,
    'current_info_title_selected' => $currentInfoSelected,
    'current_attachment_selected' => $currentAttachmentSelected,
    'stale_prev_metadata_excluded' => $staleMetadataExcluded,
    'stale_prev_attachment_excluded' => $staleAttachmentExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
] as $key => $value) {
    echo $key . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}
