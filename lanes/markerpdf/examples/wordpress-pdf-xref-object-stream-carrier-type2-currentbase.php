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
        . '<xmp:CreateDate>2026-06-05T20:06:39Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$objectStream = static function (array $members): array {
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress object-stream carrier type-2 smoke fixture.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'count' => count($members),
        'content' => $compressed,
    ];
};

$currentText = 'BT /F1 12 Tf 72 720 Td (Current type-2 carrier page) Tj T* (Metadata and attachments stay compressed) Tj ET';
$currentPayload = '<wp-export><post id="current-type2-carrier"/></wp-export>';
$currentXmp = gzcompress($xmpPacket(
    'Current Type2 Carrier XMP Title',
    'Object stream carrier remains direct before WordPress import'
));
if (!is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress type-2 carrier XMP smoke fixture.');
}

$carrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 7 0 R /Names << /EmbeddedFiles 8 0 R >> /AF [10 0 R] >>',
    6 => '<< /Title (Current Type2 Carrier Info Title) /Author (Current Type2 Carrier Author) /Producer (Current Type2 Carrier Producer) >>',
    8 => '<< /Names [(current-type2-carrier.xml) 10 0 R] >>',
    10 => '<< /Type /Filespec /F (current-type2-carrier.xml) /Desc (Current type-2 carrier attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>',
]);
$decoyCarrier = $objectStream([
    20 => '<< /Type /ObjStm /Note (malformed compressed carrier decoy) >>',
]);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . md5($currentPayload) . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(20, 0, '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream");
$addObject(21, 0, '<< /Type /ObjStm /N ' . $decoyCarrier['count'] . ' /First ' . $decoyCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($decoyCarrier['content']) . " >>\nstream\n{$decoyCarrier['content']}\nendstream");

$xrefRows = ''
    . $row(2, 20, $carrier['indexes'][1])
    . $row(1, $offsets['2:0'])
    . $row(1, $offsets['3:0'])
    . $row(1, $offsets['4:0'])
    . $row(1, $offsets['5:0'])
    . $row(2, 20, $carrier['indexes'][6])
    . $row(1, $offsets['7:0'])
    . $row(2, 20, $carrier['indexes'][8])
    . $row(0, 0, 0)
    . $row(2, 20, $carrier['indexes'][10])
    . $row(1, $offsets['11:0'])
    . $row(2, 21, $decoyCarrier['indexes'][20])
    . $row(1, $offsets['21:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress type-2 carrier xref smoke rows.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Info 6 0 R /Index [1 11 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$text = (new PdfTextExtractor())->extractPlainText($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (
    $text !== "Current type-2 carrier page\nMetadata and attachments stay compressed"
    || ($metadata['title'] ?? null) !== 'Current Type2 Carrier XMP Title'
    || ($files[0]['filename'] ?? null) !== 'current-type2-carrier.xml'
    || ($summary['attachments'][0]['filename'] ?? null) !== 'current-type2-carrier.xml'
    || str_contains($summaryJson, $currentPayload)
    || str_contains($summaryJson, 'malformed compressed carrier decoy')
) {
    throw new RuntimeException('Expected direct object-stream carrier metadata and attachment review without payload leakage.');
}

echo "<!-- markerpdf-xref-object-stream-carrier-type2-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'xref-stream type-2 row targeting direct ObjStm carrier',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'metadata_title' => $metadata['title'] ?? null,
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'direct_carrier_preserved' => true,
    'payload_bytes_omitted' => !str_contains($summaryJson, $currentPayload),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
echo "<!-- wp:list -->\n<ul>\n";
echo '<li>'
    . htmlspecialchars((string) ($summary['attachments'][0]['filename'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . ' - '
    . htmlspecialchars((string) ($summary['attachments'][0]['relationship'] ?? 'unassociated'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
