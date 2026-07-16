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
        . '<xmp:CreateDate>2026-06-06T18:41:37Z</xmp:CreateDate>'
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
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress xref Prev compressed-root object stream.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'content' => $compressed,
        'count' => count($members),
    ];
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed-root Prev page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed-root page) Tj T* (Omitted rows repaired from compressed catalog) Tj ET';
$stalePayload = '<wp-export><post id="stale-compressed-root"/></wp-export>';
$currentPayload = '<wp-export><post id="current-compressed-root"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Compressed Root XMP Title', 'Previous compressed-root rows must not win'));
$currentXmp = gzcompress($xmpPacket('Current Compressed Root XMP Title', 'Compressed catalog omitted rows recovered'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress compressed-root xref Prev smoke fixture streams.');
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
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Compressed Root Info Title) /Author (Stale Compressed Root Author) /Producer (Stale Compressed Root Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-compressed-root.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-compressed-root.xml) /Desc (Stale compressed-root attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$staleEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");

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
    . $xrefTableRow($staleEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R /Info 6 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Title (Current Compressed Root Info Title) /Author (Current Compressed Root Author) /Producer (Current Compressed Root Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(8, 0, '<< /Names [(current-compressed-root.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-compressed-root.xml) /Desc (Current compressed-root attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$rootCarrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
]);
$rootCarrierOffset = $addObject(
    30,
    0,
    '<< /Type /ObjStm /N ' . $rootCarrier['count'] . ' /First ' . $rootCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($rootCarrier['content']) . " >>\nstream\n{$rootCarrier['content']}\nendstream"
);

$currentRows = ''
    . $xrefStreamRow(2, 30, $rootCarrier['indexes'][1])
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, $rootCarrierOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress compressed-root xref stream rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 5 1 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$text = $textExtractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

$flags = [
    'compressed_root_catalog_selected' => ($metadata['language'] ?? null) === 'en-US'
        && str_contains($pdf, '/Type /ObjStm'),
    'omitted_current_page_rows_repaired' => str_contains($text, 'Current compressed-root page')
        && str_contains($text, 'Omitted rows repaired from compressed catalog'),
    'omitted_current_xmp_row_repaired' => ($metadata['title'] ?? null) === 'Current Compressed Root XMP Title',
    'omitted_current_attachment_rows_repaired' => ($files[0]['filename'] ?? null) === 'current-compressed-root.xml'
        && ($files[0]['content'] ?? null) === $currentPayload,
    'attachment_summary_current' => ($summary['filenames'] ?? []) === ['current-compressed-root.xml']
        && ($summary['total_bytes'] ?? null) === strlen($currentPayload),
    'stale_prev_rows_excluded' => !str_contains($text, 'Stale compressed-root Prev page')
        && is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'Stale Compressed Root')
        && is_string($encodedFiles)
        && !str_contains($encodedFiles, 'stale-compressed-root')
        && is_string($encodedSummary)
        && !str_contains($encodedSummary, 'stale-compressed-root'),
    'executes_python_or_models' => (bool) ($summary['executes_python_or_models'] ?? true),
    'executes_external_pdf_tools' => (bool) ($summary['executes_external_pdf_tools'] ?? true),
];

foreach ($flags as $name => $value) {
    echo $name . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}
