<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfEmbeddedFileExtractor.php';
require_once __DIR__ . '/../src/PdfMetadataExtractor.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale inherited root free row page) Tj ET';
$stalePayload = '<wp-export><post id="stale-root-free-prev"/></wp-export>';
$staleXmp = gzcompress(
    '<?xpacket begin=""?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Stale Root Free XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Previous root metadata must not be revived</rdf:li></rdf:Alt></dc:description>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>'
);
if (!is_string($staleXmp)) {
    throw new RuntimeException('Unable to compress root-free xref Prev smoke XMP.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$pageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$contentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$infoOffset = $addObject(6, 0, '<< /Title (Carried Root Free Info Title) /Author (Carried Root Free Author) /Producer (Carried Root Free Producer) >>');
$metadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$nameTreeOffset = $addObject(8, 0, '<< /Names [(stale-root-free-prev.xml) 10 0 R] >>');
$fileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-root-free-prev.xml) /Desc (Stale root free Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$embeddedFileOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($catalogOffset)
    . $xrefTableRow($pagesOffset)
    . $xrefTableRow($pageOffset)
    . $xrefTableRow($contentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow($infoOffset)
    . $xrefTableRow($metadataOffset)
    . $xrefTableRow($nameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($fileSpecOffset)
    . $xrefTableRow($embeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentRows = $xrefStreamRow(0, 0, 1);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress root-free current xref stream.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Prev ' . $previousXrefOffset . ' /Index [1 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

$classicStaleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic root free direct Prev page) Tj ET';
$classicStalePayload = '<wp-export><post id="stale-classic-root-free-prev"/></wp-export>';
$classicPdf = "%PDF-1.7\n";
$addClassicObject = static function (int $objectNumber, int $generation, string $body) use (&$classicPdf): int {
    $offset = strlen($classicPdf);
    $classicPdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$classicCatalogOffset = $addClassicObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (fr-FR) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$classicPagesOffset = $addClassicObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$classicPageOffset = $addClassicObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$classicContentOffset = $addClassicObject(4, 0, "<< /Length " . strlen($classicStaleContent) . " >>\nstream\n{$classicStaleContent}\nendstream");
$classicFontOffset = $addClassicObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$classicInfoOffset = $addClassicObject(6, 0, '<< /Title (Carried Classic Root Free Info Title) /Author (Carried Classic Root Free Author) /Producer (Carried Classic Root Free Producer) >>');
$classicMetadataOffset = $addClassicObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$classicNameTreeOffset = $addClassicObject(8, 0, '<< /Names [(stale-classic-root-free-prev.xml) 10 0 R] >>');
$classicFileSpecOffset = $addClassicObject(10, 0, '<< /Type /Filespec /F (stale-classic-root-free-prev.xml) /Desc (Stale classic root free Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$classicEmbeddedFileOffset = $addClassicObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($classicStalePayload) . " >>\nstream\n{$classicStalePayload}\nendstream");

$classicPreviousXrefOffset = strlen($classicPdf);
$classicPdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($classicCatalogOffset)
    . $xrefTableRow($classicPagesOffset)
    . $xrefTableRow($classicPageOffset)
    . $xrefTableRow($classicContentOffset)
    . $xrefTableRow($classicFontOffset)
    . $xrefTableRow($classicInfoOffset)
    . $xrefTableRow($classicMetadataOffset)
    . $xrefTableRow($classicNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($classicFileSpecOffset)
    . $xrefTableRow($classicEmbeddedFileOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$classicPreviousXrefOffset}\n%%EOF\n";

$addClassicObject(30, 0, (string) $classicPreviousXrefOffset);
$classicCurrentXrefOffset = strlen($classicPdf);
$classicPdf .= "xref\n"
    . "1 1\n"
    . $xrefTableRow(0, 1, 'f')
    . "trailer\n<< /Size 31 /Info 6 0 R /Prev 30 0 R >>\n"
    . "30 0 obj\n(post-xref classic root-free Prev helper decoy)\nendobj\n"
    . "startxref\n{$classicCurrentXrefOffset}\n%%EOF";

$classicLines = $textExtractor->extractTextLines($classicPdf);
$classicPlainText = $textExtractor->extractPlainText($classicPdf);
$classicMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($classicPdf);
$classicFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($classicPdf);
$classicEncodedMetadata = json_encode($classicMetadata, JSON_UNESCAPED_SLASHES);
$classicEncodedFiles = json_encode($classicFiles, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-prev-chain-root-free-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest xref-stream or classic xref-table free row for inherited trailer Root blocks stale page and embedded-file fallback before WordPress import',
    'latest_xref_omits_root' => !str_contains(substr($pdf, (int) strrpos($pdf, '/Type /XRef')), '/Root '),
    'root_free_row_present' => str_contains($pdf, '/Index [1 1]'),
    'paragraphs_imported' => count($lines),
    'stale_page_excluded' => !str_contains($plainText, 'Stale inherited root free row page'),
    'stale_xmp_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Root Free XMP Title'),
    'stale_attachment_excluded' => is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-root-free-prev'),
    'info_source_carried' => ($metadata['source'] ?? []) === ['info'],
    'attachment_count' => count($files),
    'classic_table_direct_prev_helper_selected' => str_contains($classicPdf, '/Prev 30 0 R'),
    'classic_table_post_xref_decoy_present' => str_contains($classicPdf, 'post-xref classic root-free Prev helper decoy'),
    'classic_table_latest_xref_omits_root' => str_contains($classicPdf, "trailer\n<< /Size 31 /Info 6 0 R /Prev 30 0 R >>"),
    'classic_table_root_free_row_present' => str_contains($classicPdf, "0000000000 00001 f"),
    'classic_table_paragraphs_imported' => count($classicLines),
    'classic_table_stale_page_excluded' => !str_contains($classicPlainText, 'Stale classic root free direct Prev page'),
    'classic_table_stale_xmp_excluded' => is_string($classicEncodedMetadata) && !str_contains($classicEncodedMetadata, 'Stale Root Free XMP Title'),
    'classic_table_stale_attachment_excluded' => is_string($classicEncodedFiles) && !str_contains($classicEncodedFiles, 'stale-classic-root-free-prev'),
    'classic_table_info_source_carried' => ($classicMetadata['source'] ?? []) === ['info'],
    'classic_table_attachment_count' => count($classicFiles),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
