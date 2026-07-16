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
        . '<xmp:CreateDate>2026-06-05T01:19:37Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$objectStream = static function (array $members): array {
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $plain = $header . "\n" . $objectData;
    $compressed = gzcompress($plain);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress compressed-Prev object-stream helper.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'content' => $compressed,
        'count' => count($members),
    ];
};

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous compressed Prev metadata page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed Prev metadata page) Tj T* (Compressed Prev helper repaired metadata) Tj ET';
$previousPayload = '<wp-export><post id="previous-compressed-prev"/></wp-export>';
$currentPayload = '<wp-export><post id="current-compressed-prev"/></wp-export>';
$previousXmp = gzcompress($xmpPacket('Previous Compressed Prev XMP Title', 'Previous compressed Prev metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Compressed Prev XMP Title', 'Current compressed object-stream Prev helper repaired metadata'));
if (!is_string($previousXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress xref-stream compressed Prev smoke streams.');
}

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$previousCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$previousPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$previousInfoOffset = $addObject(6, 0, '<< /Title (Previous Compressed Prev Info Title) /Author (Previous Compressed Author) /Producer (Previous Compressed Producer) >>');
$previousMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($previousXmp) . " >>\nstream\n{$previousXmp}\nendstream");
$previousNameTreeOffset = $addObject(8, 0, '<< /Names [(previous-compressed-prev.xml) 10 0 R] >>');
$previousFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (previous-compressed-prev.xml) /Desc (Previous compressed Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Title (Current Compressed Prev Info Title) /Author (Current Compressed Author) /Producer (Current Compressed Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /Names [(current-compressed-prev.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-compressed-prev.xml) /Desc (Current compressed Prev attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$prevHelperStream = $objectStream([30 => (string) $previousXrefOffset]);
$prevHelperCarrierOffset = $addObject(90, 0, '<< /Type /ObjStm /N ' . $prevHelperStream['count'] . ' /First ' . $prevHelperStream['first'] . ' /Filter /FlateDecode /Length ' . strlen($prevHelperStream['content']) . " >>\nstream\n{$prevHelperStream['content']}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(2, 90, $prevHelperStream['indexes'][30])
    . $xrefStreamRow(1, $prevHelperCarrierOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress xref-stream compressed Prev smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 6 0 R /Prev 30 0 R /Index [1 8 10 2 30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-prev-chain-compressed-prev-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream /Prev resolved from a compressed object-stream numeric helper before current metadata and EmbeddedFiles rows are repaired',
    'current_compressed_prev_text_selected' => $lines === [
        'Current compressed Prev metadata page',
        'Compressed Prev helper repaired metadata',
    ],
    'current_compressed_prev_xmp_selected' => ($metadata['title'] ?? null) === 'Current Compressed Prev XMP Title',
    'current_compressed_prev_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Compressed Prev Info Title',
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'current_attachment_selected' => ($files[0]['filename'] ?? null) === 'current-compressed-prev.xml',
    'current_attachment_payload_selected' => ($files[0]['content'] ?? null) === $currentPayload,
    'uses_compressed_object_stream_prev_helper' => str_contains($pdf, '/Prev 30 0 R') && str_contains($pdf, '/Type /ObjStm'),
    'stale_prev_metadata_excluded' => !str_contains($metadataJson, 'Previous Compressed Prev'),
    'stale_prev_attachment_excluded' => !str_contains($filesJson, 'previous-compressed-prev'),
    'stale_prev_text_excluded' => !str_contains($text, 'Previous compressed Prev metadata page'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
