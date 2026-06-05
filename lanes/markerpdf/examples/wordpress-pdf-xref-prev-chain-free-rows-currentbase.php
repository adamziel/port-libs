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
        . '<xmp:CreateDate>2026-06-05T07:05:01Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale latest free row Prev page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current latest free row page) Tj T* (Latest free rows suppress Prev) Tj ET';
$stalePayload = '<wp-export><post id="stale-latest-free-row"/></wp-export>';
$staleXmp = gzcompress($xmpPacket(
    'Stale Latest Free Row XMP Title',
    'Previous metadata must be suppressed by current free rows'
));
if (!is_string($staleXmp)) {
    throw new RuntimeException('Unable to compress latest-free-row xref Prev chain smoke stream.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
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
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Latest Free Row Info Title) /Author (Stale Latest Free Row Author) /Producer (Stale Latest Free Row Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-latest-free-row.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-latest-free-row.xml) /Desc (Stale latest free row attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $currentCatalogOffset, 0)
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(0, 0, 1);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress latest-free-row current xref-stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 8 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

$flags = [
    'current_text_selected' => $text === "Current latest free row page\nLatest free rows suppress Prev",
    'stale_text_excluded' => !str_contains($text, 'Stale latest free row Prev page'),
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'previous_xmp_suppressed_by_free_row' => ($metadata['source'] ?? []) === ['catalog']
        && !isset($metadata['title'])
        && is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'Stale Latest Free Row'),
    'previous_info_suppressed_by_free_row' => ($metadata['info'] ?? null) === []
        && !isset($metadata['authors'])
        && !isset($metadata['producer']),
    'previous_attachment_suppressed_by_free_row' => $files === []
        && is_string($encodedFiles)
        && !str_contains($encodedFiles, 'stale-latest-free-row'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($flags as $key => $value) {
    echo $key . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}
