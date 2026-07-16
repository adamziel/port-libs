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
        . '<xmp:CreateDate>2026-06-03T09:30:09Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged middle Prev page) Tj T* (Base xref repaired before decoys) Tj ET';
$decoyContent = 'BT /F1 12 Tf 72 720 Td (Post xref damaged middle Prev decoy page) Tj ET';
$currentPayload = '<wp-export><post id="current-damaged-middle-prev"/></wp-export>';
$decoyPayload = '<wp-export><post id="post-xref-damaged-middle-prev-decoy"/></wp-export>';
$currentXmp = gzcompress($xmpPacket(
    'Current Damaged Middle Prev XMP Title',
    'Middle Prev repaired to the earlier base xref section'
));
$decoyXmp = gzcompress($xmpPacket(
    'Post Xref Damaged Middle Prev Decoy Title',
    'Post xref direct objects must not win when the base xref is repairable'
));
if (!is_string($currentXmp) || !is_string($decoyXmp)) {
    throw new RuntimeException('Unable to compress damaged middle-Prev smoke fixture streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$currentInfoOffset = $addObject(6, 0, '<< /Title (Current Damaged Middle Prev Info Title) /Author (Current Damaged Middle Author) /Producer (Current Damaged Middle Producer) >>');
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentNameTreeOffset = $addObject(8, 0, '<< /Names [(current-damaged-middle-prev.xml) 10 0 R] >>');
$currentFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (current-damaged-middle-prev.xml) /Desc (Current damaged middle Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$currentEmbeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$baseXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($currentCatalogOffset)
    . $xrefTableRow($currentPagesOffset)
    . $xrefTableRow($currentPageOffset)
    . $xrefTableRow($currentContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($currentInfoOffset)
    . $xrefTableRow($currentMetadataOffset)
    . $xrefTableRow($currentNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($currentFileSpecOffset)
    . $xrefTableRow($currentEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$baseXrefOffset}\n%%EOF\n";

$damagedPrevOffset = $baseXrefOffset + 2;
$middleXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "30 1\n"
    . $xrefTableRow(0, 0, 'f')
    . "trailer\n<< /Size 31 /Prev {$damagedPrevOffset} >>\n"
    . "startxref\n{$middleXrefOffset}\n%%EOF\n";

$latestXrefOffset = strlen($pdf);
$latestRows = $xrefStreamRow(1, $latestXrefOffset, 0);
$compressedLatestRows = gzcompress($latestRows);
if (!is_string($compressedLatestRows)) {
    throw new RuntimeException('Unable to compress damaged middle-Prev smoke xref stream.');
}
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $middleXrefOffset . ' /Index [20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedLatestRows) . " >>\n"
    . "stream\n{$compressedLatestRows}\nendstream\nendobj\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (it-IT) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream");
$addObject(6, 0, '<< /Title (Post Xref Damaged Middle Prev Decoy Info) /Author (Post Xref Decoy Author) /Producer (Post Xref Decoy Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($decoyXmp) . " >>\nstream\n{$decoyXmp}\nendstream");
$addObject(8, 0, '<< /Names [(post-xref-damaged-middle-prev-decoy.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (post-xref-damaged-middle-prev-decoy.xml) /Desc (Post xref damaged middle Prev decoy attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

$pdf .= "startxref\n{$latestXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encoded = json_encode([
    'metadata' => $metadata,
    'text' => $plainText,
    'files' => $files,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-damaged-middle-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'damaged middle /Prev repaired to the earlier valid base xref before post-xref decoys',
    'current_xmp_title_selected' => ($metadata['title'] ?? null) === 'Current Damaged Middle Prev XMP Title',
    'current_info_title_selected' => ($metadata['info']['Title'] ?? null) === 'Current Damaged Middle Prev Info Title',
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'current_page_text_selected' => str_contains($plainText, 'Current damaged middle Prev page'),
    'current_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-damaged-middle-prev.xml',
    'post_xref_decoy_metadata_excluded' => is_string($encoded) && !str_contains($encoded, 'Post Xref Damaged Middle Prev'),
    'post_xref_decoy_text_excluded' => !str_contains($plainText, 'Post xref damaged middle Prev decoy page'),
    'post_xref_decoy_attachment_excluded' => is_string($encoded) && !str_contains($encoded, 'post-xref-damaged-middle-prev-decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF xref review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
