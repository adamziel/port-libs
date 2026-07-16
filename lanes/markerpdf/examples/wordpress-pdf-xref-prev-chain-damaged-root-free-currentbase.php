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
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale damaged root free Prev page) Tj ET';
$stalePayload = '<wp-export><post id="stale-damaged-root-free-prev"/></wp-export>';
$staleXmp = gzcompress($xmpPacket(
    'Stale Damaged Root Free XMP Title',
    'Damaged Prev root metadata must not be revived'
));
if (!is_string($staleXmp)) {
    throw new RuntimeException('Unable to compress damaged-root-free xref Prev chain smoke XMP.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Carried Damaged Root Free Info Title) /Author (Carried Damaged Root Free Author) /Producer (Carried Damaged Root Free Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-damaged-root-free-prev.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-damaged-root-free-prev.xml) /Desc (Stale damaged root-free Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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

$currentRows = $xrefStreamRow(0, 0, 1);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress damaged-root-free current xref stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$damagedPrevOffset = $previousXrefOffset + 3;
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Prev ' . $damagedPrevOffset . ' /Index [1 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

if ($lines !== [] || $plainText !== '') {
    throw new RuntimeException('Expected damaged Prev root-free xref stream to suppress stale visible page text.');
}
if (($metadata['source'] ?? null) !== ['info'] || ($metadata['title'] ?? null) !== 'Carried Damaged Root Free Info Title') {
    throw new RuntimeException('Expected only carried Info metadata after root-free damaged Prev repair.');
}
if (isset($metadata['catalog'], $metadata['language'], $metadata['embedded_files']) || ($metadata['xmp'] ?? null) !== []) {
    throw new RuntimeException('Expected stale catalog, language, XMP, and attachments to stay suppressed.');
}
if ($files !== []) {
    throw new RuntimeException('Expected stale EmbeddedFiles name tree to stay suppressed.');
}
if (
    !is_string($encodedMetadata)
    || !is_string($encodedFiles)
    || str_contains($encodedMetadata, 'Stale Damaged Root Free')
    || str_contains($encodedFiles, 'stale-damaged-root-free-prev')
) {
    throw new RuntimeException('Expected stale damaged Prev metadata and attachment strings to stay out of WordPress review output.');
}

echo '<!-- markerpdf-xref-prev-chain-damaged-root-free-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'latest xref-stream free row blocks inherited trailer Root after damaged Prev pointer repair',
    'visible_text_empty' => $plainText === '',
    'stale_page_text_excluded' => !str_contains($plainText, 'Stale damaged root free Prev page'),
    'metadata_source' => $metadata['source'],
    'info_title_carried' => $metadata['title'],
    'stale_catalog_suppressed' => !isset($metadata['catalog']),
    'stale_xmp_suppressed' => ($metadata['xmp'] ?? null) === [],
    'stale_embedded_files_suppressed' => $files === [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Carried Damaged Root Free Info Title</h2>\n";
echo "<!-- /wp:heading -->\n";
