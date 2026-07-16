<?php

declare(strict_types=1);

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
        . '<xmp:CreateDate>2026-06-02T20:08:44Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale generation zero indexed page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation indexed page) Tj T* (Prev duplicate metadata skipped) Tj ET';
$staleXmp = gzcompress($xmpPacket('Stale Indexed Generation XMP Title', 'Stale generation zero xref metadata must not win'));
$currentXmp = gzcompress($xmpPacket('Current Indexed Generation XMP Title', 'Current sparse xref-stream generation metadata wins'));
if (!is_string($staleXmp) || !is_string($currentXmp)) {
    throw new RuntimeException('Unable to compress xref-stream generation metadata smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $offset, int $generation): string => chr($type) . pack('N', $offset) . chr($generation);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 7 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Title (Stale Indexed Info Title) /Author (Stale Indexed Author) /Producer (Stale Indexed Producer) >>');
$addObject(7, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream");

$previousRows = ''
    . $xrefRow(1, $offsets['1:0'], 0)
    . $xrefRow(1, $offsets['2:0'], 0)
    . $xrefRow(1, $offsets['3:0'], 0)
    . $xrefRow(1, $offsets['4:0'], 0)
    . $xrefRow(1, $offsets['5:0'], 0)
    . $xrefRow(1, $offsets['6:0'], 0)
    . $xrefRow(1, $offsets['7:0'], 0);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream generation smoke fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Info 6 0 R /Index [1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R /Lang (en-US) /Metadata 7 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 1, '<< /Title (Current Indexed Info Title) /Author (Current Indexed Author) /Producer (Current Indexed Producer) >>');
$addObject(7, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");

$currentRows = ''
    . $xrefRow(1, $offsets['1:1'], 1)
    . $xrefRow(1, $offsets['2:1'], 1)
    . $xrefRow(1, $offsets['3:1'], 1)
    . $xrefRow(1, $offsets['4:1'], 1)
    . $xrefRow(1, $offsets['6:1'], 1)
    . $xrefRow(1, $offsets['7:1'], 1)
    . $xrefRow(1, $offsets['4:0'], 0)
    . $xrefRow(1, $offsets['6:0'], 0)
    . $xrefRow(1, $offsets['7:0'], 0);
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream generation smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Info 6 1 R /Prev ' . $previousXrefOffset . ' /Index [1 4 6 2 4 1 6 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-xref-stream-prev-generation-index-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream /Prev chain with sparse /Index generation rows preserves first current rows before stale duplicate rows',
    'current_metadata_title_selected' => ($metadata['title'] ?? null) === 'Current Indexed Generation XMP Title',
    'current_info_title_selected' => ($metadata['info']['Title'] ?? null) === 'Current Indexed Info Title',
    'current_generation_text_selected' => str_contains($plainText, 'Current generation indexed page'),
    'stale_generation_metadata_excluded' => is_string($encodedMetadata) && !str_contains($encodedMetadata, 'Stale Indexed'),
    'stale_generation_text_excluded' => !str_contains($plainText, 'Stale generation zero indexed page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars((string) ($metadata['title'] ?? 'PDF metadata review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
