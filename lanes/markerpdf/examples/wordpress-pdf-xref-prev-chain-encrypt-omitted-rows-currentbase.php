<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Omitted Encrypt XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Current same-generation Encrypt dictionary selected</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-06T20:35:43Z</xmp:CreateDate>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress omitted-Encrypt smoke XMP stream.');
}

$staleText = 'BT /F1 12 Tf 72 720 Td (Stale omitted Encrypt row text leak) Tj ET';
$currentText = 'BT /F1 12 Tf 72 720 Td (Current omitted Encrypt row text blocked) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleText) . " >>\nstream\n{$staleText}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Stale Omitted Encrypt Info Title) /Author (Stale Encrypt Author) >>');
$addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 7\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0:0'])
    . $xrefTableRow($offsets['2:0:1'])
    . $xrefTableRow($offsets['3:0:2'])
    . $xrefTableRow($offsets['4:0:3'])
    . $xrefTableRow($offsets['5:0:4'])
    . $xrefTableRow($offsets['6:0:5'])
    . "30 1\n"
    . $xrefTableRow($offsets['30:0:6'])
    . "trailer\n<< /Size 40 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentText) . " >>\nstream\n{$currentText}\nendstream");
$addObject(6, 0, '<< /Title (Current Omitted Encrypt Info Title) /Author (Current Encrypt Author) /Producer (Current Encrypt Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream");
$addObject(30, 0, '<< /Filter /Standard /V 4 /R 4 /Length 128 /O <FEEDFACE> /U <BEEFBEEF> /P -64 /EncryptMetadata false >>');

$currentRows = ''
    . $xrefStreamRow(1, $offsets['1:0:7'], 0)
    . $xrefStreamRow(1, $offsets['2:0:8'], 0)
    . $xrefStreamRow(1, $offsets['3:0:9'], 0)
    . $xrefStreamRow(1, $offsets['4:0:10'], 0)
    . $xrefStreamRow(1, $offsets['5:0:4'], 0)
    . $xrefStreamRow(1, $offsets['6:0:11'], 0)
    . $xrefStreamRow(1, $offsets['7:0:12'], 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress omitted-Encrypt smoke xref stream.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /Info 6 0 R /Encrypt 30 0 R /Prev ' . $previousXrefOffset . ' /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES) ?: '';

$flags = [
    'current_encrypt_dictionary_selected' => ($metadata['encryption']['standard_permissions']['hex'] ?? null) === 'FFFFFFC0',
    'current_xmp_preserved' => ($metadata['title'] ?? null) === 'Current Omitted Encrypt XMP Title',
    'encrypt_metadata_false_selected' => ($metadata['encryption']['encrypt_metadata'] ?? null) === false,
    'stale_prev_encrypt_suppressed' => !str_contains($encoded, 'DEADBEEF') && !str_contains($encoded, 'Stale Omitted Encrypt'),
    'encrypted_text_blocked' => $text === '' && ($preflight['text_extraction_policy'] ?? null) === 'blocked_without_decryption',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($flags as $name => $value) {
    echo $name . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}
