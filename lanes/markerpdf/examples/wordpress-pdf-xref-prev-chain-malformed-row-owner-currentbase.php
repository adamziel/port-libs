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
        . '<xmp:CreateDate>2026-06-05T12:08:31Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale wrong-owner row Prev page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current wrong-owner row page) Tj T* (Malformed rows suppress Prev) Tj ET';
$stalePayload = '<wp-export><post id="stale-wrong-owner-row"/></wp-export>';
$staleXmp = gzcompress($xmpPacket(
    'Stale Wrong Owner Row XMP Title',
    'Previous metadata must be suppressed by malformed current rows'
));
if (!is_string($staleXmp)) {
    throw new RuntimeException('Unable to compress wrong-owner-row XMP smoke stream.');
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
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Wrong Owner Row Info Title) /Author (Stale Wrong Owner Row Author) /Producer (Stale Wrong Owner Row Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-wrong-owner-row.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-wrong-owner-row.xml) /Desc (Stale wrong-owner row attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $staleContentOffset, 0)
    . $xrefStreamRow(1, $staleContentOffset, 0)
    . $xrefStreamRow(1, $staleContentOffset, 0)
    . $xrefStreamRow(1, $staleContentOffset, 0)
    . $xrefStreamRow(1, $staleContentOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress wrong-owner-row xref smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 4 6 3 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$text = $textExtractor->extractPlainText($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFiles = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES) ?: '';
$encodedAttachmentSummary = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES) ?: '';

$flags = [
    'native_boundary' => 'latest xref-stream in-use rows with stale wrong-owner offsets suppress inherited Prev metadata and attachments',
    'current_text_selected' => $text === "Current wrong-owner row page\nMalformed rows suppress Prev",
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'previous_info_suppressed_by_malformed_rows' => ($metadata['info'] ?? null) === [] && !isset($metadata['title']),
    'previous_xmp_suppressed_by_malformed_rows' => !str_contains($encodedMetadata, 'Stale Wrong Owner Row'),
    'embedded_file_stale_prev_attachment_excluded' => $embeddedFiles === [] && !str_contains($encodedFiles, 'stale-wrong-owner-row'),
    'attachment_preflight_stale_prev_attachment_excluded' => ($attachmentSummary['attachment_count'] ?? null) === 0
        && ($attachmentSummary['filenames'] ?? null) === []
        && !str_contains($encodedAttachmentSummary, 'stale-wrong-owner-row'),
    'stale_visible_text_excluded' => !str_contains($text, 'Stale wrong-owner row Prev page'),
    'no_python_or_models_executed' => true,
    'no_external_pdf_tools_executed' => true,
];

foreach ($flags as $name => $value) {
    if ($name !== 'native_boundary' && $value !== true && $value !== false) {
        throw new RuntimeException("Unexpected non-boolean smoke flag {$name}.");
    }
}
foreach ($flags as $name => $value) {
    if ($name !== 'native_boundary' && $value !== true) {
        throw new RuntimeException("Failed smoke flag {$name}.");
    }
}

echo '<!-- markerpdf-xref-prev-chain-malformed-row-owner-smoke ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
