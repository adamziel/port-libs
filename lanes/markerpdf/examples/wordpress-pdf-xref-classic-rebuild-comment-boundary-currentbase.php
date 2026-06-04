<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta>';
};

$currentXmp = $xmpPacket('Current Comment-Bounded XRef Title');
$commentXmp = $xmpPacket('Comment XRef Decoy Title');
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment bounded page) Tj T* (Comment xref ignored) Tj ET';
$commentContent = 'BT /F1 12 Tf 72 720 Td (Comment xref decoy page) Tj T* (Comment root leak) Tj ET';

$pdf = "%PDF-1.4\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 6 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, "<< /Type /Metadata /Subtype /XML /Length " . strlen($currentXmp) . " >>\nstream\n{$currentXmp}\nendstream");
$addObject(7, '<< /Title (Current Comment-Bounded Info Title) /Author (Current Comment Importer) >>');

$pdf .= "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow($offsets[7])
    . "trailer\n<< /Size 28 /Root 1 0 R /Info 7 0 R >>\n";

$addObject(20, '<< /Type /Catalog /Pages 21 0 R /Metadata 26 0 R >>');
$addObject(21, '<< /Type /Pages /Kids [22 0 R] /Count 1 >>');
$addObject(22, '<< /Type /Page /Parent 21 0 R /Resources << /Font << /F1 23 0 R >> >> /Contents 24 0 R >>');
$addObject(23, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(24, "<< /Length " . strlen($commentContent) . " >>\nstream\n{$commentContent}\nendstream");
$addObject(26, "<< /Type /Metadata /Subtype /XML /Length " . strlen($commentXmp) . " >>\nstream\n{$commentXmp}\nendstream");
$addObject(27, '<< /Title (Comment XRef Decoy Info Title) /Author (Comment Decoy Importer) >>');

$pdf .= "% xref\n"
    . "20 8\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . $xrefRow($offsets[22])
    . $xrefRow($offsets[23])
    . $xrefRow($offsets[24])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[26])
    . $xrefRow($offsets[27])
    . "trailer\n<< /Size 28 /Root 20 0 R /Info 27 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-xref-classic-rebuild-comment-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'classic xref rebuild ignores xref keywords inside PDF comments before metadata root selection',
    'uses_current_classic_trailer_root' => str_contains($plainText, 'Current comment bounded page'),
    'keeps_current_metadata_root' => ($metadata['title'] ?? null) === 'Current Comment-Bounded XRef Title',
    'keeps_current_info_root' => ($metadata['info']['Title'] ?? null) === 'Current Comment-Bounded Info Title',
    'excludes_commented_xref_page' => !str_contains($plainText, 'Comment xref decoy page'),
    'excludes_commented_xref_metadata' => !str_contains($encodedMetadata, 'Comment XRef Decoy'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
