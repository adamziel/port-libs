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
        . '<xmp:CreateDate>2026-06-05T04:07:18Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$objectStream = static function (array $members): array {
    $headerPairs = [];
    $indexes = [];
    $body = '';
    foreach ($members as $objectNumber => $memberBody) {
        $headerPairs[] = $objectNumber . ' ' . strlen($body);
        $indexes[$objectNumber] = count($indexes);
        $body .= $memberBody . "\n";
    }

    $header = implode(' ', $headerPairs);
    $encoded = gzcompress($header . "\n" . $body);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to compress object-stream smoke fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $encoded,
    ];
};

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale object-stream Prev metadata page) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current object-stream Prev metadata page) Tj T* (Compressed dictionaries selected) Tj ET';
$stalePayload = '<wp-export><post id="stale-object-stream-prev"/></wp-export>';
$currentPayload = '<wp-export><post id="current-object-stream-prev"/></wp-export>';
$staleXmp = gzcompress($xmpPacket('Stale Object Stream Prev XMP Title', 'Stale direct metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Object Stream Prev XMP Title', 'Current compressed catalog metadata selected'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress object-stream metadata smoke XMP.');
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
$staleInfoOffset = $addObject(6, 0, '<< /Title (Stale Object Stream Prev Info Title) /Author (Stale Object Stream Author) /Producer (Stale Object Stream Producer) >>');
$staleMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");
$staleNameTreeOffset = $addObject(8, 0, '<< /Names [(stale-object-stream-prev.xml) 10 0 R] >>');
$staleFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (stale-object-stream-prev.xml) /Desc (Stale object-stream attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
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

$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$currentMetadataOffset = $addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$currentEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$compressedObjects = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> >>',
    6 => '<< /Title (Current Object Stream Prev Info Title) /Author (Current Object Stream Author) /Producer (Current Object Stream Producer) >>',
    8 => '<< /Names [(current-object-stream-prev.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (current-object-stream-prev.xml) /Desc (Current object-stream attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
]);
$carrierOffset = $addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . $compressedObjects['count'] . ' /First ' . $compressedObjects['first'] . ' /Filter /FlateDecode /Length ' . strlen($compressedObjects['content']) . " >>\nstream\n{$compressedObjects['content']}\nendstream"
);

$rows = ''
    . $xrefStreamRow(2, 20, $compressedObjects['indexes'][1])
    . $xrefStreamRow(1, $currentPagesOffset, 0)
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(2, 20, $compressedObjects['indexes'][6])
    . $xrefStreamRow(1, $currentMetadataOffset, 0)
    . $xrefStreamRow(2, 20, $compressedObjects['indexes'][8])
    . $xrefStreamRow(0, 0, 0)
    . $xrefStreamRow(2, 20, $compressedObjects['indexes'][10])
    . $xrefStreamRow(1, $currentEmbeddedOffset, 0)
    . $xrefStreamRow(1, $carrierOffset, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Prev ' . $previousXrefOffset . ' /Index [1 11 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedFiles = json_encode($files, JSON_UNESCAPED_SLASHES);

$results = [
    'current_object_stream_xmp_selected' => ($metadata['title'] ?? null) === 'Current Object Stream Prev XMP Title',
    'current_object_stream_info_selected' => ($metadata['info']['Title'] ?? null) === 'Current Object Stream Prev Info Title',
    'current_catalog_language_selected' => ($metadata['language'] ?? null) === 'en-US',
    'current_attachment_selected' => count($files) === 1 && ($files[0]['filename'] ?? null) === 'current-object-stream-prev.xml',
    'current_attachment_payload_selected' => ($files[0]['content_sha256'] ?? null) === hash('sha256', $currentPayload),
    'current_text_selected' => str_contains($text, 'Current object-stream Prev metadata page'),
    'stale_prev_metadata_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Object Stream'),
    'stale_prev_attachment_excluded' => is_string($encodedFiles) && !str_contains($encodedFiles, 'stale-object-stream-prev'),
    'stale_prev_text_excluded' => !str_contains($text, 'Stale object-stream Prev metadata page'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($results as $name => $value) {
    echo $name . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}

foreach ($results as $name => $value) {
    if (str_starts_with($name, 'executes_')) {
        continue;
    }

    if ($value !== true) {
        exit(1);
    }
}

if ($results['executes_python_or_models'] !== false || $results['executes_external_pdf_tools'] !== false) {
    exit(1);
}
