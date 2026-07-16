<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$xmpPacket = static function (
    string $title,
    string $description,
    string $date,
    string $encoding
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<?xml version="1.0" encoding="' . htmlspecialchars($encoding, ENT_XML1) . '"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Ren' . "\u{00e9}" . 'e Declared Encoding Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-declared-encoding</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Declared Encoding Producer</pdf:Producer>'
        . '<xmp:CreatorTool>D' . "\u{00e9}" . 'clared Encoding Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T14:09:12Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$encodedPacket = static function (string $title, string $description, string $date) use ($xmpPacket): string {
    $bytes = iconv('UTF-8', 'Windows-1252', $xmpPacket($title, $description, $date, 'Windows-1252'));
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to encode declared XMP smoke packet.');
    }

    return $bytes;
};

$currentPacket = $encodedPacket(
    'Caf' . "\u{00e9}" . ' WordPress Declared Encoding XMP Title',
    'R' . "\u{00e9}" . 'sum' . "\u{00e9}" . ' metadata remains document XMP before WordPress import',
    '2026-06-05T10:09:12-04:00'
);
$trailingPacket = $encodedPacket(
    'Trailing WordPress Declared Encoding Decoy Title',
    'Trailing declared-encoding packet stays outside the active metadata boundary',
    '2026-06-05T14:59:59Z'
);

$metadataBytes = $currentPacket . "\0\0" . $trailingPacket;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress declared XMP smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Declared Encoding XMP WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Declared Encoding Info Title) /Author (Info Declared Author) /Producer (Info Declared Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'Caf' . "\u{00e9}" . ' WordPress Declared Encoding XMP Title') {
    throw new RuntimeException('Expected declared Windows-1252 XMP title to decode before WordPress import.');
}
if (($metadata['xmp']['packet_encoding'] ?? null) !== 'Windows-1252') {
    throw new RuntimeException('Expected packet encoding to come from the active XMP XML declaration.');
}
if (array_key_exists('encoding_fallback', $metadata['xmp'] ?? [])) {
    throw new RuntimeException('Expected declared XMP packet decoding, not legacy encoding fallback decoding.');
}
if (str_contains($encodedMetadata, 'Trailing WordPress Declared Encoding Decoy Title')) {
    throw new RuntimeException('Expected trailing declared-encoding XMP packet to stay outside document metadata.');
}
if (str_contains($plainText, 'WordPress Declared Encoding XMP Title')) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-declared-encoding-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-declared-encoding-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-declared-encoding-boundary',
    'native_boundary' => 'active xpacket XML declarations decode non-UTF XMP before legacy fallback',
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'description' => $metadata['description'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'decoded_to_utf8' => $metadata['xmp']['decoded_to_utf8'] ?? false,
    'encoding_fallback_used' => array_key_exists('encoding_fallback', $metadata['xmp'] ?? []),
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'trailing_decoy_excluded' => !str_contains($encodedMetadata, 'Trailing WordPress Declared Encoding Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Declared Encoding XMP Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
