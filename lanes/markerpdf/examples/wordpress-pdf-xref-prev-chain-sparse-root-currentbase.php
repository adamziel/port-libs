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
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $title . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . $description . '</rdf:li></rdf:Alt></dc:description>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$decoyContent = 'BT /F1 12 Tf 72 720 Td (Unreferenced sparse Root decoy page) Tj ET';
$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous sparse Root page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current sparse Root page) Tj T* (Inherited trailer Root selected) Tj ET';
$decoyPayload = '<wp-export><post id="decoy-sparse-root"/></wp-export>';
$previousPayload = '<wp-export><post id="previous-sparse-root"/></wp-export>';
$currentPayload = '<wp-export><post id="current-sparse-root"/></wp-export>';
$decoyXmp = gzcompress($xmpPacket('Decoy Sparse Root XMP Title', 'Unreferenced lower-numbered catalog must not win'));
$previousXmp = gzcompress($xmpPacket('Previous Sparse Root XMP Title', 'Previous trailer root metadata must be updated by current rows'));
$currentXmp = gzcompress($xmpPacket('Current Sparse Root XMP Title', 'Latest sparse trailer inherits previous Root'));
if (!is_string($decoyXmp) || !is_string($previousXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress sparse Root xref smoke XMP streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (fr-FR) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(8, 0, '<< /Names [(decoy-sparse-root.xml) 9 0 R] >>');
$addObject(9, 0, '<< /Type /Filespec /F (decoy-sparse-root.xml) /Desc (Decoy sparse Root attachment) /AFRelationship /Data /EF << /F 10 0 R >> >>');
$addObject(10, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Sparse Root Info Title) /Author (Previous Sparse Root Author) /Producer (Previous Sparse Root Producer) >>');
$previousCatalogOffset = $addObject(12, 0, '<< /Type /Catalog /Pages 13 0 R /Lang (de-DE) /Metadata 17 0 R /Names << /EmbeddedFiles 18 0 R >> >>');
$previousPagesOffset = $addObject(13, 0, '<< /Type /Pages /Kids [14 0 R] /Count 1 >>');
$previousPageOffset = $addObject(14, 0, '<< /Type /Page /Parent 13 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R >>');
$previousContentOffset = $addObject(15, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$previousMetadataOffset = $addObject(17, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
$previousNameTreeOffset = $addObject(18, 0, '<< /Names [(previous-sparse-root.xml) 19 0 R] >>');
$previousFileSpecOffset = $addObject(19, 0, '<< /Type /Filespec /F (previous-sparse-root.xml) /Desc (Previous sparse Root attachment) /AFRelationship /Source /EF << /F 20 0 R >> >>');
$previousEmbeddedOffset = $addObject(20, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($previousPayload) . " >>\nstream\n{$previousPayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefTableRow(0, 65535, 'f')
    . "5 2\n"
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($previousInfoOffset)
    . "12 4\n"
    . $xrefTableRow($previousCatalogOffset)
    . $xrefTableRow($previousPagesOffset)
    . $xrefTableRow($previousPageOffset)
    . $xrefTableRow($previousContentOffset)
    . "17 4\n"
    . $xrefTableRow($previousMetadataOffset)
    . $xrefTableRow($previousNameTreeOffset)
    . $xrefTableRow($previousFileSpecOffset)
    . $xrefTableRow($previousEmbeddedOffset)
    . "trailer\n<< /Size 21 /Root 12 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Sparse Root Info Title) /Author (Current Sparse Root Author) /Producer (Current Sparse Root Producer) >>');
$currentCatalogOffset = $addObject(12, 0, '<< /Type /Catalog /Pages 13 0 R /Lang (en-US) /Metadata 17 0 R /Names << /EmbeddedFiles 18 0 R >> >>');
$currentPagesOffset = $addObject(13, 0, '<< /Type /Pages /Kids [14 0 R] /Count 1 >>');
$currentPageOffset = $addObject(14, 0, '<< /Type /Page /Parent 13 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R >>');
$currentContentOffset = $addObject(15, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentMetadataOffset = $addObject(17, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(18, 0, '<< /Names [(current-sparse-root.xml) 19 0 R] >>');
$currentFileSpecOffset = $addObject(19, 0, '<< /Type /Filespec /F (current-sparse-root.xml) /Desc (Current sparse Root attachment) /AFRelationship /Source /EF << /F 20 0 R >> >>');
$currentEmbeddedOffset = $addObject(20, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $currentInfoOffset, 0)
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $currentMetadataOffset, 0)
    . $xrefStreamRow(1, $currentNameTreeOffset, 0)
    . $xrefStreamRow(1, $currentFileSpecOffset, 0)
    . $xrefStreamRow(1, $currentEmbeddedOffset, 0);
$compressedCurrentRows = gzcompress($currentRows);
if (!is_string($compressedCurrentRows)) {
    throw new RuntimeException('Unable to compress sparse Root xref smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [6 1 12 4 17 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
    . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedEmbeddedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-sparse-root-currentbase '
    . htmlspecialchars(json_encode([
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'latest_sparse_xref_stream_inherits_prev_root' => ($metadata['title'] ?? null) === 'Current Sparse Root XMP Title'
            && ($metadata['language'] ?? null) === 'en-US',
        'current_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Sparse Root Info Title',
        'current_page_text_selected' => $lines === [
            'Current sparse Root page',
            'Inherited trailer Root selected',
        ],
        'current_attachment_selected' => ($embeddedFiles[0]['filename'] ?? null) === 'current-sparse-root.xml'
            && ($embeddedFiles[0]['content'] ?? null) === $currentPayload,
        'attachment_preflight_uses_current_root' => ($attachmentSummary['attachment_count'] ?? null) === 1
            && ($attachmentSummary['filenames'] ?? null) === ['current-sparse-root.xml']
            && ($attachmentSummary['total_bytes'] ?? null) === strlen($currentPayload),
        'decoy_catalog_excluded' => is_string($encodedMetadata)
            && is_string($encodedEmbeddedFiles)
            && is_string($encodedAttachmentSummary)
            && !str_contains($encodedMetadata, 'Decoy Sparse Root')
            && !str_contains($encodedEmbeddedFiles, 'decoy-sparse-root')
            && !str_contains($encodedAttachmentSummary, 'decoy-sparse-root')
            && !str_contains($plainText, 'Unreferenced sparse Root decoy page'),
        'previous_root_excluded_after_current_update' => is_string($encodedMetadata)
            && is_string($encodedEmbeddedFiles)
            && is_string($encodedAttachmentSummary)
            && !str_contains($encodedMetadata, 'Previous Sparse Root')
            && !str_contains($encodedEmbeddedFiles, 'previous-sparse-root')
            && !str_contains($encodedAttachmentSummary, 'previous-sparse-root')
            && !str_contains($plainText, 'Previous sparse Root page'),
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
